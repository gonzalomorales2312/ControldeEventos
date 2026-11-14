const express = require('express');
const pool = require('../config/db');

const router = express.Router();

const CAMPOS = ['descripcion', 'valor', 'existencia'];

// Valida los datos. Si parcial=true (PUT) solo valida los campos enviados.
// existencia es opcional al crear (por defecto 0).
function validar(body, parcial = false) {
  const errores = [];
  const b = body || {};
  const requerido = (c) => !parcial || b[c] !== undefined;

  if (requerido('descripcion') && (typeof b.descripcion !== 'string' || !b.descripcion.trim() || b.descripcion.trim().length > 255)) {
    errores.push('descripcion es obligatoria (texto, máximo 255 caracteres)');
  }
  if (requerido('valor') && !(typeof b.valor === 'number' && Number.isFinite(b.valor) && b.valor >= 0)) {
    errores.push('valor (valor total de la existencia) debe ser un número mayor o igual a 0');
  }
  if (b.existencia !== undefined && !(Number.isInteger(b.existencia) && b.existencia >= 0)) {
    errores.push('existencia debe ser un entero mayor o igual a 0');
  }
  return errores;
}

function errorInterno(res, err) {
  console.error(err);
  res.status(500).json({ error: 'Error interno del servidor' });
}

async function buscar(id) {
  const [rows] = await pool.query('SELECT * FROM inventario WHERE id = ?', [id]);
  return rows[0];
}

// GET /api/inventario?page=1&limit=20&buscar=texto&estado=activos|inactivos|todos&agotados=true
// Incluye valor_total_inventario = suma de "valor" (valor total de cada artículo) del filtro aplicado.
router.get('/', async (req, res) => {
  try {
    const page = Math.max(parseInt(req.query.page) || 1, 1);
    const limit = Math.min(Math.max(parseInt(req.query.limit) || 20, 1), 100);
    const offset = (page - 1) * limit;
    const estado = req.query.estado || 'activos';

    const condiciones = [];
    const params = [];
    if (estado === 'activos') condiciones.push('activo = 1');
    else if (estado === 'inactivos') condiciones.push('activo = 0');
    else if (estado !== 'todos') {
      return res.status(400).json({ error: 'estado debe ser: activos, inactivos o todos' });
    }
    if (req.query.buscar) {
      condiciones.push('descripcion LIKE ?');
      params.push(`%${req.query.buscar}%`);
    }
    if (req.query.agotados === 'true') condiciones.push('existencia = 0');
    const where = condiciones.length ? `WHERE ${condiciones.join(' AND ')}` : '';

    const [[resumen]] = await pool.query(
      `SELECT COUNT(*) AS total, COALESCE(SUM(valor), 0) AS valor_total
         FROM inventario ${where}`,
      params
    );
    const [rows] = await pool.query(
      `SELECT * FROM inventario ${where} ORDER BY id DESC LIMIT ? OFFSET ?`,
      [...params, limit, offset]
    );

    res.json({
      page,
      limit,
      total: resumen.total,
      valor_total_inventario: Math.round(Number(resumen.valor_total) * 100) / 100,
      data: rows,
    });
  } catch (err) {
    errorInterno(res, err);
  }
});

// POST /api/inventario/salidas  { "idinventario": 1, "cantidad": 2, "motivo": "...", "observaciones": "..." }
// Registra la salida y rebaja la existencia de forma atómica (nunca deja existencia negativa).
router.post('/salidas', async (req, res) => {
  const b = req.body || {};
  const errores = [];
  if (!Number.isInteger(b.idinventario)) errores.push('idinventario es obligatorio (número entero)');
  if (!(Number.isInteger(b.cantidad) && b.cantidad > 0)) errores.push('cantidad debe ser un entero mayor a 0');
  for (const c of ['motivo', 'observaciones']) {
    if (b[c] !== undefined && b[c] !== null && typeof b[c] !== 'string') errores.push(`${c} debe ser texto`);
  }
  if (typeof b.motivo === 'string' && b.motivo.trim().length > 255) errores.push('motivo no puede superar 255 caracteres');
  if (errores.length) return res.status(400).json({ errores });

  const limpio = (v) => (typeof v === 'string' && v.trim() ? v.trim() : null);

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    // El valor baja proporcionalmente (se conserva el precio unitario). Orden de SET importa:
    // valor se calcula con la existencia anterior; si queda en 0, el valor también queda en 0.
    const [upd] = await conn.query(
      `UPDATE inventario
          SET valor = IF(existencia = ?, 0, ROUND(valor - valor * ? / existencia, 2)),
              existencia = existencia - ?
        WHERE id = ? AND activo = 1 AND existencia >= ?`,
      [b.cantidad, b.cantidad, b.cantidad, b.idinventario, b.cantidad]
    );
    if (!upd.affectedRows) {
      const [rows] = await conn.query('SELECT activo, existencia FROM inventario WHERE id = ?', [b.idinventario]);
      await conn.rollback();
      if (!rows.length) return res.status(404).json({ error: 'Artículo no encontrado' });
      if (!rows[0].activo) return res.status(409).json({ error: 'El artículo está inactivo' });
      return res.status(409).json({
        error: 'Existencia insuficiente',
        existencia_disponible: rows[0].existencia,
        cantidad_solicitada: b.cantidad,
      });
    }

    const [s] = await conn.query(
      'INSERT INTO salidas_inventario (idinventario, cantidad, motivo, observaciones, idusuario) VALUES (?, ?, ?, ?, ?)',
      [b.idinventario, b.cantidad, limpio(b.motivo), limpio(b.observaciones), req.usuario.id]
    );
    await conn.commit();

    const [[salida]] = await pool.query('SELECT * FROM salidas_inventario WHERE id = ?', [s.insertId]);
    const [[articulo]] = await pool.query('SELECT * FROM inventario WHERE id = ?', [b.idinventario]);
    res.status(201).json({ salida, articulo });
  } catch (err) {
    try { await conn.rollback(); } catch (_) { /* conexión ya cerrada */ }
    errorInterno(res, err);
  } finally {
    conn.release();
  }
});

// GET /api/inventario/salidas?idinventario=1&page=1&limit=20  (historial de salidas)
router.get('/salidas', async (req, res) => {
  try {
    const page = Math.max(parseInt(req.query.page) || 1, 1);
    const limit = Math.min(Math.max(parseInt(req.query.limit) || 20, 1), 100);
    const offset = (page - 1) * limit;

    const params = [];
    let where = '';
    if (req.query.idinventario) {
      where = 'WHERE s.idinventario = ?';
      params.push(req.query.idinventario);
    }

    const [[{ total }]] = await pool.query(`SELECT COUNT(*) AS total FROM salidas_inventario s ${where}`, params);
    const [rows] = await pool.query(
      `SELECT s.*, i.descripcion AS articulo
         FROM salidas_inventario s JOIN inventario i ON i.id = s.idinventario
         ${where} ORDER BY s.id DESC LIMIT ? OFFSET ?`,
      [...params, limit, offset]
    );
    res.json({ page, limit, total, data: rows });
  } catch (err) {
    errorInterno(res, err);
  }
});

// GET /api/inventario/:id
router.get('/:id', async (req, res) => {
  try {
    const fila = await buscar(req.params.id);
    if (!fila) return res.status(404).json({ error: 'Artículo no encontrado' });
    res.json(fila);
  } catch (err) {
    errorInterno(res, err);
  }
});

// POST /api/inventario
router.post('/', async (req, res) => {
  const body = req.body || {};
  const errores = validar(body);
  if (errores.length) return res.status(400).json({ errores });

  try {
    const [result] = await pool.query(
      'INSERT INTO inventario (descripcion, valor, existencia) VALUES (?, ?, ?)',
      [body.descripcion.trim(), body.valor, body.existencia ?? 0]
    );
    res.status(201).json(await buscar(result.insertId));
  } catch (err) {
    errorInterno(res, err);
  }
});

// PUT /api/inventario/:id  (acepta uno o varios campos)
router.put('/:id', async (req, res) => {
  const body = req.body || {};
  const enviados = CAMPOS.filter((c) => body[c] !== undefined);
  if (!enviados.length) return res.status(400).json({ error: 'Envíe al menos un campo a actualizar' });

  const errores = validar(body, true);
  if (errores.length) return res.status(400).json({ errores });

  try {
    const [result] = await pool.query(
      `UPDATE inventario SET ${enviados.map((c) => `${c} = ?`).join(', ')} WHERE id = ?`,
      [...enviados.map((c) => (c === 'descripcion' ? body[c].trim() : body[c])), req.params.id]
    );
    if (!result.affectedRows) return res.status(404).json({ error: 'Artículo no encontrado' });
    res.json(await buscar(req.params.id));
  } catch (err) {
    errorInterno(res, err);
  }
});

// PATCH /api/inventario/:id/inactivar  y  /activar
for (const [accion, valor] of [['inactivar', 0], ['activar', 1]]) {
  router.patch(`/:id/${accion}`, async (req, res) => {
    try {
      const [result] = await pool.query('UPDATE inventario SET activo = ? WHERE id = ?', [valor, req.params.id]);
      if (!result.affectedRows) return res.status(404).json({ error: 'Artículo no encontrado' });
      res.json(await buscar(req.params.id));
    } catch (err) {
      errorInterno(res, err);
    }
  });
}

module.exports = router;

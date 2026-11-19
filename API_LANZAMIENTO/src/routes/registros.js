const express = require('express');
const pool = require('../config/db');

const router = express.Router();

const CAMPOS = ['nombre_lanzado', 'nombre_lider', 'subred', 'numero_boleta', 'monto_boleta', 'imagen_boleta'];

// Columnas del listado: no incluye la imagen (pesada); se indica con tiene_imagen y se obtiene con GET /:id
const COLUMNAS_LISTA =
  'id, nombre_lanzado, nombre_lider, subred, numero_boleta, monto_boleta, (imagen_boleta IS NOT NULL) AS tiene_imagen, activo, creado_en, actualizado_en';

// Acepta base64 puro o un data URI (data:image/png;base64,....)
const RE_BASE64 = /^(data:image\/[a-zA-Z0-9.+-]+;base64,)?[A-Za-z0-9+/\r\n]+={0,2}$/;

// nombre_lanzado es opcional: en blanco significa que quien paga es el líder
const limpioLanzado = (v) => (typeof v === 'string' ? v.trim() : '');

// Valida los datos. Si parcial=true (PUT) solo valida los campos enviados.
function validar(body, parcial = false) {
  const errores = [];
  const b = body || {};
  const requerido = (c) => !parcial || b[c] !== undefined;

  for (const c of ['nombre_lider', 'subred', 'numero_boleta']) {
    if (requerido(c) && (typeof b[c] !== 'string' || !b[c].trim())) errores.push(`${c} es obligatorio (texto)`);
  }
  if (b.nombre_lanzado !== undefined && b.nombre_lanzado !== null && typeof b.nombre_lanzado !== 'string') {
    errores.push('nombre_lanzado debe ser texto (déjelo en blanco si paga el líder)');
  }
  if (b.imagen_boleta !== undefined && b.imagen_boleta !== null && b.imagen_boleta !== '') {
    if (typeof b.imagen_boleta !== 'string' || !RE_BASE64.test(b.imagen_boleta)) {
      errores.push('imagen_boleta debe ser una imagen en base64 (texto)');
    }
  }
  if (typeof b.subred === 'string' && b.subred.trim().length > 100) {
    errores.push('subred no puede superar 100 caracteres');
  }
  if (requerido('monto_boleta') && !(typeof b.monto_boleta === 'number' && b.monto_boleta >= 0)) {
    errores.push('monto_boleta debe ser un número mayor o igual a 0');
  }
  return errores;
}

function errorInterno(res, err) {
  // MariaDB corta la conexión (ECONNRESET) cuando el paquete supera max_allowed_packet
  if (['ER_NET_PACKET_TOO_LARGE', 'ER_WARN_ALLOWED_PACKET_OVERFLOWED', 'ECONNRESET'].includes(err.code)) {
    return res.status(413).json({ error: 'La imagen es demasiado grande para el límite max_allowed_packet de MariaDB' });
  }
  console.error(err);
  res.status(500).json({ error: 'Error interno del servidor' });
}

// Datos del ingreso que se genera a partir de un registro:
// si hay nombre_lanzado paga el lanzado; si está en blanco, paga el líder.
function datosIngreso(r) {
  const lanzado = limpioLanzado(r.nombre_lanzado);
  return {
    descripcion: `Boleta ${r.numero_boleta}`,
    vvalor: r.monto_boleta,
    tipopersona: lanzado ? 'lanzado' : 'lider',
    nombrepersona: lanzado || r.nombre_lider,
  };
}

// Alcance por rol: admin (subred null) ve todo; los demás solo su subred.
function alcance(req) {
  const subred = req.usuario.subred;
  return subred === null
    ? { restringido: false, sql: '', params: [] }
    : { restringido: true, sql: ' AND subred = ?', params: [subred] };
}

// ¿El body intenta escribir en una subred distinta a la del usuario?
function subredAjena(req, body) {
  const { restringido } = alcance(req);
  if (!restringido || body.subred === undefined) return false;
  return String(body.subred).trim().toLowerCase() !== req.usuario.subred.trim().toLowerCase();
}

const MSG_SUBRED_AJENA = 'Su rol solo permite trabajar con registros de su propia subred';

// GET /api/registros?page=1&limit=20&buscar=texto&estado=activos|inactivos|todos
router.get('/', async (req, res) => {
  try {
    const page = Math.max(parseInt(req.query.page) || 1, 1);
    const limit = Math.min(Math.max(parseInt(req.query.limit) || 20, 1), 100);
    const offset = (page - 1) * limit;
    const buscar = req.query.buscar ? `%${req.query.buscar}%` : null;
    const estado = req.query.estado || 'activos';

    const condiciones = [];
    const params = [];
    if (estado === 'activos') condiciones.push('activo = 1');
    else if (estado === 'inactivos') condiciones.push('activo = 0');
    else if (estado !== 'todos') {
      return res.status(400).json({ error: 'estado debe ser: activos, inactivos o todos' });
    }
    if (buscar) {
      condiciones.push('(nombre_lanzado LIKE ? OR nombre_lider LIKE ? OR subred LIKE ? OR numero_boleta LIKE ?)');
      params.push(buscar, buscar, buscar, buscar);
    }
    if (req.usuario.subred !== null) {
      condiciones.push('subred = ?');
      params.push(req.usuario.subred);
    }
    const where = condiciones.length ? `WHERE ${condiciones.join(' AND ')}` : '';

    const [[{ total }]] = await pool.query(`SELECT COUNT(*) AS total FROM registros ${where}`, params);
    const [rows] = await pool.query(
      `SELECT ${COLUMNAS_LISTA} FROM registros ${where} ORDER BY id DESC LIMIT ? OFFSET ?`,
      [...params, limit, offset]
    );

    res.json({ page, limit, total, data: rows });
  } catch (err) {
    errorInterno(res, err);
  }
});

// GET /api/registros/:id
router.get('/:id', async (req, res) => {
  try {
    const a = alcance(req);
    const [rows] = await pool.query(`SELECT * FROM registros WHERE id = ?${a.sql}`, [req.params.id, ...a.params]);
    if (!rows.length) return res.status(404).json({ error: 'Registro no encontrado' });
    res.json(rows[0]);
  } catch (err) {
    errorInterno(res, err);
  }
});

// POST /api/registros  (un rol restringido registra siempre en su subred; "subred" es opcional para él)
// Además genera automáticamente el ingreso de la boleta (en la misma transacción).
router.post('/', async (req, res) => {
  const body = { ...(req.body || {}) };
  if (subredAjena(req, body)) return res.status(403).json({ error: MSG_SUBRED_AJENA });
  if (req.usuario.subred !== null) body.subred = req.usuario.subred;

  const errores = validar(body);
  if (errores.length) return res.status(400).json({ errores });
  body.nombre_lanzado = limpioLanzado(body.nombre_lanzado);

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    const [result] = await conn.query(
      `INSERT INTO registros (${CAMPOS.join(', ')}) VALUES (${CAMPOS.map(() => '?').join(', ')})`,
      CAMPOS.map((c) => (c === 'imagen_boleta' ? body[c] || null : body[c]))
    );
    const [[registro]] = await conn.query('SELECT * FROM registros WHERE id = ?', [result.insertId]);

    const d = datosIngreso(registro);
    const [ing] = await conn.query(
      `INSERT INTO ingresosegresos (tipoingresoegreso, descripcion, vvalor, observaciones, tipopersona, nombrepersona, idregistro)
       VALUES ('ingreso', ?, ?, ?, ?, ?, ?)`,
      [d.descripcion, d.vvalor, `Generado automáticamente desde el registro #${registro.id}`, d.tipopersona, d.nombrepersona, registro.id]
    );
    const [[ingreso]] = await conn.query('SELECT * FROM ingresosegresos WHERE id = ?', [ing.insertId]);

    await conn.commit();
    res.status(201).json({ ...registro, ingreso_generado: ingreso });
  } catch (err) {
    try { await conn.rollback(); } catch (_) { /* conexión ya cerrada */ }
    if (err.code === 'ER_DUP_ENTRY') return res.status(409).json({ error: 'El número de boleta ya existe' });
    errorInterno(res, err);
  } finally {
    conn.release();
  }
});

// PUT /api/registros/:id  (acepta uno o varios campos). Mantiene sincronizado su ingreso generado.
router.put('/:id', async (req, res) => {
  const body = req.body || {};
  const enviados = CAMPOS.filter((c) => body[c] !== undefined);
  if (!enviados.length) return res.status(400).json({ error: 'Envíe al menos un campo a actualizar' });

  if (subredAjena(req, body)) return res.status(403).json({ error: MSG_SUBRED_AJENA });

  const errores = validar(body, true);
  if (errores.length) return res.status(400).json({ errores });

  const valor = (c) =>
    c === 'nombre_lanzado' ? limpioLanzado(body[c]) : c === 'imagen_boleta' ? body[c] || null : body[c];

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    const a = alcance(req);
    const [result] = await conn.query(
      `UPDATE registros SET ${enviados.map((c) => `${c} = ?`).join(', ')} WHERE id = ?${a.sql}`,
      [...enviados.map(valor), req.params.id, ...a.params]
    );
    if (!result.affectedRows) {
      await conn.rollback();
      return res.status(404).json({ error: 'Registro no encontrado' });
    }

    const [[registro]] = await conn.query('SELECT * FROM registros WHERE id = ?', [req.params.id]);
    const d = datosIngreso(registro);
    await conn.query(
      'UPDATE ingresosegresos SET descripcion = ?, vvalor = ?, tipopersona = ?, nombrepersona = ? WHERE idregistro = ?',
      [d.descripcion, d.vvalor, d.tipopersona, d.nombrepersona, registro.id]
    );

    await conn.commit();
    res.json(registro);
  } catch (err) {
    try { await conn.rollback(); } catch (_) { /* conexión ya cerrada */ }
    if (err.code === 'ER_DUP_ENTRY') return res.status(409).json({ error: 'El número de boleta ya existe' });
    errorInterno(res, err);
  } finally {
    conn.release();
  }
});

// PATCH /api/registros/:id/inactivar  y  /api/registros/:id/activar  (también inactiva/activa su ingreso)
for (const [accion, valor] of [['inactivar', 0], ['activar', 1]]) {
  router.patch(`/:id/${accion}`, async (req, res) => {
    const conn = await pool.getConnection();
    try {
      await conn.beginTransaction();

      const a = alcance(req);
      const [result] = await conn.query(`UPDATE registros SET activo = ? WHERE id = ?${a.sql}`, [
        valor,
        req.params.id,
        ...a.params,
      ]);
      if (!result.affectedRows) {
        await conn.rollback();
        return res.status(404).json({ error: 'Registro no encontrado' });
      }
      await conn.query('UPDATE ingresosegresos SET activo = ? WHERE idregistro = ?', [valor, req.params.id]);

      const [[registro]] = await conn.query('SELECT * FROM registros WHERE id = ?', [req.params.id]);
      await conn.commit();
      res.json(registro);
    } catch (err) {
      try { await conn.rollback(); } catch (_) { /* conexión ya cerrada */ }
      errorInterno(res, err);
    } finally {
      conn.release();
    }
  });
}

module.exports = router;

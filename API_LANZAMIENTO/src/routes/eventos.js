const express = require('express');
const pool = require('../config/db');
const auth = require('../middleware/auth');

const router = express.Router();

const COLUMNAS = 'idevento, tipoevento, descripcionevento, activo, creado_en, actualizado_en';
const CAMPOS = ['tipoevento', 'descripcionevento'];

// Valida los datos. Si parcial=true (PUT) solo valida los campos enviados.
function validar(body, parcial = false) {
  const errores = [];
  const b = body || {};
  const requerido = (c) => !parcial || b[c] !== undefined;

  if (requerido('tipoevento') && (typeof b.tipoevento !== 'string' || !b.tipoevento.trim() || b.tipoevento.trim().length > 100)) {
    errores.push('tipoevento es obligatorio (texto, máximo 100 caracteres)');
  }
  if (requerido('descripcionevento') && (typeof b.descripcionevento !== 'string' || !b.descripcionevento.trim() || b.descripcionevento.trim().length > 255)) {
    errores.push('descripcionevento es obligatoria (texto, máximo 255 caracteres)');
  }
  return errores;
}

function errorInterno(res, err) {
  console.error(err);
  res.status(500).json({ error: 'Error interno del servidor' });
}

async function buscar(id) {
  const [rows] = await pool.query(`SELECT ${COLUMNAS} FROM eventos WHERE idevento = ?`, [id]);
  return rows[0];
}

// Lectura: cualquier usuario autenticado (los demás roles necesitan elegir el evento al registrar).
// Escritura (POST, PUT, PATCH): solo admin.

// GET /api/eventos?buscar=texto&tipoevento=...&estado=activos|inactivos|todos
router.get('/', async (req, res) => {
  try {
    const estado = req.query.estado || 'activos';
    const condiciones = [];
    const params = [];
    if (estado === 'activos') condiciones.push('activo = 1');
    else if (estado === 'inactivos') condiciones.push('activo = 0');
    else if (estado !== 'todos') {
      return res.status(400).json({ error: 'estado debe ser: activos, inactivos o todos' });
    }
    if (req.query.tipoevento) {
      condiciones.push('tipoevento = ?');
      params.push(req.query.tipoevento);
    }
    if (req.query.buscar) {
      condiciones.push('(tipoevento LIKE ? OR descripcionevento LIKE ?)');
      params.push(`%${req.query.buscar}%`, `%${req.query.buscar}%`);
    }
    const where = condiciones.length ? `WHERE ${condiciones.join(' AND ')}` : '';
    const [rows] = await pool.query(`SELECT ${COLUMNAS} FROM eventos ${where} ORDER BY idevento DESC`, params);
    res.json({ total: rows.length, data: rows });
  } catch (err) {
    errorInterno(res, err);
  }
});

// GET /api/eventos/:id
router.get('/:id', async (req, res) => {
  try {
    const fila = await buscar(req.params.id);
    if (!fila) return res.status(404).json({ error: 'Evento no encontrado' });
    res.json(fila);
  } catch (err) {
    errorInterno(res, err);
  }
});

// POST /api/eventos  { "tipoevento": "Lanzamiento", "descripcionevento": "Lanzamiento 2026" }
router.post('/', auth.soloAdmin, async (req, res) => {
  const errores = validar(req.body);
  if (errores.length) return res.status(400).json({ errores });
  try {
    const b = req.body;
    const [result] = await pool.query('INSERT INTO eventos (tipoevento, descripcionevento) VALUES (?, ?)', [
      b.tipoevento.trim(),
      b.descripcionevento.trim(),
    ]);
    res.status(201).json(await buscar(result.insertId));
  } catch (err) {
    errorInterno(res, err);
  }
});

// PUT /api/eventos/:id  (acepta uno o varios campos)
router.put('/:id', auth.soloAdmin, async (req, res) => {
  const b = req.body || {};
  const enviados = CAMPOS.filter((c) => b[c] !== undefined);
  if (!enviados.length) return res.status(400).json({ error: 'Envíe al menos un campo a actualizar' });
  const errores = validar(b, true);
  if (errores.length) return res.status(400).json({ errores });
  try {
    const [result] = await pool.query(
      `UPDATE eventos SET ${enviados.map((c) => `${c} = ?`).join(', ')} WHERE idevento = ?`,
      [...enviados.map((c) => b[c].trim()), req.params.id]
    );
    if (!result.affectedRows) return res.status(404).json({ error: 'Evento no encontrado' });
    res.json(await buscar(req.params.id));
  } catch (err) {
    errorInterno(res, err);
  }
});

// PATCH /api/eventos/:id/inactivar  y  /activar
// Un evento inactivo no se puede asignar a registros, ingresos, egresos ni inventario nuevos;
// lo ya asignado se conserva.
for (const [accion, valor] of [['inactivar', 0], ['activar', 1]]) {
  router.patch(`/:id/${accion}`, auth.soloAdmin, async (req, res) => {
    try {
      const [result] = await pool.query('UPDATE eventos SET activo = ? WHERE idevento = ?', [valor, req.params.id]);
      if (!result.affectedRows) return res.status(404).json({ error: 'Evento no encontrado' });
      res.json(await buscar(req.params.id));
    } catch (err) {
      errorInterno(res, err);
    }
  });
}

module.exports = router;

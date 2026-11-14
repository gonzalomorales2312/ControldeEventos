const express = require('express');
const pool = require('../config/db');

const router = express.Router();

const COLUMNAS = 'idrol, nombrerol, es_admin, activo';
const BANDERAS = ['es_admin'];

// es_admin: único tipo de rol con acceso a todas las subredes (los demás ven solo la subred del usuario)
//           y el único con acceso a ingresos, egresos, inventario, usuarios, roles y balance.
function validar(body, parcial = false) {
  const errores = [];
  const b = body || {};

  if (!parcial || b.nombrerol !== undefined) {
    if (typeof b.nombrerol !== 'string' || !b.nombrerol.trim() || b.nombrerol.trim().length > 50) {
      errores.push('nombrerol es obligatorio (texto, máximo 50 caracteres)');
    }
  }
  for (const p of BANDERAS) {
    if (b[p] !== undefined && ![true, false, 0, 1].includes(b[p])) {
      errores.push(`${p} debe ser true o false`);
    }
  }
  return errores;
}

const aBit = (v) => (v === true || v === 1 ? 1 : 0);

async function buscarRol(id) {
  const [rows] = await pool.query(`SELECT ${COLUMNAS} FROM roles WHERE idrol = ?`, [id]);
  return rows[0];
}

function errorInterno(res, err) {
  console.error(err);
  res.status(500).json({ error: 'Error interno del servidor' });
}

// GET /api/roles
router.get('/', async (req, res) => {
  try {
    const [rows] = await pool.query(`SELECT ${COLUMNAS} FROM roles ORDER BY idrol`);
    res.json(rows);
  } catch (err) {
    errorInterno(res, err);
  }
});

// GET /api/roles/:id
router.get('/:id', async (req, res) => {
  try {
    const rol = await buscarRol(req.params.id);
    if (!rol) return res.status(404).json({ error: 'Rol no encontrado' });
    res.json(rol);
  } catch (err) {
    errorInterno(res, err);
  }
});

// POST /api/roles
// { "nombrerol": "lider", "es_admin": false }
router.post('/', async (req, res) => {
  const errores = validar(req.body);
  if (errores.length) return res.status(400).json({ errores });

  try {
    const b = req.body;
    const [result] = await pool.query(
      'INSERT INTO roles (nombrerol, es_admin) VALUES (?, ?)',
      [b.nombrerol.trim(), aBit(b.es_admin)]
    );
    res.status(201).json(await buscarRol(result.insertId));
  } catch (err) {
    if (err.code === 'ER_DUP_ENTRY') return res.status(409).json({ error: 'Ese nombre de rol ya existe' });
    errorInterno(res, err);
  }
});

// PUT /api/roles/:id  (nombrerol y/o es_admin)
router.put('/:id', async (req, res) => {
  const b = req.body || {};
  if (b.nombrerol === undefined && BANDERAS.every((p) => b[p] === undefined)) {
    return res.status(400).json({ error: 'Envíe nombrerol y/o es_admin a actualizar' });
  }
  const errores = validar(b, true);
  if (errores.length) return res.status(400).json({ errores });

  // Evita que el admin se quite a sí mismo el acceso total
  if (String(req.params.id) === String(req.usuario.idrol) && b.es_admin !== undefined && !aBit(b.es_admin)) {
    return res.status(400).json({ error: 'No puede quitar es_admin al rol con el que está usando la API' });
  }

  try {
    const campos = [];
    const valores = [];
    if (b.nombrerol !== undefined) {
      campos.push('nombrerol = ?');
      valores.push(b.nombrerol.trim());
    }
    for (const p of BANDERAS) {
      if (b[p] !== undefined) {
        campos.push(`${p} = ?`);
        valores.push(aBit(b[p]));
      }
    }

    const [result] = await pool.query(`UPDATE roles SET ${campos.join(', ')} WHERE idrol = ?`, [
      ...valores,
      req.params.id,
    ]);
    if (!result.affectedRows) return res.status(404).json({ error: 'Rol no encontrado' });

    res.json(await buscarRol(req.params.id));
  } catch (err) {
    if (err.code === 'ER_DUP_ENTRY') return res.status(409).json({ error: 'Ese nombre de rol ya existe' });
    errorInterno(res, err);
  }
});

// PATCH /api/roles/:id/inactivar  y  /api/roles/:id/activar
// Un rol inactivo deja sin acceso a la API a todos los usuarios que lo tengan.
for (const [accion, valor] of [['inactivar', 0], ['activar', 1]]) {
  router.patch(`/:id/${accion}`, async (req, res) => {
    try {
      if (valor === 0 && String(req.params.id) === String(req.usuario.idrol)) {
        return res.status(400).json({ error: 'No puede inactivar el rol con el que está usando la API' });
      }
      const [result] = await pool.query('UPDATE roles SET activo = ? WHERE idrol = ?', [valor, req.params.id]);
      if (!result.affectedRows) return res.status(404).json({ error: 'Rol no encontrado' });
      res.json(await buscarRol(req.params.id));
    } catch (err) {
      errorInterno(res, err);
    }
  });
}

module.exports = router;

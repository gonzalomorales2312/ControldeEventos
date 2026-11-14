const express = require('express');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const pool = require('../config/db');
const auth = require('../middleware/auth');

const router = express.Router();

const COLUMNAS_PUBLICAS = 'id, usuario, idrol, subred, activo, creado_en';
const admin = [auth, auth.soloAdmin]; // gestión de usuarios: solo el rol admin

function validarUsuario(usuario) {
  return typeof usuario === 'string' && usuario.trim().length > 0 && usuario.trim().length <= 50;
}
function validarPassword(password) {
  return typeof password === 'string' && password.length >= 8;
}
// subred es opcional: undefined/null/vacío = sin subred
function validarSubred(subred) {
  return subred === undefined || subred === null || (typeof subred === 'string' && subred.trim().length <= 100);
}
const normalizarSubred = (s) => (typeof s === 'string' && s.trim() ? s.trim() : null);
const MSG_SUBRED = 'subred debe ser texto (máximo 100 caracteres)';

async function buscarUsuario(id) {
  const [rows] = await pool.query(`SELECT ${COLUMNAS_PUBLICAS} FROM usuarios WHERE id = ?`, [id]);
  return rows[0];
}

// Devuelve un mensaje de error si el rol no existe, está inactivo, o si el rol no es admin y el
// usuario quedaría sin subred (solo el admin puede no tener subred); null si es válido.
async function errorRol(idrol, subred) {
  const [rows] = await pool.query('SELECT activo, es_admin FROM roles WHERE idrol = ?', [idrol]);
  if (!rows.length) return 'El idrol no existe';
  if (!rows[0].activo) return 'El rol está inactivo';
  if (!rows[0].es_admin && !subred) return 'subred es obligatoria para usuarios cuyo rol no es admin';
  return null;
}

function errorInterno(res, err) {
  console.error(err);
  res.status(500).json({ error: 'Error interno del servidor' });
}

// POST /api/auth/login  { "usuario": "admin", "password": "..." }
router.post('/login', async (req, res) => {
  try {
    const { usuario, password } = req.body || {};
    if (!usuario || !password) {
      return res.status(400).json({ error: 'usuario y password son obligatorios' });
    }

    const [rows] = await pool.query(
      `SELECT u.id, u.usuario, u.password_hash, u.activo, r.activo AS rol_activo
         FROM usuarios u LEFT JOIN roles r ON r.idrol = u.idrol
        WHERE u.usuario = ?`,
      [usuario]
    );
    const user = rows[0];

    const ok = user && (await bcrypt.compare(password, user.password_hash));
    if (!ok) return res.status(401).json({ error: 'Credenciales inválidas' });
    if (!user.activo) return res.status(403).json({ error: 'Usuario inactivo' });
    if (user.rol_activo === 0) return res.status(403).json({ error: 'El rol del usuario está inactivo' });

    const token = jwt.sign(
      { id: user.id, usuario: user.usuario },
      process.env.JWT_SECRET,
      { expiresIn: process.env.JWT_EXPIRES_IN || '1h' }
    );

    res.json({ token, tipo: 'Bearer', expira_en: process.env.JWT_EXPIRES_IN || '1h' });
  } catch (err) {
    errorInterno(res, err);
  }
});

// GET /api/auth/usuarios  (lista de usuarios, sin contraseñas)
router.get('/usuarios', ...admin, async (req, res) => {
  try {
    const [rows] = await pool.query(`SELECT ${COLUMNAS_PUBLICAS} FROM usuarios ORDER BY id`);
    res.json(rows);
  } catch (err) {
    errorInterno(res, err);
  }
});

// GET /api/auth/usuarios/:id
router.get('/usuarios/:id', ...admin, async (req, res) => {
  try {
    const user = await buscarUsuario(req.params.id);
    if (!user) return res.status(404).json({ error: 'Usuario no encontrado' });
    res.json(user);
  } catch (err) {
    errorInterno(res, err);
  }
});

// POST /api/auth/usuarios  { "usuario": "nuevo", "password": "...", "idrol": 2, "subred": "Subred Norte" (opcional) }
router.post('/usuarios', ...admin, async (req, res) => {
  try {
    const { usuario, password, idrol } = req.body || {};
    if (!validarUsuario(usuario)) {
      return res.status(400).json({ error: 'usuario es obligatorio (texto, máximo 50 caracteres)' });
    }
    if (!validarPassword(password)) {
      return res.status(400).json({ error: 'password es obligatorio y debe tener al menos 8 caracteres' });
    }
    if (!Number.isInteger(idrol)) {
      return res.status(400).json({ error: 'idrol es obligatorio (número entero)' });
    }
    if (!validarSubred(req.body.subred)) return res.status(400).json({ error: MSG_SUBRED });
    const msgRol = await errorRol(idrol, normalizarSubred(req.body.subred));
    if (msgRol) return res.status(400).json({ error: msgRol });

    const hash = await bcrypt.hash(password, 10);
    const [result] = await pool.query(
      'INSERT INTO usuarios (usuario, password_hash, idrol, subred) VALUES (?, ?, ?, ?)',
      [usuario.trim(), hash, idrol, normalizarSubred(req.body.subred)]
    );
    res.status(201).json(await buscarUsuario(result.insertId));
  } catch (err) {
    if (err.code === 'ER_NO_REFERENCED_ROW_2') return res.status(400).json({ error: 'El idrol no existe' });
    if (err.code === 'ER_DUP_ENTRY') return res.status(409).json({ error: 'Ese usuario ya existe' });
    errorInterno(res, err);
  }
});

// PUT /api/auth/usuarios/:id  { "usuario", "password", "idrol", "subred" }  (uno o varios; subred null/"" la quita)
router.put('/usuarios/:id', ...admin, async (req, res) => {
  try {
    const { usuario, password, idrol, subred } = req.body || {};
    if (usuario === undefined && password === undefined && idrol === undefined && subred === undefined) {
      return res.status(400).json({ error: 'Envíe usuario, password, idrol y/o subred a actualizar' });
    }

    const campos = [];
    const valores = [];
    if (subred !== undefined) {
      if (!validarSubred(subred)) return res.status(400).json({ error: MSG_SUBRED });
      campos.push('subred = ?');
      valores.push(normalizarSubred(subred));
    }
    if (idrol !== undefined) {
      if (!Number.isInteger(idrol)) {
        return res.status(400).json({ error: 'idrol debe ser un número entero' });
      }
      campos.push('idrol = ?');
      valores.push(idrol);
    }
    // Si cambia el rol o la subred, el resultado final debe ser válido (rol activo; no admin => con subred)
    if (idrol !== undefined || subred !== undefined) {
      const actual = await buscarUsuario(req.params.id);
      if (!actual) return res.status(404).json({ error: 'Usuario no encontrado' });
      const idrolFinal = idrol !== undefined ? idrol : actual.idrol;
      const subredFinal = subred !== undefined ? normalizarSubred(subred) : actual.subred;
      if (idrolFinal === null) return res.status(400).json({ error: 'idrol es obligatorio (número entero)' });
      const msgRol = await errorRol(idrolFinal, subredFinal);
      if (msgRol) return res.status(400).json({ error: msgRol });
    }
    if (usuario !== undefined) {
      if (!validarUsuario(usuario)) {
        return res.status(400).json({ error: 'usuario debe ser texto (máximo 50 caracteres)' });
      }
      campos.push('usuario = ?');
      valores.push(usuario.trim());
    }
    if (password !== undefined) {
      if (!validarPassword(password)) {
        return res.status(400).json({ error: 'password debe tener al menos 8 caracteres' });
      }
      campos.push('password_hash = ?');
      valores.push(await bcrypt.hash(password, 10));
    }

    const [result] = await pool.query(
      `UPDATE usuarios SET ${campos.join(', ')} WHERE id = ?`,
      [...valores, req.params.id]
    );
    if (!result.affectedRows) return res.status(404).json({ error: 'Usuario no encontrado' });

    res.json(await buscarUsuario(req.params.id));
  } catch (err) {
    if (err.code === 'ER_NO_REFERENCED_ROW_2') return res.status(400).json({ error: 'El idrol no existe' });
    if (err.code === 'ER_DUP_ENTRY') return res.status(409).json({ error: 'Ese usuario ya existe' });
    errorInterno(res, err);
  }
});

// PATCH /api/auth/usuarios/:id/inactivar  y  /api/auth/usuarios/:id/activar
for (const [accion, valor] of [['inactivar', 0], ['activar', 1]]) {
  router.patch(`/usuarios/:id/${accion}`, ...admin, async (req, res) => {
    try {
      if (valor === 0 && String(req.usuario.id) === String(req.params.id)) {
        return res.status(400).json({ error: 'No puede inactivar su propio usuario' });
      }
      const [result] = await pool.query('UPDATE usuarios SET activo = ? WHERE id = ?', [valor, req.params.id]);
      if (!result.affectedRows) return res.status(404).json({ error: 'Usuario no encontrado' });

      res.json(await buscarUsuario(req.params.id));
    } catch (err) {
      errorInterno(res, err);
    }
  });
}

module.exports = router;

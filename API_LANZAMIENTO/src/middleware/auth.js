const jwt = require('jsonwebtoken');
const pool = require('../config/db');

// Valida Authorization: Bearer <token>, que el usuario siga activo y carga su rol.
// req.usuario = { id, usuario, idrol, rol, es_admin, subred }  (subred null = todas las subredes, solo admin)
function auth(req, res, next) {
  const header = req.header('authorization') || '';
  const [tipo, token] = header.split(' ');

  if (tipo !== 'Bearer' || !token) {
    return res.status(401).json({ error: 'Token ausente. Use: Authorization: Bearer <token>' });
  }

  let payload;
  try {
    payload = jwt.verify(token, process.env.JWT_SECRET);
  } catch (err) {
    const msg = err.name === 'TokenExpiredError' ? 'Token expirado' : 'Token inválido';
    return res.status(401).json({ error: msg });
  }

  pool
    .query(
      `SELECT u.activo, u.idrol, u.subred, r.nombrerol, r.es_admin, r.activo AS rol_activo
         FROM usuarios u LEFT JOIN roles r ON r.idrol = u.idrol
        WHERE u.id = ?`,
      [payload.id]
    )
    .then(([rows]) => {
      if (!rows.length || !rows[0].activo) {
        return res.status(403).json({ error: 'Usuario inactivo o inexistente' });
      }
      if (rows[0].idrol === null) {
        return res.status(403).json({ error: 'El usuario no tiene un rol asignado' });
      }
      if (!rows[0].rol_activo) {
        return res.status(403).json({ error: 'El rol del usuario está inactivo' });
      }
      const esAdmin = !!rows[0].es_admin;
      const subredUsuario = typeof rows[0].subred === 'string' && rows[0].subred.trim() ? rows[0].subred.trim() : null;
      // Solo el admin ve todas las subredes; los demás necesitan tener una subred asignada
      if (!esAdmin && subredUsuario === null) {
        return res.status(403).json({ error: 'El usuario no tiene una subred asignada' });
      }
      req.usuario = {
        id: payload.id,
        usuario: payload.usuario,
        idrol: rows[0].idrol,
        rol: rows[0].nombrerol,
        es_admin: esAdmin,
        subred: esAdmin ? null : subredUsuario, // null = todas las subredes (solo admin)
      };
      next();
    })
    .catch((err) => {
      console.error(err);
      res.status(500).json({ error: 'Error interno del servidor' });
    });
}

// Solo el rol admin (es_admin) puede usar usuarios, roles, ingresos, egresos, inventario y balance
function soloAdmin(req, res, next) {
  if (!req.usuario.es_admin) {
    return res.status(403).json({ error: 'Solo el rol admin puede hacer esto' });
  }
  next();
}

module.exports = auth;
module.exports.soloAdmin = soloAdmin;

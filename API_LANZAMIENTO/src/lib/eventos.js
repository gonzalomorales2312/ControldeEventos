const pool = require('../config/db');

// Valida un idevento recibido en el body. undefined/null = sin evento (permitido).
// Devuelve un mensaje de error o null si es válido. `db` puede ser el pool o una conexión.
async function errorEvento(v, db = pool) {
  if (v === undefined || v === null) return null;
  if (!Number.isInteger(v) || v <= 0) return 'idevento debe ser un número entero (o null para dejarlo sin evento)';
  const [rows] = await db.query('SELECT activo FROM eventos WHERE idevento = ?', [v]);
  if (!rows.length) return 'El idevento no existe';
  if (!rows[0].activo) return 'El evento está inactivo';
  return null;
}

// Condición para filtrar listados con ?idevento=N (o ?idevento=null para filas sin evento).
// Devuelve {} si no hay filtro, { error } si es inválido o { sql, params }.
function condicionEvento(query, col = 'idevento') {
  const v = query.idevento;
  if (v === undefined || v === '') return {};
  if (v === 'null') return { sql: `${col} IS NULL`, params: [] };
  const n = Number(v);
  if (!Number.isInteger(n) || n <= 0) return { error: 'idevento debe ser un número entero o "null"' };
  return { sql: `${col} = ?`, params: [n] };
}

module.exports = { errorEvento, condicionEvento };

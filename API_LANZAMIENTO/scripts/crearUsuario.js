// Uso: npm run crear-usuario -- admin MiPassword123 [idrol]   (idrol por defecto: 1 = admin)
require('dotenv').config();
const bcrypt = require('bcryptjs');
const pool = require('../src/config/db');

(async () => {
  const [usuario, password, idrolArg] = process.argv.slice(2);
  if (!usuario || !password) {
    console.error('Uso: npm run crear-usuario -- <usuario> <password> [idrol]');
    process.exit(1);
  }
  const idrol = idrolArg ? parseInt(idrolArg, 10) : 1;
  try {
    const hash = await bcrypt.hash(password, 10);
    await pool.query('INSERT INTO usuarios (usuario, password_hash, idrol) VALUES (?, ?, ?)', [usuario, hash, idrol]);
    console.log(`Usuario "${usuario}" creado correctamente con idrol ${idrol}`);
  } catch (err) {
    console.error(
      err.code === 'ER_DUP_ENTRY' ? 'Ese usuario ya existe'
        : err.code === 'ER_NO_REFERENCED_ROW_2' ? 'El idrol no existe'
        : err.message
    );
  } finally {
    await pool.end();
  }
})();

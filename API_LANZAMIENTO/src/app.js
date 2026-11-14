require('dotenv').config();
const express = require('express');
const helmet = require('helmet');
const cors = require('cors');

const apiKey = require('./middleware/apiKey');
const auth = require('./middleware/auth');
const authRoutes = require('./routes/auth');
const registrosRoutes = require('./routes/registros');
const rolesRoutes = require('./routes/roles');
const crearMovimientos = require('./routes/movimientos');
const pool = require('./config/db');
const inventarioRoutes = require('./routes/inventario');

for (const v of ['API_KEY', 'JWT_SECRET', 'DB_NAME']) {
  if (!process.env[v]) {
    console.error(`Falta la variable de entorno ${v} en el archivo .env`);
    process.exit(1);
  }
}

const app = express();
app.use(helmet());
app.use(cors());
app.use(express.json());

// 1) Toda la API exige x-api-key
app.use('/api', apiKey);

// 2) Login (genera el JWT)
app.use('/api/auth', authRoutes);

// 3) Rutas protegidas: x-api-key + Bearer token
app.use('/api/registros', auth, registrosRoutes);
app.use('/api/roles', auth, auth.soloAdmin, rolesRoutes);
// Ingresos y egresos son endpoints separados; ambos son solo para el rol admin.
app.use('/api/ingresos', auth, auth.soloAdmin, crearMovimientos('ingreso'));
app.use('/api/egresos', auth, auth.soloAdmin, crearMovimientos('egreso'));

// GET /api/balance: totales de ingresos, egresos y balance (activos). Solo admin.
app.get('/api/balance', auth, auth.soloAdmin, async (req, res) => {
  try {
    const [[r]] = await pool.query(
      `SELECT COALESCE(SUM(CASE WHEN tipoingresoegreso = 'ingreso' THEN vvalor END), 0) AS total_ingresos,
              COALESCE(SUM(CASE WHEN tipoingresoegreso = 'egreso' THEN vvalor END), 0) AS total_egresos
         FROM ingresosegresos WHERE activo = 1`
    );
    const ingresos = Number(r.total_ingresos);
    const egresos = Number(r.total_egresos);
    res.json({ total_ingresos: ingresos, total_egresos: egresos, balance: Math.round((ingresos - egresos) * 100) / 100 });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Error interno del servidor' });
  }
});

// El inventario no tiene subred: solo el rol admin
app.use('/api/inventario', auth, auth.soloAdmin, inventarioRoutes);

app.use((req, res) => res.status(404).json({ error: 'Ruta no encontrada' }));

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`API escuchando en http://localhost:${PORT}`));

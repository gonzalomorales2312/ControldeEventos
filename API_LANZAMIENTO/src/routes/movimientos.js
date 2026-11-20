const express = require('express');
const pool = require('../config/db');
const { errorEvento, condicionEvento } = require('../lib/eventos');

const TIPOS_PERSONA = ['lider', 'lanzado'];
const COLUMNA_PERSONA = { lider: 'nombre_lider', lanzado: 'nombre_lanzado' };

const redondear = (n) => Math.round(n * 100) / 100;
const esNumeroValido = (n) => typeof n === 'number' && Number.isFinite(n) && n >= 0;
const esEnteroPositivo = (n) => Number.isInteger(n) && n > 0;

function errorInterno(res, err) {
  console.error(err);
  res.status(500).json({ error: 'Error interno del servidor' });
}

// Texto opcional: vacío o ausente se guarda como NULL
function textoOpcional(v) {
  if (typeof v !== 'string') return v === undefined ? null : v;
  return v.trim() || null;
}

// ---------- Validaciones ----------
// parcial=true (PUT): solo se validan los campos enviados.
function validarBase(b, parcial) {
  const errores = [];
  const requerido = (c) => !parcial || b[c] !== undefined;

  if (requerido('descripcion') && (typeof b.descripcion !== 'string' || !b.descripcion.trim() || b.descripcion.trim().length > 255)) {
    errores.push('descripcion es obligatoria (texto, máximo 255 caracteres)');
  }
  if (requerido('vvalor') && !esNumeroValido(b.vvalor)) {
    errores.push('vvalor debe ser un número mayor o igual a 0');
  }
  if (b.observaciones !== undefined && b.observaciones !== null && typeof b.observaciones !== 'string') {
    errores.push('observaciones debe ser texto');
  }
  return errores;
}

function validarEgreso(b, parcial) {
  const errores = validarBase(b, parcial);
  if ((!parcial || b.cantidad !== undefined) && !esEnteroPositivo(b.cantidad)) {
    errores.push('cantidad es obligatoria (entero mayor a 0)');
  }
  if (!parcial && b.idinventario !== undefined && b.idinventario !== null && !Number.isInteger(b.idinventario)) {
    errores.push('idinventario debe ser un número entero');
  }
  return errores;
}

function validarIngreso(b, parcial) {
  const errores = validarBase(b, parcial);
  if (!parcial || b.tipopersona !== undefined) {
    if (!TIPOS_PERSONA.includes(b.tipopersona)) errores.push(`tipopersona debe ser uno de: ${TIPOS_PERSONA.join(', ')}`);
  }
  if (!parcial || b.nombrepersona !== undefined) {
    if (typeof b.nombrepersona !== 'string' || !b.nombrepersona.trim()) errores.push('nombrepersona es obligatorio (texto)');
  }
  return errores;
}

// ---------- Personas (combo de ingresos) ----------
// Solo el admin ve todas las personas; los demás usuarios ven las de su propia subred (igual que en /api/registros).
function filtroSubred(req) {
  return req.usuario.subred === null ? { sql: '', params: [] } : { sql: ' AND subred = ?', params: [req.usuario.subred] };
}

// Devuelve el nombre tal como está en registros, o null si no existe para ese tipo
async function resolverPersona(req, tipopersona, nombre) {
  const col = COLUMNA_PERSONA[tipopersona];
  const f = filtroSubred(req);
  const [rows] = await pool.query(
    `SELECT ${col} AS nombre FROM registros WHERE activo = 1 AND ${col} = ?${f.sql} LIMIT 1`,
    [nombre.trim(), ...f.params]
  );
  return rows.length ? rows[0].nombre : null;
}

// ---------- Inventario ----------
// Suma (o resta, si son negativos) cantidad y valor a un artículo. Debe llamarse dentro de una transacción.
// Devuelve null si todo salió bien, o { status, error } si no se puede aplicar el cambio.
async function ajustarInventario(conn, idinventario, deltaCantidad, deltaValor) {
  const [rows] = await conn.query('SELECT existencia, valor, activo FROM inventario WHERE id = ? FOR UPDATE', [idinventario]);
  if (!rows.length) return { status: 404, error: 'Artículo de inventario no encontrado' };
  const a = rows[0];
  if (!a.activo) return { status: 409, error: 'El artículo de inventario está inactivo' };

  const nuevaExistencia = a.existencia + deltaCantidad;
  if (nuevaExistencia < 0) {
    return {
      status: 409,
      error: 'La existencia del inventario es insuficiente para este cambio',
      existencia_disponible: a.existencia,
    };
  }
  const nuevoValor = nuevaExistencia === 0 ? 0 : Math.max(redondear(Number(a.valor) + deltaValor), 0);
  await conn.query('UPDATE inventario SET valor = ?, existencia = ? WHERE id = ?', [nuevoValor, nuevaExistencia, idinventario]);
  return null;
}

// Crea el router CRUD para un tipo ('ingreso' | 'egreso') sobre la tabla ingresosegresos.
// Cada router solo ve y modifica filas de su propio tipo.
function crearRouter(tipo) {
  const router = express.Router();
  const esEgreso = tipo === 'egreso';
  const noEncontrado = esEgreso ? 'Egreso no encontrado' : 'Ingreso no encontrado';
  const CAMPOS = esEgreso
    ? ['idevento', 'descripcion', 'vvalor', 'cantidad', 'observaciones']
    : ['idevento', 'descripcion', 'vvalor', 'observaciones', 'tipopersona', 'nombrepersona'];

  async function buscar(id) {
    const [rows] = await pool.query('SELECT * FROM ingresosegresos WHERE id = ? AND tipoingresoegreso = ?', [id, tipo]);
    return rows[0];
  }

  // Respuesta de egresos: incluye el artículo de inventario asociado (si lo tiene)
  async function conArticulo(fila) {
    if (!esEgreso || !fila || fila.idinventario === null) return fila;
    const [[articulo]] = await pool.query('SELECT * FROM inventario WHERE id = ?', [fila.idinventario]);
    return { egreso: fila, articulo };
  }

  // GET /api/{ingresos|egresos}?page=1&limit=20&buscar=texto&estado=activos|inactivos|todos
  // (ingresos acepta además tipopersona=lider|lanzado). Incluye total_valor del filtro aplicado.
  router.get('/', async (req, res) => {
    try {
      const page = Math.max(parseInt(req.query.page) || 1, 1);
      const limit = Math.min(Math.max(parseInt(req.query.limit) || 20, 1), 100);
      const offset = (page - 1) * limit;
      const estado = req.query.estado || 'activos';

      const condiciones = ['tipoingresoegreso = ?'];
      const params = [tipo];
      if (estado === 'activos') condiciones.push('activo = 1');
      else if (estado === 'inactivos') condiciones.push('activo = 0');
      else if (estado !== 'todos') {
        return res.status(400).json({ error: 'estado debe ser: activos, inactivos o todos' });
      }
      if (!esEgreso && req.query.tipopersona) {
        if (!TIPOS_PERSONA.includes(req.query.tipopersona)) {
          return res.status(400).json({ error: `tipopersona debe ser uno de: ${TIPOS_PERSONA.join(', ')}` });
        }
        condiciones.push('tipopersona = ?');
        params.push(req.query.tipopersona);
      }
      if (req.query.buscar) {
        condiciones.push('(descripcion LIKE ? OR observaciones LIKE ? OR nombrepersona LIKE ?)');
        params.push(`%${req.query.buscar}%`, `%${req.query.buscar}%`, `%${req.query.buscar}%`);
      }
      const ev = condicionEvento(req.query);
      if (ev.error) return res.status(400).json({ error: ev.error });
      if (ev.sql) {
        condiciones.push(ev.sql);
        params.push(...ev.params);
      }
      const where = `WHERE ${condiciones.join(' AND ')}`;

      const [[resumen]] = await pool.query(
        `SELECT COUNT(*) AS total, COALESCE(SUM(vvalor), 0) AS total_valor FROM ingresosegresos ${where}`,
        params
      );
      const [rows] = await pool.query(
        `SELECT * FROM ingresosegresos ${where} ORDER BY id DESC LIMIT ? OFFSET ?`,
        [...params, limit, offset]
      );

      res.json({ page, limit, total: resumen.total, total_valor: redondear(Number(resumen.total_valor)), data: rows });
    } catch (err) {
      errorInterno(res, err);
    }
  });

  // GET /api/ingresos/personas?tipo=lider|lanzado
  // Valores del combo: nombres distintos de la tabla registros (solo registros activos)
  if (!esEgreso) {
    router.get('/personas', async (req, res) => {
      try {
        const t = req.query.tipo;
        if (!TIPOS_PERSONA.includes(t)) {
          return res.status(400).json({ error: `tipo debe ser uno de: ${TIPOS_PERSONA.join(', ')}` });
        }
        const col = COLUMNA_PERSONA[t];
        const f = filtroSubred(req);
        const [rows] = await pool.query(
          `SELECT DISTINCT ${col} AS nombre FROM registros WHERE activo = 1 AND ${col} <> ''${f.sql} ORDER BY ${col}`,
          f.params
        );
        res.json({ tipo: t, data: rows.map((r) => r.nombre) });
      } catch (err) {
        errorInterno(res, err);
      }
    });
  }

  // GET /api/{ingresos|egresos}/:id
  router.get('/:id', async (req, res) => {
    try {
      const fila = await buscar(req.params.id);
      if (!fila) return res.status(404).json({ error: noEncontrado });
      res.json(fila);
    } catch (err) {
      errorInterno(res, err);
    }
  });

  if (!esEgreso) {
    // POST /api/ingresos  { descripcion, vvalor, tipopersona: "lider"|"lanzado", nombrepersona, observaciones? }
    router.post('/', async (req, res) => {
      const b = req.body || {};
      const errores = validarIngreso(b, false);
      if (errores.length) return res.status(400).json({ errores });

      try {
        const eEvento = await errorEvento(b.idevento);
        if (eEvento) return res.status(400).json({ error: eEvento });

        const nombre = await resolverPersona(req, b.tipopersona, b.nombrepersona);
        if (!nombre) {
          return res.status(400).json({ error: `"${b.nombrepersona.trim()}" no existe en registros como ${b.tipopersona}` });
        }
        const [result] = await pool.query(
          'INSERT INTO ingresosegresos (tipoingresoegreso, idevento, descripcion, vvalor, observaciones, tipopersona, nombrepersona) VALUES (?, ?, ?, ?, ?, ?, ?)',
          [tipo, b.idevento ?? null, b.descripcion.trim(), b.vvalor, textoOpcional(b.observaciones), b.tipopersona, nombre]
        );
        res.status(201).json(await buscar(result.insertId));
      } catch (err) {
        errorInterno(res, err);
      }
    });

    // PUT /api/ingresos/:id  (acepta uno o varios campos)
    router.put('/:id', async (req, res) => {
      const b = { ...(req.body || {}) };
      if (!CAMPOS.some((c) => b[c] !== undefined)) {
        return res.status(400).json({ error: 'Envíe al menos un campo a actualizar' });
      }
      const errores = validarIngreso(b, true);
      if (errores.length) return res.status(400).json({ errores });

      try {
        const actual = await buscar(req.params.id);
        if (!actual) return res.status(404).json({ error: noEncontrado });

        const eEvento = await errorEvento(b.idevento);
        if (eEvento) return res.status(400).json({ error: eEvento });

        if (b.tipopersona !== undefined || b.nombrepersona !== undefined) {
          const tp = b.tipopersona ?? actual.tipopersona;
          const np = b.nombrepersona ?? actual.nombrepersona;
          if (!tp || !np) {
            return res.status(400).json({ error: 'Envíe tipopersona y nombrepersona juntos' });
          }
          const nombre = await resolverPersona(req, tp, np);
          if (!nombre) return res.status(400).json({ error: `"${np}" no existe en registros como ${tp}` });
          b.tipopersona = tp;
          b.nombrepersona = nombre;
        }

        const enviados = CAMPOS.filter((c) => b[c] !== undefined);
        await pool.query(
          `UPDATE ingresosegresos SET ${enviados.map((c) => `${c} = ?`).join(', ')} WHERE id = ? AND tipoingresoegreso = ?`,
          [...enviados.map((c) => (c === 'observaciones' ? textoOpcional(b[c]) : typeof b[c] === 'string' ? b[c].trim() : b[c])), req.params.id, tipo]
        );
        res.json(await buscar(req.params.id));
      } catch (err) {
        errorInterno(res, err);
      }
    });

    for (const [accion, valor] of [['inactivar', 0], ['activar', 1]]) {
      router.patch(`/:id/${accion}`, async (req, res) => {
        try {
          const [result] = await pool.query(
            'UPDATE ingresosegresos SET activo = ? WHERE id = ? AND tipoingresoegreso = ?',
            [valor, req.params.id, tipo]
          );
          if (!result.affectedRows) return res.status(404).json({ error: noEncontrado });
          res.json(await buscar(req.params.id));
        } catch (err) {
          errorInterno(res, err);
        }
      });
    }

    return router;
  }

  // ================= Solo egresos: todo egreso entra al inventario =================

  // POST /api/egresos  { descripcion, vvalor (total pagado), cantidad, observaciones?, idinventario? }
  //  - sin idinventario: crea un artículo nuevo en el inventario (valor = vvalor, existencia = cantidad)
  //  - con idinventario: suma cantidad y vvalor a ese artículo existente
  // preciounitario del egreso = vvalor / cantidad (calculado por la base de datos)
  router.post('/', async (req, res) => {
    const b = req.body || {};
    const errores = validarEgreso(b, false);
    if (errores.length) return res.status(400).json({ errores });

    const descripcion = b.descripcion.trim();
    const conn = await pool.getConnection();
    try {
      await conn.beginTransaction();

      const eEvento = await errorEvento(b.idevento, conn);
      if (eEvento) {
        await conn.rollback();
        return res.status(400).json({ error: eEvento });
      }
      let idevento = b.idevento ?? null;

      let idinventario = b.idinventario ?? null;
      if (idinventario === null) {
        // El artículo nuevo pertenece al mismo evento que el egreso
        const [r] = await conn.query(
          'INSERT INTO inventario (idevento, descripcion, valor, existencia) VALUES (?, ?, ?, ?)',
          [idevento, descripcion, b.vvalor, b.cantidad]
        );
        idinventario = r.insertId;
      } else {
        // Sin idevento en el body, el egreso hereda el evento del artículo existente
        if (idevento === null && b.idevento === undefined) {
          const [[art]] = await conn.query('SELECT idevento FROM inventario WHERE id = ?', [idinventario]);
          if (art) idevento = art.idevento;
        }
        const fallo = await ajustarInventario(conn, idinventario, b.cantidad, b.vvalor);
        if (fallo) {
          await conn.rollback();
          const { status, ...cuerpo } = fallo;
          return res.status(status).json(cuerpo);
        }
      }

      const [e] = await conn.query(
        `INSERT INTO ingresosegresos (tipoingresoegreso, idevento, descripcion, vvalor, observaciones, idinventario, cantidad)
         VALUES ('egreso', ?, ?, ?, ?, ?, ?)`,
        [idevento, descripcion, b.vvalor, textoOpcional(b.observaciones), idinventario, b.cantidad]
      );
      await conn.commit();

      res.status(201).json(await conArticulo(await buscar(e.insertId)));
    } catch (err) {
      try { await conn.rollback(); } catch (_) { /* conexión ya cerrada */ }
      errorInterno(res, err);
    } finally {
      conn.release();
    }
  });

  // PUT /api/egresos/:id  (uno o varios campos). Si cambian vvalor o cantidad, el inventario se ajusta
  // con la diferencia. Los egresos antiguos sin artículo asociado solo actualizan el egreso.
  router.put('/:id', async (req, res) => {
    const b = req.body || {};
    const enviados = CAMPOS.filter((c) => b[c] !== undefined);
    if (!enviados.length) return res.status(400).json({ error: 'Envíe al menos un campo a actualizar' });
    const errores = validarEgreso(b, true);
    if (errores.length) return res.status(400).json({ errores });

    const conn = await pool.getConnection();
    try {
      await conn.beginTransaction();
      const [rows] = await conn.query(
        'SELECT * FROM ingresosegresos WHERE id = ? AND tipoingresoegreso = ? FOR UPDATE',
        [req.params.id, tipo]
      );
      if (!rows.length) {
        await conn.rollback();
        return res.status(404).json({ error: noEncontrado });
      }
      const actual = rows[0];

      const eEvento = await errorEvento(b.idevento, conn);
      if (eEvento) {
        await conn.rollback();
        return res.status(400).json({ error: eEvento });
      }

      // Solo se toca el inventario si el egreso está activo y enlazado a un artículo
      if (actual.idinventario !== null && actual.activo) {
        const dCantidad = (b.cantidad ?? actual.cantidad) - actual.cantidad;
        const dValor = redondear((b.vvalor ?? Number(actual.vvalor)) - Number(actual.vvalor));
        if (dCantidad !== 0 || dValor !== 0) {
          const fallo = await ajustarInventario(conn, actual.idinventario, dCantidad, dValor);
          if (fallo) {
            await conn.rollback();
            const { status, ...cuerpo } = fallo;
            return res.status(status).json(cuerpo);
          }
        }
      }

      await conn.query(
        `UPDATE ingresosegresos SET ${enviados.map((c) => `${c} = ?`).join(', ')} WHERE id = ?`,
        [...enviados.map((c) => (c === 'observaciones' ? textoOpcional(b[c]) : typeof b[c] === 'string' ? b[c].trim() : b[c])), req.params.id]
      );
      await conn.commit();

      res.json(await conArticulo(await buscar(req.params.id)));
    } catch (err) {
      try { await conn.rollback(); } catch (_) { /* conexión ya cerrada */ }
      errorInterno(res, err);
    } finally {
      conn.release();
    }
  });

  // PATCH /api/egresos/:id/inactivar  y  /activar
  // Inactivar un egreso con artículo asociado quita del inventario lo que había sumado; activar lo vuelve a sumar.
  for (const [accion, valor] of [['inactivar', 0], ['activar', 1]]) {
    router.patch(`/:id/${accion}`, async (req, res) => {
      const conn = await pool.getConnection();
      try {
        await conn.beginTransaction();
        const [rows] = await conn.query(
          'SELECT * FROM ingresosegresos WHERE id = ? AND tipoingresoegreso = ? FOR UPDATE',
          [req.params.id, tipo]
        );
        if (!rows.length) {
          await conn.rollback();
          return res.status(404).json({ error: noEncontrado });
        }
        const actual = rows[0];

        if (actual.activo !== valor && actual.idinventario !== null) {
          const signo = valor === 1 ? 1 : -1;
          const fallo = await ajustarInventario(conn, actual.idinventario, signo * actual.cantidad, signo * Number(actual.vvalor));
          if (fallo) {
            await conn.rollback();
            const { status, ...cuerpo } = fallo;
            return res.status(status).json(cuerpo);
          }
        }

        await conn.query('UPDATE ingresosegresos SET activo = ? WHERE id = ?', [valor, req.params.id]);
        await conn.commit();
        res.json(await conArticulo(await buscar(req.params.id)));
      } catch (err) {
        try { await conn.rollback(); } catch (_) { /* conexión ya cerrada */ }
        errorInterno(res, err);
      } finally {
        conn.release();
      }
    });
  }

  return router;
}

module.exports = crearRouter;

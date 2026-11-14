# API Boletas (Node.js + MySQL + API Key + JWT)

## Instalación
1. Instalar dependencias: `npm install`
2. Crear la base de datos: ejecutar `schema.sql` en MySQL
   (`mysql -u root -p < schema.sql`)
3. Copiar `.env.example` a `.env` y ajustar credenciales, `API_KEY` y `JWT_SECRET`
4. Crear un usuario: `npm run crear-usuario -- admin MiPassword123`
5. Iniciar: `npm start` (o `npm run dev`)

## Seguridad
- Todas las rutas requieren el header `x-api-key: <API_KEY>`
- `/api/registros/*` además requiere `Authorization: Bearer <token>`

## Endpoints
| Método | Ruta | Descripción |
|-------|------|-------------|
| POST | /api/auth/login | Devuelve el JWT |
| GET | /api/registros | Lista (page, limit, buscar) |
| GET | /api/registros/:id | Detalle |
| POST | /api/registros | Crear |
| PUT | /api/registros/:id | Actualizar (campos parciales) |

## Ejemplos (curl)
```bash
# Login
curl -X POST http://localhost:3000/api/auth/login \
  -H "x-api-key: TU_API_KEY" -H "Content-Type: application/json" \
  -d '{"usuario":"admin","password":"MiPassword123"}'

# Crear
curl -X POST http://localhost:3000/api/registros \
  -H "x-api-key: TU_API_KEY" -H "Authorization: Bearer TU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"nombre_lanzado":"Juan Pérez","nombre_lider":"María López","subred":"Subred Norte","numero_boleta":"B-0001","monto_boleta":1500.50}'

# Listar
curl http://localhost:3000/api/registros -H "x-api-key: TU_API_KEY" -H "Authorization: Bearer TU_TOKEN"

# Actualizar
curl -X PUT http://localhost:3000/api/registros/1 \
  -H "x-api-key: TU_API_KEY" -H "Authorization: Bearer TU_TOKEN" \
  -H "Content-Type: application/json" -d '{"monto_boleta":1800}'
```

Estados civiles válidos: soltero, casado, divorciado, viudo, union_libre

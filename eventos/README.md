# Cliente PHP para API Boletas

Aplicación PHP (sin frameworks) que consume la API Node.js: pide usuario y contraseña,
obtiene el JWT y lo usa en las llamadas GET, POST y PUT.

## Requisitos
- PHP 8.0+ con extensión **curl** habilitada
- La API Node.js corriendo (por defecto en http://localhost:3000)

## Configuración
Edite `config.php`:
- `api_url`: URL base de la API (ej. `http://localhost:3000/api`)
- `api_key`: debe ser igual a `API_KEY` del `.env` de la API

## Ejecutar
```bash
php -S localhost:8000
```
Abra http://localhost:8000 e inicie sesión con el usuario creado en la API
(`npm run crear-usuario -- admin MiPassword123`).

## Flujo
- `login.php` → POST /auth/login, guarda el token en la sesión PHP
- `index.php` → GET /registros (búsqueda y paginación)
- `form.php`  → GET /registros/:id, POST /registros (crear), PUT /registros/:id (editar)
- Si el token expira (401), se cierra la sesión y se vuelve al login

## Seguridad
- La API key y el JWT quedan en el servidor PHP; el navegador solo recibe la cookie de sesión
- Protección CSRF en formularios y escape de salida con htmlspecialchars

<?php
// Configuración del cliente. La API key vive solo en el servidor PHP (nunca se envía al navegador).
return [
    'api_url'  => 'http://localhost:3000/api',   // URL base de la API Node.js
    'api_key'  => '19763da0-dfb3-11f1-9641-683e26442edc',   // Debe coincidir con API_KEY del .env de la API
    'per_page' => 10,
];

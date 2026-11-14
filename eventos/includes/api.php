<?php
// Cliente HTTP (cURL) para consumir la API Node.js

function config(): array {
    static $cfg = null;
    if ($cfg === null) $cfg = require __DIR__ . '/../config.php';
    return $cfg;
}

/**
 * Llama a la API. Devuelve ['status' => int, 'data' => array|null].
 * Si hay token en sesión lo envía como Bearer.
 */
function api_request(string $method, string $path, ?array $body = null, ?string $token = null): array {
    $cfg = config();
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'x-api-key: ' . $cfg['api_key'],
    ];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;

    $ch = curl_init(rtrim($cfg['api_url'], '/') . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['status' => 0, 'data' => ['error' => 'No se pudo conectar con la API: ' . $err]];
    }
    return ['status' => $status, 'data' => json_decode($raw, true)];
}

// Llamada autenticada: si el token expiró (401) cierra sesión y redirige al login
function api_auth(string $method, string $path, ?array $body = null): array {
    $res = api_request($method, $path, $body, $_SESSION['token'] ?? null);
    if ($res['status'] === 401) {
        $_SESSION = [];
        session_destroy();
        session_start();
        $_SESSION['flash'] = 'Su sesión expiró. Inicie sesión nuevamente.';
        header('Location: login.php');
        exit;
    }
    return $res;
}

// Convierte la respuesta de error de la API en lista de mensajes
function api_errores(array $res): array {
    $d = $res['data'] ?? [];
    if (!empty($d['errores'])) return $d['errores'];
    if (!empty($d['error'])) return [$d['error']];
    return ['Error inesperado (HTTP ' . $res['status'] . ')'];
}

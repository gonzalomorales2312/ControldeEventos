<?php
// Descarga la imagen de la boleta de un registro (guardada en base64 en la API)
require __DIR__ . '/includes/auth.php';
requiere_login();

$id = (int) ($_GET['id'] ?? 0);
$res = $id > 0 ? api_auth('GET', "/registros/$id") : ['status' => 404, 'data' => null];

$reg = $res['status'] === 200 ? $res['data'] : null;
// Un usuario no admin solo puede descargar boletas de su subred
if ($reg && subred_usuario() !== null && ($reg['subred'] ?? null) !== subred_usuario()) $reg = null;

$b64 = $reg['imagen_boleta'] ?? '';
$mime = null;
if (preg_match('#^data:([\w/+.-]+);base64,#', $b64, $m)) {
    $mime = $m[1];
    $b64 = substr($b64, strlen($m[0]));
}
$bin = $b64 !== '' ? base64_decode($b64, true) : false;

if ($bin === false || $bin === '') {
    http_response_code(404);
    exit('No hay imagen de boleta para este registro');
}

$mime = $mime ?: (new finfo(FILEINFO_MIME_TYPE))->buffer($bin);
$ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? 'bin';
$nombre = 'boleta_' . (preg_replace('/[^\w-]+/', '_', (string) ($reg['numero_boleta'] ?? $id)) ?: $id) . '.' . $ext;

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($bin));
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('X-Content-Type-Options: nosniff');
echo $bin;

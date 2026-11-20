<?php
// secure: la cookie de sesión solo viaja por HTTPS cuando el sitio se sirve con HTTPS (en local con http sigue funcionando)
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
require_once __DIR__ . '/api.php';

function h($v): string {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function requiere_login(): void {
    if (empty($_SESSION['token'])) {
        header('Location: login.php');
        exit;
    }
    evento_actual(); // fija el evento de trabajo (?ev=) antes de procesar la página
}

// ¿Es administrador? Admin = rol sin subred (acceso a todas las subredes)
function es_admin(): bool {
    return !empty($_SESSION['es_admin']);
}

// Subred del usuario (null si es admin)
function subred_usuario(): ?string {
    return es_admin() ? null : ($_SESSION['subred'] ?? null);
}

// Solo admin: ingresos, egresos, inventario y administración
function requiere_admin(): void {
    requiere_login();
    if (!es_admin()) {
        $_SESSION['flash_err'] = 'No tiene acceso a esa sección';
        header('Location: index.php');
        exit;
    }
}

// Obtiene idrol del login (cuerpo de respuesta o payload del JWT) y carga rol/subred en sesión
function cargar_rol_sesion(array $loginData): void {
    $idrol = $loginData['idrol'] ?? ($loginData['usuario']['idrol'] ?? null);
    if ($idrol === null && !empty($loginData['token'])) {
        $partes = explode('.', $loginData['token']);
        if (count($partes) === 3) {
            $p = json_decode(base64_decode(strtr($partes[1], '-_', '+/')), true);
            $idrol = $p['idrol'] ?? null;
        }
    }
    $_SESSION['es_admin'] = false;
    $_SESSION['subred'] = null;
    // La subred es un dato del usuario; buscarlo en la lista de usuarios (también da el idrol si falta)
    $subred = $loginData['subred'] ?? ($loginData['usuario']['subred'] ?? null);
    $ur = api_request('GET', '/auth/usuarios', null, $loginData['token']);
    if ($ur['status'] === 200 && is_array($ur['data'])) {
        foreach ($ur['data'] as $u) {
            if (isset($u['usuario']) && strcasecmp($u['usuario'], $_SESSION['usuario'] ?? '') === 0) {
                if ($idrol === null) $idrol = $u['idrol'] ?? null;
                $subred = $u['subred'] ?? $subred;
                break;
            }
        }
    }
    if ($idrol === null) return;
    $rr = api_request('GET', '/roles', null, $loginData['token']);
    if ($rr['status'] !== 200) return;
    foreach ($rr['data'] as $r) {
        if ((string) $r['idrol'] === (string) $idrol) {
            // Admin = rol llamado "admin" (o "administrador")
            // La API marca el rol con es_admin; si no viene, se usa el nombre "admin"/"administrador"
            $_SESSION['es_admin'] = !empty($r['es_admin'])
                || in_array(mb_strtolower(trim($r['nombrerol'])), ['admin', 'administrador'], true);
            $_SESSION['subred'] = $_SESSION['es_admin'] || $subred === '' ? null : $subred;
            return;
        }
    }
}

// ---- Eventos ----
// Todos los eventos (activos e inactivos), cacheados por petición
function eventos_todos(): array {
    static $lista = null;
    if ($lista === null) {
        $r = api_auth('GET', '/eventos?estado=todos');
        $lista = $r['status'] === 200 ? ($r['data']['data'] ?? []) : [];
    }
    return $lista;
}

function evento_etiqueta(array $e): string {
    return $e['tipoevento'] . ' — ' . $e['descripcionevento'];
}

// Eventos activos de tipo Lanzamiento (los que aparecen en el menú)
function eventos_lanzamiento(): array {
    return array_values(array_filter(eventos_todos(), fn($e) => $e['activo'] && mb_strtolower(trim($e['tipoevento'])) === 'lanzamiento'));
}

// Evento en el que se está trabajando. Se fija con ?ev=N (enlaces del menú) y se recuerda en la sesión;
// sin elección es null (el menú pide elegirlo en una ventana). Nunca se muestra: las pantallas lo usan para filtrar y para asignar idevento.
function evento_actual(): ?int {
    static $calculado = false, $valor = null;
    if ($calculado) return $valor;
    $calculado = true;
    $validos = array_map(fn($e) => (int) $e['idevento'], eventos_lanzamiento());
    $ev = isset($_GET['ev']) && ctype_digit((string) $_GET['ev']) ? (int) $_GET['ev'] : null;
    if ($ev !== null && in_array($ev, $validos, true)) $_SESSION['evento_actual'] = $ev;
    $guardado = $_SESSION['evento_actual'] ?? null;
    $valor = in_array($guardado, $validos, true) ? $guardado : null;
    $_SESSION['evento_actual'] = $valor;
    return $valor;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

// Procesa un POST de activar/inactivar (PATCH /{base}/{id}/{accion}) y vuelve a la página actual.
function procesar_estado(string $base): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['accion'])) return;
    csrf_validar();
    $id = (int) ($_POST['id'] ?? 0);
    $accion = $_POST['accion'];
    if ($id > 0 && in_array($accion, ['activar', 'inactivar'], true)) {
        $res = api_auth('PATCH', "$base/$id/$accion");
        if ($res['status'] === 200) $_SESSION['flash_ok'] = $accion === 'activar' ? 'Registro activado' : 'Registro inactivado';
        else $_SESSION['flash_err'] = implode(' ', api_errores($res));
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Lee y limpia los mensajes flash [ok, error]
function flash(): array {
    $r = [$_SESSION['flash_ok'] ?? null, $_SESSION['flash_err'] ?? null];
    unset($_SESSION['flash_ok'], $_SESSION['flash_err']);
    return $r;
}

// Botón activar/inactivar para una fila
function boton_estado(int $id, bool $activo): string {
    $accion = $activo ? 'inactivar' : 'activar';
    return '<form method="post" class="inline"><input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'
         . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="accion" value="' . $accion . '">'
         . '<button type="submit" class="link">' . ($activo ? 'Inactivar' : 'Activar') . '</button></form>';
}

// Paginador HTML conservando los filtros de $query
function paginador(int $page, int $total, int $per_page, array $query = []): string {
    $paginas = max((int) ceil($total / $per_page), 1);
    $link = fn(int $p, string $txt) => '<a href="?' . h(http_build_query(array_merge($query, ['page' => $p]))) . '">' . $txt . '</a>';
    $html = '<div class="pager">';
    if ($page > 1) $html .= $link($page - 1, '&laquo; Anterior');
    $html .= "<span>Página $page de $paginas ($total registros)</span>";
    if ($page < $paginas) $html .= $link($page + 1, 'Siguiente &raquo;');
    return $html . '</div>';
}

function csrf_validar(): void {
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    if (!$ok) {
        http_response_code(403);
        exit('Token CSRF inválido');
    }
}

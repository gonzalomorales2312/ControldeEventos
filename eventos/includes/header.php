<?php
// Variables con prefijo propio para no pisar las de la página que incluye este header
$logueado = !empty($_SESSION['token']);
$menu_actual = basename($_SERVER['SCRIPT_NAME']);

// Iconos (trazos SVG, viewBox 24x24)
$menu_iconos = [
    'grupo'      => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'registros'  => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
    'inventario' => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
    'ingresos'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v10M8 13l4 4 4-4"/>',
    'egresos'    => '<circle cx="12" cy="12" r="9"/><path d="M12 17V7M8 11l4-4 4 4"/>',
    'usuarios'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
    'roles'      => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
    'admin'      => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>',
    'encuentros' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
];
function menu_svg(array $iconos, string $nombre): string {
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($iconos[$nombre] ?? '') . '</svg>';
}

// Secciones del menú (mismo nivel). Cada opción: archivo => [texto, archivos que la marcan como activa, icono].
// Si la sección tiene 'href', su título es un enlace directo (sin opciones internas).
$menu_secciones = [
    [
        'titulo' => 'Control de Lanzamientos',
        'icono'  => 'grupo',
        'href'   => null,
        'items'  => [],
        'arbol'  => true, // árbol: un subgrupo por evento de lanzamiento con sus opciones
    ],
    [
        'titulo' => 'Control de Encuentros',
        'icono'  => 'encuentros',
        'href'   => 'encuentros.php',
        'items'  => [],
    ],
    [
        'titulo' => 'Administración',
        'icono'  => 'admin',
        'href'   => null,
        'items'  => [
            'eventos.php'  => ['Eventos',  ['eventos.php', 'evento_form.php'], 'encuentros'],
            'usuarios.php' => ['Usuarios', ['usuarios.php', 'usuario_form.php'], 'usuarios'],
            'roles.php'    => ['Roles',    ['roles.php', 'rol_form.php'], 'roles'],
        ],
    ],
];
// Opciones de cada evento de lanzamiento. Solo admin ve inventario, ingresos, egresos, reporte y administración.
$menu_opc_evento = [
    'index.php' => ['Registro', ['index.php', 'form.php'], 'registros', false],
    'inventario.php' => ['Inventario', ['inventario.php', 'inventario_form.php', 'inventario_salida.php'], 'inventario', true],
    'ingresos.php' => ['Ingresos', ['ingresos.php', 'ingreso_form.php'], 'ingresos', true],
    'egresos.php' => ['Egresos', ['egresos.php', 'egreso_form.php'], 'egresos', true],
    'reporte.php' => ['Reporte', ['reporte.php'], 'registros', true],
];
if (!es_admin()) {
    $menu_opc_evento = array_filter($menu_opc_evento, fn($o) => !$o[3]);
    unset($menu_secciones[2]);
    $menu_secciones = array_values($menu_secciones);
}
// En el login no hay token: no consultar la API
$menu_eventos = $logueado ? eventos_lanzamiento() : [];
$menu_ev_actual = $logueado ? evento_actual() : null;
$menu_ev_etiqueta = '';
foreach ($menu_eventos as $m_ev) if ((int) $m_ev['idevento'] === $menu_ev_actual) $menu_ev_etiqueta = $m_ev['descripcionevento'];
// Página de un módulo de lanzamientos (su lista principal es el primer archivo de la opción)
$menu_pagina_lanz = null;
foreach ($menu_opc_evento as $m_o) if (in_array($menu_actual, $m_o[1], true)) $menu_pagina_lanz = $m_o[1][0];
// Sección actual (para el título de la barra superior en móvil)
$menu_titulo_actual = 'Control de Lanzamientos';
foreach ($menu_secciones as $m_sec) {
    if (($m_sec['href'] ?? null) === $menu_actual) $menu_titulo_actual = $m_sec['titulo'];
    foreach ($m_sec['items'] as [$m_t, $m_arch]) {
        if (in_array($menu_actual, $m_arch, true)) $menu_titulo_actual = $m_sec['titulo'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($titulo ?? 'Control de Lanzamientos') ?></title>
  <link rel="stylesheet" href="assets/style.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/style.css') ?>">
</head>
<body class="<?= $logueado ? 'con-menu' : '' ?>">
<?php if ($logueado): ?>
<script>try { if (localStorage.getItem('menuColapsado') === '1') document.body.classList.add('menu-colapsado'); } catch (e) {}</script>
<header class="topbar">
  <button type="button" class="menu-toggle" aria-label="Abrir menú" onclick="document.body.classList.toggle('menu-abierto')">&#9776;</button>
  <strong><?= h($menu_titulo_actual) ?></strong>
</header>
<aside class="sidebar" aria-label="Navegación principal">
  <div class="sidebar-cabecera">
    <button type="button" class="sidebar-colapsar" aria-label="Colapsar menú" title="Colapsar / expandir"
      onclick="var c=document.body.classList.toggle('menu-colapsado');try{localStorage.setItem('menuColapsado',c?'1':'0')}catch(e){}">&#8801;</button>
  </div>
  <nav>
    <?php foreach ($menu_secciones as $m_sec): ?>
      <?php if ($m_sec['href']): ?>
        <a href="<?= h($m_sec['href']) ?>" class="nav-item nav-raiz <?= $menu_actual === $m_sec['href'] ? 'activo' : '' ?>" title="<?= h($m_sec['titulo']) ?>">
          <?= menu_svg($menu_iconos, $m_sec['icono']) ?><span class="txt"><?= h($m_sec['titulo']) ?></span>
        </a>
      <?php elseif (!empty($m_sec['arbol'])):
        $m_abierto = false;
        foreach ($menu_opc_evento as $m_arch_h => $m_o) if (in_array($menu_actual, $m_o[1], true)) $m_abierto = true;
      ?>
        <div class="grupo <?= $m_abierto ? '' : 'cerrado' ?>">
          <button type="button" class="nav-item nav-grupo" aria-expanded="<?= $m_abierto ? 'true' : 'false' ?>" title="Elegir evento"
            onclick="this.parentNode.classList.remove('cerrado');this.setAttribute('aria-expanded','true');abrirModalEvento()">
            <?= menu_svg($menu_iconos, $m_sec['icono']) ?><span class="txt"><?= h($m_sec['titulo']) ?></span><span class="chev txt">&#9662;</span>
          </button>
          <div class="grupo-items">
            <?php if ($menu_ev_etiqueta !== ''): ?><span class="nav-vacio txt">Evento: <?= h($menu_ev_etiqueta) ?></span><?php endif; ?>
            <?php foreach ($menu_opc_evento as $m_href => [$m_texto, $m_archivos, $m_icono]): ?>
              <a href="<?= h($m_href) ?>" class="nav-item nav-sub <?= in_array($menu_actual, $m_archivos, true) ? 'activo' : '' ?>" title="<?= h($m_texto) ?>">
                <?= menu_svg($menu_iconos, $m_icono) ?><span class="txt"><?= h($m_texto) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php else:
        $m_abierto = false;
        foreach ($m_sec['items'] as [$m_t, $m_arch, $m_i]) if (in_array($menu_actual, $m_arch, true)) $m_abierto = true;
      ?>
        <div class="grupo <?= $m_abierto ? '' : 'cerrado' ?>">
          <button type="button" class="nav-item nav-grupo" aria-expanded="<?= $m_abierto ? 'true' : 'false' ?>" title="<?= h($m_sec['titulo']) ?>"
            onclick="var g=this.parentNode;var c=g.classList.toggle('cerrado');this.setAttribute('aria-expanded',c?'false':'true')">
            <?= menu_svg($menu_iconos, $m_sec['icono']) ?><span class="txt"><?= h($m_sec['titulo']) ?></span><span class="chev txt">&#9662;</span>
          </button>
          <div class="grupo-items">
            <?php foreach ($m_sec['items'] as $m_href => [$m_texto, $m_archivos, $m_icono]): ?>
              <a href="<?= h($m_href) ?>" class="nav-item nav-sub <?= in_array($menu_actual, $m_archivos, true) ? 'activo' : '' ?>" title="<?= h($m_texto) ?>">
                <?= menu_svg($menu_iconos, $m_icono) ?><span class="txt"><?= h($m_texto) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-pie">
    <span class="txt"><?= h($_SESSION['usuario'] ?? '') ?></span>
    <a href="logout.php" title="Cerrar sesión"><span class="txt">Cerrar sesión</span><span class="solo-colapsado">&#x2715;</span></a>
  </div>
</aside>
<div class="sidebar-fondo" onclick="document.body.classList.remove('menu-abierto')"></div>

<?php // Ventana para elegir el evento de lanzamiento. Sin evento elegido se abre sola en las pantallas de lanzamientos y no se puede cerrar.
$m_modal_auto = $menu_ev_actual === null && $menu_pagina_lanz !== null; ?>
<div class="modal-fondo" id="modal-evento" <?= $m_modal_auto ? '' : 'hidden' ?> data-obligatorio="<?= $menu_ev_actual === null ? '1' : '0' ?>">
  <div class="modal-caja" role="dialog" aria-modal="true" aria-labelledby="modal-evento-titulo">
    <h2 id="modal-evento-titulo">Control de Lanzamientos</h2>
    <?php if ($menu_eventos): ?>
      <form method="get" action="<?= h($menu_pagina_lanz ?? 'index.php') ?>">
        <label>Seleccione el evento
          <select name="ev" required>
            <?php if ($menu_ev_actual === null): ?><option value="">Seleccione...</option><?php endif; ?>
            <?php foreach ($menu_eventos as $m_ev): ?>
              <option value="<?= (int) $m_ev['idevento'] ?>" <?= (int) $m_ev['idevento'] === $menu_ev_actual ? 'selected' : '' ?>><?= h($m_ev['descripcionevento']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="actions">
          <button type="submit">Continuar</button>
          <?php if ($menu_ev_actual !== null): ?><a href="#" onclick="cerrarModalEvento();return false">Cancelar</a><?php endif; ?>
        </div>
      </form>
    <?php else: ?>
      <p>No hay eventos de lanzamiento activos.<?= es_admin() ? ' <a href="eventos.php">Crear un evento</a>' : ' Pida al administrador que cree uno.' ?></p>
      <?php if ($menu_ev_actual !== null || !$m_modal_auto): ?><div class="actions"><a href="#" onclick="cerrarModalEvento();return false">Cerrar</a></div><?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<script>
function abrirModalEvento() { document.getElementById('modal-evento').hidden = false; }
function cerrarModalEvento() { var m = document.getElementById('modal-evento'); if (m.dataset.obligatorio !== '1') m.hidden = true; }
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrarModalEvento(); });
</script>
<?php endif; ?>
<main class="container">

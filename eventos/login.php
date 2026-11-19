<?php
require __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['token'])) {
    header('Location: index.php');
    exit;
}

$error = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $usuario  = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($usuario === '' || $password === '') {
        $error = 'Ingrese usuario y contraseña';
    } else {
        $res = api_request('POST', '/auth/login', ['usuario' => $usuario, 'password' => $password]);
        if ($res['status'] === 200 && !empty($res['data']['token'])) {
            session_regenerate_id(true);
            $_SESSION['token']   = $res['data']['token'];
            $_SESSION['usuario'] = $usuario;
            cargar_rol_sesion($res['data']);
            header('Location: index.php');
            exit;
        }
        $error = implode(' ', api_errores($res));
    }
}

$titulo = 'Control de Encuentros y Lanzamientos';
require __DIR__ . '/includes/header.php';
?>
<div class="lg">
  <section class="lg-marca" aria-hidden="false">
    <span class="lg-orbe lg-orbe1"></span><span class="lg-orbe lg-orbe2"></span><span class="lg-orbe lg-orbe3"></span>
    <div class="lg-marca-cont">
      <div class="lg-logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/><path d="M9 16l2 2 4-4"/></svg>
      </div>
      <h1>Control de Encuentros<br>y Lanzamientos</h1>
      <p class="lg-lema">Todo el seguimiento de tu red en un solo lugar.</p>
      <ul class="lg-lista">
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l4 4 10-10"/></svg>Registro de lanzamientos por subred</li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l4 4 10-10"/></svg>Control de encuentros</li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l4 4 10-10"/></svg>Inventario, ingresos y egresos con reportes</li>
      </ul>
    </div>
  </section>

  <section class="lg-form">
    <div class="lg-caja">
      <div class="lg-logo lg-logo-mov">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/><path d="M9 16l2 2 4-4"/></svg>
      </div>
      <p class="lg-mov-titulo">Control de Encuentros y Lanzamientos</p>
      <h2>Bienvenido</h2>
      <p class="lg-sub">Ingresa tus credenciales para continuar</p>

      <?php if ($error): ?><div class="alert error" role="alert"><?= h($error) ?></div><?php endif; ?>

      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

        <label class="lg-campo">Usuario
          <span class="lg-input">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
            <input type="text" name="usuario" placeholder="Tu usuario" required autofocus autocomplete="username">
          </span>
        </label>

        <label class="lg-campo">Contraseña
          <span class="lg-input">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
            <input type="password" name="password" id="lg-pass" placeholder="Tu contraseña" required autocomplete="current-password">
            <button type="button" class="lg-ver" aria-label="Mostrar u ocultar contraseña" onclick="var i=document.getElementById('lg-pass');i.type=i.type==='password'?'text':'password';this.classList.toggle('on')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </span>
        </label>

        <button type="submit" class="lg-btn">Ingresar</button>
      </form>
      <p class="lg-pie">&copy; <?= date('Y') ?> Control de Encuentros y Lanzamientos</p>
    </div>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

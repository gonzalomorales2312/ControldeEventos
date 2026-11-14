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
<div class="login-pantalla">
<h1 class="login-titulo">Control de Encuentros y Lanzamientos</h1>
<div class="card login">
  <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <label>Usuario
      <input type="text" name="usuario" required autofocus>
    </label>
    <label>Contraseña
      <input type="password" name="password" required>
    </label>
    <button type="submit">Ingresar</button>
  </form>
</div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

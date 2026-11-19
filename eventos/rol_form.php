<?php
require __DIR__ . '/includes/auth.php';
requiere_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$errores = [];
$reg = ['nombrerol' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $reg['nombrerol'] = trim($_POST['nombrerol'] ?? '');
    // Ingresos y egresos son solo del rol admin (se controla en la aplicación), no se asignan por rol
    $payload = ['nombrerol' => $reg['nombrerol'], 'acceso_ingresos' => false, 'acceso_egresos' => false];

    $res = $id ? api_auth('PUT', "/roles/$id", $payload) : api_auth('POST', '/roles', $payload);
    if (in_array($res['status'], [200, 201], true)) {
        $_SESSION['flash_ok'] = $id ? 'Rol actualizado correctamente' : 'Rol creado correctamente';
        header('Location: roles.php');
        exit;
    }
    $errores = api_errores($res);
} elseif ($id) {
    $res = api_auth('GET', "/roles/$id");
    if ($res['status'] === 200) {
        $reg['nombrerol'] = $res['data']['nombrerol'] ?? '';
    } else {
        $errores = api_errores($res);
    }
}

$titulo = $id ? 'Editar rol' : 'Nuevo rol';
require __DIR__ . '/includes/header.php';
?>
<h1><?= h($titulo) ?></h1>
<?php foreach ($errores as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>

<form method="post" class="card grid">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <label>Nombre del rol
    <input type="text" name="nombrerol" maxlength="50" value="<?= h($reg['nombrerol']) ?>" required>
  </label>
  <div class="actions">
    <button type="submit"><?= $id ? 'Actualizar' : 'Guardar' ?></button>
    <a href="roles.php">Cancelar</a>
  </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>

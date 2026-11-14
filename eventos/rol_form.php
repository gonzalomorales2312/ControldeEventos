<?php
require __DIR__ . '/includes/auth.php';
requiere_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$errores = [];
$reg = ['nombrerol' => '', 'acceso_ingresos' => false, 'acceso_egresos' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $reg['nombrerol'] = trim($_POST['nombrerol'] ?? '');
    $reg['acceso_ingresos'] = isset($_POST['acceso_ingresos']);
    $reg['acceso_egresos'] = isset($_POST['acceso_egresos']);
    $payload = [
        'nombrerol'       => $reg['nombrerol'],
        'acceso_ingresos' => $reg['acceso_ingresos'],
        'acceso_egresos'  => $reg['acceso_egresos'],
    ];

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
        $reg['acceso_ingresos'] = !empty($res['data']['acceso_ingresos']);
        $reg['acceso_egresos'] = !empty($res['data']['acceso_egresos']);
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
  <label><input type="checkbox" name="acceso_ingresos" value="1" style="width:auto" <?= $reg['acceso_ingresos'] ? 'checked' : '' ?>> Acceso a Ingresos</label>
  <label><input type="checkbox" name="acceso_egresos" value="1" style="width:auto" <?= $reg['acceso_egresos'] ? 'checked' : '' ?>> Acceso a Egresos</label>
  <div class="actions">
    <button type="submit"><?= $id ? 'Actualizar' : 'Guardar' ?></button>
    <a href="roles.php">Cancelar</a>
  </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>

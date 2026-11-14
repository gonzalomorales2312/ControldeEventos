<?php
require __DIR__ . '/includes/auth.php';
requiere_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$errores = [];
$reg = ['usuario' => '', 'idrol' => '', 'subred' => '', 'password' => ''];

$rr = api_auth('GET', '/roles');
$roles = $rr['status'] === 200 ? $rr['data'] : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $reg['usuario'] = trim($_POST['usuario'] ?? '');
    $reg['idrol'] = trim($_POST['idrol'] ?? '');
    $reg['subred'] = trim($_POST['subred'] ?? '');
    $reg['password'] = $_POST['password'] ?? '';

    $payload = ['usuario' => $reg['usuario'], 'idrol' => ctype_digit($reg['idrol']) ? (int) $reg['idrol'] : $reg['idrol'],
                'subred' => $reg['subred'] === '' ? null : $reg['subred']];
    // En edición la contraseña es opcional: vacía = no cambiar
    if (!$id || $reg['password'] !== '') $payload['password'] = $reg['password'];

    $res = $id ? api_auth('PUT', "/auth/usuarios/$id", $payload) : api_auth('POST', '/auth/usuarios', $payload);
    if (in_array($res['status'], [200, 201], true)) {
        $_SESSION['flash_ok'] = $id ? 'Usuario actualizado correctamente' : 'Usuario creado correctamente';
        header('Location: usuarios.php');
        exit;
    }
    $errores = api_errores($res);
} elseif ($id) {
    $res = api_auth('GET', "/auth/usuarios/$id");
    if ($res['status'] === 200) {
        $reg['usuario'] = $res['data']['usuario'] ?? '';
        $reg['idrol'] = (string) ($res['data']['idrol'] ?? '');
        $reg['subred'] = (string) ($res['data']['subred'] ?? '');
    } else {
        $errores = api_errores($res);
    }
}

$titulo = $id ? 'Editar usuario' : 'Nuevo usuario';
require __DIR__ . '/includes/header.php';
?>
<h1><?= h($titulo) ?></h1>
<?php foreach ($errores as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>

<form method="post" class="card grid" autocomplete="off">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <label>Usuario
    <input type="text" name="usuario" maxlength="50" value="<?= h($reg['usuario']) ?>" required>
  </label>
  <label>Rol
    <select name="idrol" required>
      <option value="">Seleccione...</option>
      <?php foreach ($roles as $r): ?>
        <option value="<?= (int) $r['idrol'] ?>" <?= (string) $r['idrol'] === $reg['idrol'] ? 'selected' : '' ?>><?= h($r['nombrerol']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Subred (vacío = todas, para administradores)
    <input type="text" name="subred" maxlength="100" value="<?= h($reg['subred']) ?>">
  </label>
  <label>Contraseña<?= $id ? ' (vacía = no cambiar)' : '' ?>
    <input type="password" name="password" minlength="8" autocomplete="new-password" <?= $id ? '' : 'required' ?>>
  </label>
  <div class="actions">
    <button type="submit"><?= $id ? 'Actualizar' : 'Guardar' ?></button>
    <a href="usuarios.php">Cancelar</a>
  </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>

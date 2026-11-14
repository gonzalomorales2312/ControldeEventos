<?php
require __DIR__ . '/includes/auth.php';
requiere_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$campos = ['nombre_lanzado', 'nombre_lider', 'subred', 'numero_boleta', 'monto_boleta'];

$errores = [];
$reg = array_fill_keys($campos, '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    foreach ($campos as $c) $reg[$c] = trim($_POST[$c] ?? '');
    if (subred_usuario() !== null) $reg['subred'] = subred_usuario(); // no admin: siempre su subred

    // Tipos que espera la API
    $payload = $reg;
    $payload['monto_boleta'] = is_numeric($reg['monto_boleta']) ? (float) $reg['monto_boleta'] : $reg['monto_boleta'];

    $res = $id ? api_auth('PUT', "/registros/$id", $payload)      // PUT: actualizar
              : api_auth('POST', '/registros', $payload);         // POST: crear

    if (in_array($res['status'], [200, 201], true)) {
        $_SESSION['flash_ok'] = $id ? 'Registro actualizado correctamente' : 'Registro creado correctamente';
        header('Location: index.php');
        exit;
    }
    $errores = api_errores($res);
} elseif ($id) {
    $res = api_auth('GET', "/registros/$id");                     // GET: consultar uno
    if ($res['status'] === 200) {
        foreach ($campos as $c) $reg[$c] = $res['data'][$c] ?? '';
        if (subred_usuario() !== null && $reg['subred'] !== subred_usuario()) {
            $_SESSION['flash_err'] = 'No tiene acceso a ese registro';
            header('Location: index.php');
            exit;
        }
    } else {
        $errores = api_errores($res);
    }
}

$titulo = $id ? 'Editar registro' : 'Nuevo registro';
require __DIR__ . '/includes/header.php';
?>
<h1><?= h($titulo) ?></h1>
<?php foreach ($errores as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>

<form method="post" class="card grid">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

  <label>Nombre del lanzado
    <input type="text" name="nombre_lanzado" value="<?= h($reg['nombre_lanzado']) ?>" required>
  </label>
  <label>Nombre del líder
    <input type="text" name="nombre_lider" value="<?= h($reg['nombre_lider']) ?>" required>
  </label>
  <label>Subred
    <?php if (subred_usuario() !== null): ?>
      <input type="text" name="subred" value="<?= h(subred_usuario()) ?>" readonly required>
    <?php else: ?>
      <input type="text" name="subred" maxlength="100" value="<?= h($reg['subred']) ?>" required>
    <?php endif; ?>
  </label>
  <label>Número de boleta de pago
    <input type="text" name="numero_boleta" value="<?= h($reg['numero_boleta']) ?>" required>
  </label>
  <label>Monto de boleta de pago
    <input type="number" step="0.01" min="0" name="monto_boleta" value="<?= h($reg['monto_boleta']) ?>" required>
  </label>

  <div class="actions">
    <button type="submit"><?= $id ? 'Actualizar' : 'Guardar' ?></button>
    <a href="index.php">Cancelar</a>
  </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>

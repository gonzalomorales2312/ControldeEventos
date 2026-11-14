<?php
require __DIR__ . '/includes/auth.php';
requiere_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$campos = ['descripcion', 'valor', 'existencia'];
$errores = [];
$reg = array_fill_keys($campos, '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    foreach ($campos as $c) $reg[$c] = trim($_POST[$c] ?? '');

    $payload = ['descripcion' => $reg['descripcion']];
    $payload['valor'] = is_numeric($reg['valor']) ? (float) $reg['valor'] : $reg['valor'];
    $payload['existencia'] = ctype_digit($reg['existencia']) ? (int) $reg['existencia'] : ($reg['existencia'] === '' ? 0 : $reg['existencia']);

    $res = $id ? api_auth('PUT', "/inventario/$id", $payload) : api_auth('POST', '/inventario', $payload);
    if (in_array($res['status'], [200, 201], true)) {
        $_SESSION['flash_ok'] = $id ? 'Artículo actualizado correctamente' : 'Artículo creado correctamente';
        header('Location: inventario.php');
        exit;
    }
    $errores = api_errores($res);
} elseif ($id) {
    $res = api_auth('GET', "/inventario/$id");
    if ($res['status'] === 200) {
        foreach ($campos as $c) $reg[$c] = $res['data'][$c] ?? '';
    } else {
        $errores = api_errores($res);
    }
}

$titulo = $id ? 'Editar artículo' : 'Nuevo artículo';
require __DIR__ . '/includes/header.php';
?>
<h1><?= h($titulo) ?></h1>
<?php foreach ($errores as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>

<form method="post" class="card grid">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <label>Descripción
    <input type="text" name="descripcion" maxlength="255" value="<?= h($reg['descripcion']) ?>" required>
  </label>
  <label>Valor total de la existencia
    <input type="number" step="0.01" min="0" name="valor" value="<?= h($reg['valor']) ?>" required>
  </label>
  <label>Existencia
    <input type="number" step="1" min="0" name="existencia" value="<?= h($reg['existencia']) ?>">
  </label>
  <div class="actions">
    <button type="submit"><?= $id ? 'Actualizar' : 'Guardar' ?></button>
    <a href="inventario.php">Cancelar</a>
  </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>

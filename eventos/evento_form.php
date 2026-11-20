<?php
require __DIR__ . '/includes/auth.php';
requiere_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$errores = [];
$reg = ['tipoevento' => '', 'descripcionevento' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    foreach ($reg as $c => $_) $reg[$c] = trim($_POST[$c] ?? '');

    $res = $id ? api_auth('PUT', "/eventos/$id", $reg) : api_auth('POST', '/eventos', $reg);
    if (in_array($res['status'], [200, 201], true)) {
        $_SESSION['flash_ok'] = $id ? 'Evento actualizado correctamente' : 'Evento creado correctamente';
        header('Location: eventos.php');
        exit;
    }
    $errores = api_errores($res);
} elseif ($id) {
    $res = api_auth('GET', "/eventos/$id");
    if ($res['status'] === 200) {
        foreach ($reg as $c => $_) $reg[$c] = $res['data'][$c] ?? '';
    } else {
        $errores = api_errores($res);
    }
}

$titulo = $id ? 'Editar evento' : 'Nuevo evento';
require __DIR__ . '/includes/header.php';
?>
<h1><?= h($titulo) ?></h1>
<?php foreach ($errores as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>

<form method="post" class="card grid" autocomplete="off">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <label>Tipo de evento
    <select name="tipoevento" required>
      <option value="">Seleccione...</option>
      <?php $tipos = ['Lanzamiento', 'Encuentro'];
      if ($reg['tipoevento'] !== '' && !in_array($reg['tipoevento'], $tipos, true)) $tipos[] = $reg['tipoevento']; // valor previo distinto ?>
      <?php foreach ($tipos as $t): ?>
        <option value="<?= h($t) ?>" <?= $reg['tipoevento'] === $t ? 'selected' : '' ?>><?= h($t) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Descripción del evento
    <input type="text" name="descripcionevento" maxlength="255" value="<?= h($reg['descripcionevento']) ?>" required>
  </label>
  <div class="actions">
    <button type="submit"><?= $id ? 'Actualizar' : 'Guardar' ?></button>
    <a href="eventos.php">Cancelar</a>
  </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>

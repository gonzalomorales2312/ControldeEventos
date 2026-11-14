<?php
require __DIR__ . '/includes/auth.php';
requiere_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$errores = [];
$reg = ['idinventario' => '', 'descripcion' => '', 'cantidad' => '', 'vvalor' => '', 'observaciones' => ''];
$bloqueado = false;
$articulo_actual = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    foreach ($reg as $c => $_) $reg[$c] = trim($_POST[$c] ?? '');

    $payload = [
        'descripcion'   => $reg['descripcion'],
        'cantidad'      => ctype_digit($reg['cantidad']) ? (int) $reg['cantidad'] : $reg['cantidad'],
        'vvalor'        => is_numeric($reg['vvalor']) ? (float) $reg['vvalor'] : $reg['vvalor'],
        'observaciones' => $reg['observaciones'],
    ];
    // Todo egreso entra al inventario: sin artículo elegido la API crea uno nuevo
    if (!$id && $reg['idinventario'] !== '') $payload['idinventario'] = (int) $reg['idinventario'];

    $res = $id ? api_auth('PUT', "/egresos/$id", $payload) : api_auth('POST', '/egresos', $payload);
    if (in_array($res['status'], [200, 201], true)) {
        $_SESSION['flash_ok'] = $id ? 'Egreso actualizado correctamente (inventario ajustado)'
                                    : 'Egreso registrado y agregado al inventario';
        header('Location: egresos.php');
        exit;
    }
    $errores = api_errores($res);
    if ($id) $reg['idinventario'] = trim($_POST['idinventario_actual'] ?? '');
} elseif ($id) {
    $res = api_auth('GET', "/egresos/$id");
    if ($res['status'] === 200) {
        $e = $res['data']['egreso'] ?? $res['data'];
        foreach (['descripcion', 'cantidad', 'vvalor', 'observaciones'] as $c) $reg[$c] = $e[$c] ?? '';
        $reg['idinventario'] = (string) ($e['idinventario'] ?? '');
    } else {
        $errores = api_errores($res);
        $bloqueado = true;
    }
}

// Artículos del inventario (solo al crear). Si el rol no puede ver inventario, queda solo "artículo nuevo"
$articulos = [];
if (!$id) {
    $ra = api_auth('GET', '/inventario?estado=activos&limit=100');
    if ($ra['status'] === 200) $articulos = $ra['data']['data'];
} elseif (!$bloqueado && $reg['idinventario'] !== '') {
    $ri = api_auth('GET', '/inventario/' . (int) $reg['idinventario']);
    if ($ri['status'] === 200) $articulo_actual = $ri['data']['descripcion'];
}

$titulo = $id ? 'Editar egreso' : 'Nuevo egreso';
require __DIR__ . '/includes/header.php';
?>
<h1><?= h($titulo) ?></h1>
<?php foreach ($errores as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>

<?php if ($bloqueado): ?>
  <a href="egresos.php">Volver</a>
<?php else: ?>
<form method="post" class="card grid">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

  <?php if ($id): ?>
    <input type="hidden" name="idinventario_actual" value="<?= h($reg['idinventario']) ?>">
    <label>Artículo del inventario
      <input type="text" value="<?= $reg['idinventario'] === '' ? 'Sin artículo asociado' : h(($articulo_actual ?: 'Artículo') . ' (#' . $reg['idinventario'] . ')') ?>" disabled>
    </label>
  <?php else: ?>
    <label>Artículo del inventario (vacío = crear artículo nuevo)
      <select name="idinventario" id="idinventario">
        <option value="">— Artículo nuevo —</option>
        <?php foreach ($articulos as $a): ?>
          <option value="<?= (int) $a['id'] ?>" data-desc="<?= h($a['descripcion']) ?>" <?= (string) $a['id'] === $reg['idinventario'] ? 'selected' : '' ?>>
            <?= h($a['descripcion']) ?> (existencia: <?= h($a['existencia']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>

  <label>Descripción
    <input type="text" name="descripcion" id="descripcion" maxlength="255" value="<?= h($reg['descripcion']) ?>" required>
  </label>
  <label>Cantidad
    <input type="number" step="1" min="1" name="cantidad" id="cantidad" value="<?= h($reg['cantidad']) ?>" required>
  </label>
  <label>Valor total pagado
    <input type="number" step="0.01" min="0" name="vvalor" id="vvalor" value="<?= h($reg['vvalor']) ?>" required>
  </label>
  <label>Precio unitario (calculado)
    <input type="text" id="preciounitario" value="" readonly tabindex="-1">
  </label>
  <label>Observaciones
    <input type="text" name="observaciones" value="<?= h($reg['observaciones']) ?>">
  </label>

  <div class="actions">
    <button type="submit"><?= $id ? 'Actualizar' : 'Guardar' ?></button>
    <a href="egresos.php">Cancelar</a>
  </div>
</form>
<script>
(function () {
  var cant = document.getElementById('cantidad'), val = document.getElementById('vvalor'),
      pu = document.getElementById('preciounitario'), art = document.getElementById('idinventario'),
      desc = document.getElementById('descripcion');
  function calcular() {
    var c = parseFloat(cant.value), v = parseFloat(val.value);
    pu.value = (c > 0 && v >= 0) ? (v / c).toFixed(2) : '';
  }
  cant.addEventListener('input', calcular);
  val.addEventListener('input', calcular);
  if (art) art.addEventListener('change', function () {
    var o = art.options[art.selectedIndex];
    if (o && o.dataset.desc && !desc.value.trim()) desc.value = o.dataset.desc;
  });
  calcular();
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

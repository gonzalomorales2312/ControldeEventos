<?php
require __DIR__ . '/includes/auth.php';
requiere_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$campos = ['tipopersona', 'nombrepersona', 'descripcion', 'vvalor', 'observaciones'];
$errores = [];
$reg = array_fill_keys($campos, '');
$bloqueado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    foreach ($campos as $c) $reg[$c] = trim($_POST[$c] ?? '');

    $payload = $reg;
    $payload['vvalor'] = is_numeric($reg['vvalor']) ? (float) $reg['vvalor'] : $reg['vvalor'];
    if (!$id) $payload['idevento'] = evento_actual(); // evento del menú; al editar se conserva el suyo

    $res = $id ? api_auth('PUT', "/ingresos/$id", $payload) : api_auth('POST', '/ingresos', $payload);
    if (in_array($res['status'], [200, 201], true)) {
        $_SESSION['flash_ok'] = $id ? 'Ingreso actualizado correctamente' : 'Ingreso creado correctamente';
        header('Location: ingresos.php');
        exit;
    }
    $errores = api_errores($res);
} elseif ($id) {
    $res = api_auth('GET', "/ingresos/$id");
    if ($res['status'] === 200) {
        foreach ($campos as $c) $reg[$c] = $res['data'][$c] ?? '';    } else {
        $errores = api_errores($res);
        $bloqueado = true;
    }
}

// Valores de los combos: nombres distintos de la tabla registro, por tipo
$personas = ['lider' => [], 'lanzado' => []];
if (!$bloqueado) {
    foreach (array_keys($personas) as $t) {
        $rp = api_auth('GET', '/ingresos/personas?tipo=' . $t);
        if ($rp['status'] === 200) $personas[$t] = $rp['data']['data'] ?? [];
        else $errores = array_merge($errores, api_errores($rp));
    }
}

$titulo = $id ? 'Editar ingreso' : 'Nuevo ingreso';
require __DIR__ . '/includes/header.php';
?>
<h1><?= h($titulo) ?></h1>
<?php foreach (array_unique($errores) as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>

<?php if ($bloqueado): ?>
  <a href="ingresos.php">Volver</a>
<?php else: ?>
<form method="post" class="card grid">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <label>¿Es líder o lanzado?
    <select name="tipopersona" id="tipopersona" required>
      <option value="">Seleccione...</option>
      <option value="lider" <?= $reg['tipopersona'] === 'lider' ? 'selected' : '' ?>>Líder</option>
      <option value="lanzado" <?= $reg['tipopersona'] === 'lanzado' ? 'selected' : '' ?>>Lanzado</option>
    </select>
  </label>
  <label>Nombre
    <select name="nombrepersona" id="nombrepersona" required>
      <option value="">Seleccione primero el tipo...</option>
    </select>
  </label>
  <label>Descripción
    <input type="text" name="descripcion" maxlength="255" value="<?= h($reg['descripcion']) ?>" required>
  </label>
  <label>Valor
    <input type="number" step="0.01" min="0" name="vvalor" value="<?= h($reg['vvalor']) ?>" required>
  </label>
  <label>Observaciones
    <input type="text" name="observaciones" value="<?= h($reg['observaciones']) ?>">
  </label>
  <div class="actions">
    <button type="submit"><?= $id ? 'Actualizar' : 'Guardar' ?></button>
    <a href="ingresos.php">Cancelar</a>
  </div>
</form>
<script>
(function () {
  var personas = <?= json_encode($personas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var seleccionado = <?= json_encode($reg['nombrepersona'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var tipo = document.getElementById('tipopersona'), nombre = document.getElementById('nombrepersona');
  function llenar() {
    var lista = personas[tipo.value] || [];
    nombre.innerHTML = '';
    var o = document.createElement('option');
    o.value = '';
    o.textContent = tipo.value ? (lista.length ? 'Seleccione...' : 'No hay registros') : 'Seleccione primero el tipo...';
    nombre.appendChild(o);
    lista.forEach(function (n) {
      var op = document.createElement('option');
      op.value = n; op.textContent = n;
      if (n === seleccionado) op.selected = true;
      nombre.appendChild(op);
    });
  }
  tipo.addEventListener('change', function () { seleccionado = ''; llenar(); });
  llenar();
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

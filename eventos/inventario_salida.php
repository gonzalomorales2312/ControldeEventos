<?php
require __DIR__ . '/includes/auth.php';
requiere_admin();

$cfg = config();
$page = max((int) ($_GET['page'] ?? 1), 1);
$idfiltro = (int) ($_GET['idinventario'] ?? 0);
$errores = [];
$reg = ['idinventario' => $idfiltro ?: '', 'cantidad' => '', 'motivo' => '', 'observaciones' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    foreach ($reg as $c => $_) $reg[$c] = trim($_POST[$c] ?? '');

    $payload = [
        'idinventario'  => ctype_digit((string) $reg['idinventario']) ? (int) $reg['idinventario'] : $reg['idinventario'],
        'cantidad'      => ctype_digit($reg['cantidad']) ? (int) $reg['cantidad'] : $reg['cantidad'],
        'motivo'        => $reg['motivo'],
        'observaciones' => $reg['observaciones'],
    ];
    $res = api_auth('POST', '/inventario/salidas', $payload);
    if ($res['status'] === 201) {
        $_SESSION['flash_ok'] = 'Salida registrada correctamente';
        header('Location: inventario_salida.php' . ($idfiltro ? "?idinventario=$idfiltro" : ''));
        exit;
    }
    $errores = api_errores($res);
    if (isset($res['data']['existencia_disponible'])) {
        $errores[] = 'Existencia disponible: ' . $res['data']['existencia_disponible'];
    }
}

// Artículos activos para el selector
$ra = api_auth('GET', '/inventario?estado=activos&limit=100');
$articulos = $ra['status'] === 200 ? $ra['data']['data'] : [];

// Historial de salidas
$q = ['page' => $page, 'limit' => $cfg['per_page']];
if ($idfiltro) $q['idinventario'] = $idfiltro;
$rs = api_auth('GET', '/inventario/salidas?' . http_build_query($q));
$salidas = $rs['status'] === 200 ? $rs['data']['data'] : [];
$total = $rs['status'] === 200 ? (int) $rs['data']['total'] : 0;
if ($rs['status'] !== 200) $errores = array_merge($errores, api_errores($rs));

$ok = $_SESSION['flash_ok'] ?? null;
unset($_SESSION['flash_ok']);

$titulo = 'Salidas de inventario';
require __DIR__ . '/includes/header.php';
?>
<h1>Salidas de inventario</h1>
<?php if ($ok): ?><div class="alert ok"><?= h($ok) ?></div><?php endif; ?>
<?php foreach ($errores as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>

<form method="post" class="card grid">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <label>Artículo
    <select name="idinventario" required>
      <option value="">Seleccione...</option>
      <?php foreach ($articulos as $a): ?>
        <option value="<?= (int) $a['id'] ?>" <?= (string) $a['id'] === (string) $reg['idinventario'] ? 'selected' : '' ?>>
          <?= h($a['descripcion']) ?> (existencia: <?= h($a['existencia']) ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Cantidad
    <input type="number" step="1" min="1" name="cantidad" value="<?= h($reg['cantidad']) ?>" required>
  </label>
  <label>Motivo
    <input type="text" name="motivo" maxlength="255" value="<?= h($reg['motivo']) ?>">
  </label>
  <label>Observaciones
    <input type="text" name="observaciones" value="<?= h($reg['observaciones']) ?>">
  </label>
  <div class="actions">
    <button type="submit">Registrar salida</button>
    <a href="inventario.php">Volver al inventario</a>
  </div>
</form>

<h2>Historial<?= $idfiltro ? ' del artículo #' . $idfiltro . ' (<a href="inventario_salida.php">ver todos</a>)' : '' ?></h2>
<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><th>Artículo</th><th>Cantidad</th><th>Motivo</th><th>Observaciones</th><th>Fecha</th></tr></thead>
  <tbody>
  <?php foreach ($salidas as $s): ?>
    <tr>
      <td><?= h($s['id']) ?></td>
      <td><?= h($s['articulo']) ?></td>
      <td class="num"><?= h($s['cantidad']) ?></td>
      <td><?= h($s['motivo']) ?></td>
      <td><?= h($s['observaciones']) ?></td>
      <td><?= h($s['creado_en']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$salidas): ?><tr><td colspan="6" class="center">Sin salidas</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?= paginador($page, $total, $cfg['per_page'], $idfiltro ? ['idinventario' => $idfiltro] : []) ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

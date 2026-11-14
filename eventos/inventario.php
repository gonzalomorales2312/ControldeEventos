<?php
require __DIR__ . '/includes/auth.php';
requiere_admin();
procesar_estado('/inventario');

$cfg    = config();
$page   = max((int) ($_GET['page'] ?? 1), 1);
$buscar = trim($_GET['buscar'] ?? '');
$estado = in_array($_GET['estado'] ?? '', ['activos', 'inactivos', 'todos'], true) ? $_GET['estado'] : 'activos';
$agotados = ($_GET['agotados'] ?? '') === 'true';

$filtros = ['estado' => $estado];
if ($buscar !== '') $filtros['buscar'] = $buscar;
if ($agotados) $filtros['agotados'] = 'true';

$res = api_auth('GET', '/inventario?' . http_build_query($filtros + ['page' => $page, 'limit' => $cfg['per_page']]));
$errores = [];
$filas = [];
$total = 0;
$valor_total = 0;
if ($res['status'] === 200) {
    $filas = $res['data']['data'] ?? [];
    $total = (int) ($res['data']['total'] ?? 0);
    $valor_total = (float) ($res['data']['valor_total_inventario'] ?? 0);
} else {
    $errores = api_errores($res);
}
[$ok, $err] = flash();
if ($err) $errores[] = $err;

$titulo = 'Inventario';
require __DIR__ . '/includes/header.php';
?>
<h1>Inventario</h1>
<?php if ($ok): ?><div class="alert ok"><?= h($ok) ?></div><?php endif; ?>
<?php foreach ($errores as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>

<div class="resumen"><div class="card">Valor total del inventario<b><?= number_format($valor_total, 2) ?></b></div></div>

<div class="toolbar">
  <a class="btn" href="inventario_form.php">+ Nuevo artículo</a>
  <a class="btn" href="inventario_salida.php">Registrar salida</a>
  <form method="get" class="search">
    <input type="text" name="buscar" value="<?= h($buscar) ?>" placeholder="Buscar descripción">
    <select name="estado" style="width:auto;margin:0">
      <?php foreach (['activos', 'inactivos', 'todos'] as $e): ?>
        <option value="<?= $e ?>" <?= $estado === $e ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
      <?php endforeach; ?>
    </select>
    <label style="margin:0"><input type="checkbox" name="agotados" value="true" style="width:auto" <?= $agotados ? 'checked' : '' ?>> Agotados</label>
    <button type="submit">Filtrar</button>
  </form>
</div>

<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><th>Descripción</th><th>Existencia</th><th>Valor total</th><th>Precio unitario</th><th>Estado</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($filas as $r): $activo = (bool) $r['activo']; ?>
    <tr class="<?= $activo ? '' : 'inactivo' ?>">
      <td><?= h($r['id']) ?></td>
      <td><?= h($r['descripcion']) ?></td>
      <td class="num"><?= h($r['existencia']) ?></td>
      <td class="num"><?= number_format((float) $r['valor'], 2) ?></td>
      <td class="num"><?= $r['preciounitario'] === null ? '—' : number_format((float) $r['preciounitario'], 2) ?></td>
      <td><?= $activo ? 'Activo' : 'Inactivo' ?></td>
      <td>
        <a href="inventario_form.php?id=<?= (int) $r['id'] ?>">Editar</a>
        <a href="inventario_salida.php?idinventario=<?= (int) $r['id'] ?>">Salidas</a>
        <?= boton_estado((int) $r['id'], $activo) ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$filas && !$errores): ?><tr><td colspan="7" class="center">Sin artículos</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?= paginador($page, $total, $cfg['per_page'], $filtros) ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

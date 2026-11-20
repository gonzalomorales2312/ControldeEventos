<?php
// Listado compartido de Ingresos / Egresos.
// La página que lo incluye define: $mov_tipo ('ingreso'|'egreso'), $mov_archivo ('ingresos.php'|'egresos.php'),
// $mov_nombre ('Ingresos'|'Egresos'), $mov_form ('ingreso_form.php'|'egreso_form.php'), $mov_ruta ('/ingresos'|'/egresos').
require_once __DIR__ . '/auth.php';
requiere_admin();
procesar_estado($mov_ruta);

$cfg    = config();
$page   = max((int) ($_GET['page'] ?? 1), 1);
$buscar = trim($_GET['buscar'] ?? '');
$estado = in_array($_GET['estado'] ?? '', ['activos', 'inactivos', 'todos'], true) ? $_GET['estado'] : 'activos';

$tipopersona = in_array($_GET['tipopersona'] ?? '', ['lider', 'lanzado'], true) ? $_GET['tipopersona'] : '';

$filtros = ['estado' => $estado];
if ($buscar !== '') $filtros['buscar'] = $buscar;
if ($mov_tipo === 'ingreso' && $tipopersona !== '') $filtros['tipopersona'] = $tipopersona;
// Solo los movimientos del evento elegido en el menú
if (evento_actual() !== null) $filtros['idevento'] = evento_actual();

$res = api_auth('GET', $mov_ruta . '?' . http_build_query($filtros + ['page' => $page, 'limit' => $cfg['per_page']]));
$errores = [];
$filas = [];
$total = 0;
$suma = 0.0;
if ($res['status'] === 200) {
    $d = $res['data'];
    $filas = $d['data'] ?? [];
    $total = (int) ($d['total'] ?? 0);
    $suma = (float) ($d['total_valor'] ?? 0);
} else {
    $errores = api_errores($res);
}
[$ok, $err] = flash();
if ($err) $errores[] = $err;

$titulo = $mov_nombre;
require __DIR__ . '/header.php';
?>
<h1><?= h($mov_nombre) ?></h1>
<?php if ($ok): ?><div class="alert ok"><?= h($ok) ?></div><?php endif; ?>
<?php foreach ($errores as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>

<div class="resumen"><div class="card">Total de <?= h(strtolower($mov_nombre)) ?><b><?= number_format($suma, 2) ?></b></div></div>

<div class="toolbar">
  <a class="btn" href="<?= h($mov_form) ?>">+ Nuevo <?= $mov_tipo ?></a>
  <form method="get" class="search">
    <input type="text" name="buscar" value="<?= h($buscar) ?>" placeholder="Buscar descripción, observaciones<?= $mov_tipo === 'ingreso' ? ' o nombre' : '' ?>">
    <?php if ($mov_tipo === 'ingreso'): ?>
    <select name="tipopersona" style="width:auto;margin:0">
      <option value="">Líder y lanzado</option>
      <option value="lider" <?= $tipopersona === 'lider' ? 'selected' : '' ?>>Líder</option>
      <option value="lanzado" <?= $tipopersona === 'lanzado' ? 'selected' : '' ?>>Lanzado</option>
    </select>
    <?php endif; ?>
    <select name="estado" style="width:auto;margin:0">
      <?php foreach (['activos', 'inactivos', 'todos'] as $e): ?>
        <option value="<?= $e ?>" <?= $estado === $e ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Filtrar</button>
  </form>
</div>

<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><?php if ($mov_tipo === 'ingreso'): ?><th>Tipo</th><th>Nombre</th><?php endif; ?><th>Descripción</th><?php if ($mov_tipo === 'egreso'): ?><th>Cantidad</th><th>Precio unitario</th><?php endif; ?><th>Valor</th><th>Observaciones</th><th>Fecha</th><th>Estado</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($filas as $r): $activo = (bool) $r['activo']; ?>
    <tr class="<?= $activo ? '' : 'inactivo' ?>">
      <td><?= h($r['id']) ?></td>
      <?php if ($mov_tipo === 'ingreso'): ?>
        <td><?= ['lider' => 'Líder', 'lanzado' => 'Lanzado'][$r['tipopersona'] ?? ''] ?? '—' ?></td>
        <td><?= h($r['nombrepersona'] ?? '') ?></td>
      <?php endif; ?>
      <td><?= h($r['descripcion']) ?></td>
      <?php if ($mov_tipo === 'egreso'):
        $cant = (int) ($r['cantidad'] ?? 0);
        $pu = $r['preciounitario'] ?? ($cant > 0 ? (float) $r['vvalor'] / $cant : null); ?>
        <td class="num"><?= $cant > 0 ? $cant : '—' ?></td>
        <td class="num"><?= $pu === null ? '—' : number_format((float) $pu, 2) ?></td>
      <?php endif; ?>
      <td class="num"><?= number_format((float) $r['vvalor'], 2) ?></td>
      <td><?= h($r['observaciones']) ?></td>
      <td><?= h($r['creado_en']) ?></td>
      <td><?= $activo ? 'Activo' : 'Inactivo' ?></td>
      <td><a href="<?= h($mov_form) ?>?id=<?= (int) $r['id'] ?>">Editar</a><?= boton_estado((int) $r['id'], $activo) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$filas && !$errores): ?><tr><td colspan="9" class="center">Sin <?= h(strtolower($mov_nombre)) ?></td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?= paginador($page, $total, $cfg['per_page'], $filtros) ?>
<?php require __DIR__ . '/footer.php'; ?>

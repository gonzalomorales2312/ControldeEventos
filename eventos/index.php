<?php
require __DIR__ . '/includes/auth.php';
requiere_login();
procesar_estado('/registros');

$cfg    = config();
$page   = max((int) ($_GET['page'] ?? 1), 1);
$buscar = trim($_GET['buscar'] ?? '');
$estado = in_array($_GET['estado'] ?? '', ['activos', 'inactivos', 'todos'], true) ? $_GET['estado'] : 'activos';

$filtros = ['estado' => $estado];
if ($buscar !== '') $filtros['buscar'] = $buscar;
// Solo los registros del evento elegido en el menú
if (evento_actual() !== null) $filtros['idevento'] = evento_actual();
// Roles no admin: solo ven los registros de su subred
$mi_subred = subred_usuario();
if ($mi_subred !== null) $filtros['subred'] = $mi_subred;

$res = api_auth('GET', '/registros?' . http_build_query($filtros + ['page' => $page, 'limit' => $cfg['per_page']]));

$errores = [];
$filas = [];
$total = 0;
if ($res['status'] === 200) {
    $filas = $res['data']['data'] ?? [];
    $total = (int) ($res['data']['total'] ?? 0);
} else {
    $errores = api_errores($res);
}

[$ok, $err] = flash();
if ($err) $errores[] = $err;

$titulo = 'Registro';
require __DIR__ . '/includes/header.php';
?>
<h1>Registro</h1>

<?php if ($ok): ?><div class="alert ok"><?= h($ok) ?></div><?php endif; ?>
<?php foreach ($errores as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>

<div class="toolbar">
  <a class="btn" href="form.php">+ Nuevo</a>
  <form method="get" class="search">
    <input type="text" name="buscar" value="<?= h($buscar) ?>" placeholder="Buscar por nombre, líder, subred o boleta">
    <select name="estado" style="width:auto;margin:0">
      <?php foreach (['activos', 'inactivos', 'todos'] as $e): ?>
        <option value="<?= $e ?>" <?= $estado === $e ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Buscar</button>
    <?php if ($buscar !== '' || $estado !== 'activos'): ?><a href="index.php">Limpiar</a><?php endif; ?>
  </form>
</div>

<div class="table-wrap">
<table>
  <thead>
    <tr>
      <th>ID</th><th>Nombre lanzado</th><th>Líder</th><th>Subred</th><th>Boleta</th><th>Monto</th><th>Imagen</th><th>Estado</th><th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($filas as $r): $activo = (bool) ($r['activo'] ?? 1); ?>
    <tr class="<?= $activo ? '' : 'inactivo' ?>">
      <td><?= h($r['id']) ?></td>
      <td><?= h($r['nombre_lanzado']) ?></td>
      <td><?= h($r['nombre_lider']) ?></td>
      <td><?= h($r['subred']) ?></td>
      <td><?= h($r['numero_boleta']) ?></td>
      <td class="num"><?= number_format((float) $r['monto_boleta'], 2) ?></td>
      <td><?= !empty($r['imagen_boleta']) || !empty($r['tiene_imagen']) ? '<a href="boleta_descargar.php?id=' . (int) $r['id'] . '">Descargar</a>' : '—' ?></td>
      <td><?= $activo ? 'Activo' : 'Inactivo' ?></td>
      <td><a href="form.php?id=<?= (int) $r['id'] ?>">Editar</a><?= boton_estado((int) $r['id'], $activo) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$filas && !$errores): ?>
    <tr><td colspan="9" class="center">Sin registros</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>

<?= paginador($page, $total, $cfg['per_page'], $filtros) ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

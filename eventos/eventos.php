<?php
require __DIR__ . '/includes/auth.php';
requiere_admin();
procesar_estado('/eventos');

$buscar = trim($_GET['buscar'] ?? '');
$estado = in_array($_GET['estado'] ?? '', ['activos', 'inactivos', 'todos'], true) ? $_GET['estado'] : 'activos';
$filtros = ['estado' => $estado];
if ($buscar !== '') $filtros['buscar'] = $buscar;
$tipo = in_array($_GET['tipoevento'] ?? '', ['Lanzamiento', 'Encuentro'], true) ? $_GET['tipoevento'] : '';
if ($tipo !== '') $filtros['tipoevento'] = $tipo;

$res = api_auth('GET', '/eventos?' . http_build_query($filtros));
$filas = $res['status'] === 200 ? ($res['data']['data'] ?? []) : [];
$errores = $res['status'] === 200 ? [] : api_errores($res);
[$ok, $err] = flash();
if ($err) $errores[] = $err;

$titulo = 'Eventos';
require __DIR__ . '/includes/header.php';
?>
<h1>Eventos</h1>
<?php if ($ok): ?><div class="alert ok"><?= h($ok) ?></div><?php endif; ?>
<?php foreach ($errores as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>

<div class="toolbar">
  <a class="btn" href="evento_form.php">+ Nuevo evento</a>
  <form method="get" class="search">
    <input type="text" name="buscar" value="<?= h($buscar) ?>" placeholder="Buscar por tipo o descripción">
    <select name="tipoevento" style="width:auto;margin:0">
      <option value="">Todos los tipos</option>
      <?php foreach (['Lanzamiento', 'Encuentro'] as $t): ?>
        <option value="<?= $t ?>" <?= $tipo === $t ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
    <select name="estado" style="width:auto;margin:0">
      <?php foreach (['activos', 'inactivos', 'todos'] as $e): ?>
        <option value="<?= $e ?>" <?= $estado === $e ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Filtrar</button>
    <?php if ($buscar !== '' || $estado !== 'activos' || $tipo !== ''): ?><a href="eventos.php">Limpiar</a><?php endif; ?>
  </form>
</div>

<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><th>Tipo de evento</th><th>Descripción</th><th>Estado</th><th>Creado</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($filas as $r): $activo = (bool) $r['activo']; ?>
    <tr class="<?= $activo ? '' : 'inactivo' ?>">
      <td><?= h($r['idevento']) ?></td>
      <td><?= h($r['tipoevento']) ?></td>
      <td><?= h($r['descripcionevento']) ?></td>
      <td><?= $activo ? 'Activo' : 'Inactivo' ?></td>
      <td><?= h($r['creado_en']) ?></td>
      <td><a href="evento_form.php?id=<?= (int) $r['idevento'] ?>">Editar</a><?= boton_estado((int) $r['idevento'], $activo) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$filas && !$errores): ?><tr><td colspan="6" class="center">Sin eventos</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

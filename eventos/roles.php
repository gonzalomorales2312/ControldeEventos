<?php
require __DIR__ . '/includes/auth.php';
requiere_admin();
procesar_estado('/roles');

$res = api_auth('GET', '/roles');
$filas = $res['status'] === 200 ? ($res['data'] ?? []) : [];
$errores = $res['status'] === 200 ? [] : api_errores($res);
[$ok, $err] = flash();
if ($err) $errores[] = $err;

$titulo = 'Roles';
require __DIR__ . '/includes/header.php';
?>
<h1>Roles</h1>
<?php if ($ok): ?><div class="alert ok"><?= h($ok) ?></div><?php endif; ?>
<?php foreach ($errores as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>

<div class="toolbar"><a class="btn" href="rol_form.php">+ Nuevo rol</a></div>

<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><th>Rol</th><th>Ingresos</th><th>Egresos</th><th>Estado</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($filas as $r): $activo = (bool) ($r['activo'] ?? 1); ?>
    <tr class="<?= $activo ? '' : 'inactivo' ?>">
      <td><?= h($r['idrol']) ?></td>
      <td><?= h($r['nombrerol']) ?></td>
      <td><?= !empty($r['acceso_ingresos']) ? 'Sí' : 'No' ?></td>
      <td><?= !empty($r['acceso_egresos']) ? 'Sí' : 'No' ?></td>
      <td><?= $activo ? 'Activo' : 'Inactivo' ?></td>
      <td><a href="rol_form.php?id=<?= (int) $r['idrol'] ?>">Editar</a><?= boton_estado((int) $r['idrol'], $activo) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$filas && !$errores): ?><tr><td colspan="6" class="center">Sin roles</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

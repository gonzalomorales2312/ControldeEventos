<?php
require __DIR__ . '/includes/auth.php';
requiere_admin();
procesar_estado('/auth/usuarios');

$res = api_auth('GET', '/auth/usuarios');
$filas = $res['status'] === 200 ? ($res['data'] ?? []) : [];
$errores = $res['status'] === 200 ? [] : api_errores($res);

// Nombres de rol para mostrar en la tabla
$roles = [];
$rr = api_auth('GET', '/roles');
if ($rr['status'] === 200) foreach ($rr['data'] as $r) $roles[$r['idrol']] = $r['nombrerol'];

[$ok, $err] = flash();
if ($err) $errores[] = $err;

$titulo = 'Usuarios';
require __DIR__ . '/includes/header.php';
?>
<h1>Usuarios</h1>
<?php if ($ok): ?><div class="alert ok"><?= h($ok) ?></div><?php endif; ?>
<?php foreach ($errores as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>

<div class="toolbar"><a class="btn" href="usuario_form.php">+ Nuevo usuario</a></div>

<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><th>Usuario</th><th>Rol</th><th>Subred</th><th>Estado</th><th>Creado</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($filas as $u): $activo = (bool) $u['activo']; ?>
    <tr class="<?= $activo ? '' : 'inactivo' ?>">
      <td><?= h($u['id']) ?></td>
      <td><?= h($u['usuario']) ?></td>
      <td><?= h($roles[$u['idrol']] ?? '—') ?></td>
      <td><?= h(($u['subred'] ?? '') !== '' ? $u['subred'] : '—') ?></td>
      <td><?= $activo ? 'Activo' : 'Inactivo' ?></td>
      <td><?= h($u['creado_en']) ?></td>
      <td><a href="usuario_form.php?id=<?= (int) $u['id'] ?>">Editar</a><?= boton_estado((int) $u['id'], $activo) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$filas && !$errores): ?><tr><td colspan="7" class="center">Sin usuarios</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

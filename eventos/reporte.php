<?php
require __DIR__ . '/includes/auth.php';
requiere_admin();

// Trae todas las páginas de un listado de la API (solo registros activos)
function traer_todos(string $ruta, array &$errores): array {
    $filas = [];
    for ($p = 1; $p <= 500; $p++) {
        $res = api_auth('GET', $ruta . '?' . http_build_query(['estado' => 'activos', 'page' => $p, 'limit' => 100]));
        if ($res['status'] !== 200) { $errores = array_merge($errores, api_errores($res)); break; }
        $lote = $res['data']['data'] ?? [];
        if (!$lote) break;
        $filas = array_merge($filas, $lote);
        if (count($filas) >= (int) ($res['data']['total'] ?? 0)) break;
    }
    return $filas;
}

$errores = [];
$desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde'] ?? '') ? $_GET['desde'] : '';
$hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta'] ?? '') ? $_GET['hasta'] : '';

$movs = [];
foreach (traer_todos('/ingresos', $errores) as $r) {
    $movs[] = ['fecha' => (string) $r['creado_en'], 'tipo' => 'Ingreso', 'desc' => $r['descripcion'] ?? '',
               'persona' => $r['nombrepersona'] ?? '', 'ingreso' => (float) $r['vvalor'], 'egreso' => 0.0, 'id' => (int) $r['id']];
}
foreach (traer_todos('/egresos', $errores) as $r) {
    $movs[] = ['fecha' => (string) $r['creado_en'], 'tipo' => 'Egreso', 'desc' => $r['descripcion'] ?? '',
               'persona' => '', 'ingreso' => 0.0, 'egreso' => (float) $r['vvalor'], 'id' => (int) $r['id']];
}
// Orden cronológico (a igual fecha: ingresos primero, luego por id)
usort($movs, fn($a, $b) => [strtotime($a['fecha']), $a['tipo'] === 'Ingreso' ? 0 : 1, $a['id']]
                       <=> [strtotime($b['fecha']), $b['tipo'] === 'Ingreso' ? 0 : 1, $b['id']]);

// Saldo inicial = movimientos anteriores a "desde"; el saldo corre línea por línea
$saldo = 0.0;
$filas = [];
$tot_ing = $tot_egr = 0.0;
foreach ($movs as $m) {
    $dia = substr($m['fecha'], 0, 10);
    if ($desde !== '' && $dia < $desde) { $saldo += $m['ingreso'] - $m['egreso']; continue; }
    if ($hasta !== '' && $dia > $hasta) continue;
    $saldo += $m['ingreso'] - $m['egreso'];
    $tot_ing += $m['ingreso'];
    $tot_egr += $m['egreso'];
    $m['saldo'] = $saldo;
    $filas[] = $m;
}
$saldo_inicial = $filas ? $filas[0]['saldo'] - $filas[0]['ingreso'] + $filas[0]['egreso'] : $saldo;

// Exportar a Excel (SpreadsheetML: no requiere extensiones de PHP)
if (isset($_GET['exportar']) && !$errores) {
    $x = fn($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $s = fn($v) => '<Cell><Data ss:Type="String">' . $x($v) . '</Data></Cell>';
    $n = fn($v) => $v === '' ? '<Cell/>' : '<Cell ss:StyleID="num"><Data ss:Type="Number">' . round((float) $v, 2) . '</Data></Cell>';
    $b = fn($v) => '<Cell ss:StyleID="neg"><Data ss:Type="String">' . $x($v) . '</Data></Cell>';
    $bn = fn($v) => '<Cell ss:StyleID="negnum"><Data ss:Type="Number">' . round((float) $v, 2) . '</Data></Cell>';

    $out = '<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?>'
         . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
         . '<Styles><Style ss:ID="neg"><Font ss:Bold="1"/></Style>'
         . '<Style ss:ID="num"><NumberFormat ss:Format="#,##0.00"/></Style>'
         . '<Style ss:ID="negnum"><Font ss:Bold="1"/><NumberFormat ss:Format="#,##0.00"/></Style></Styles>'
         . '<Worksheet ss:Name="Reporte"><Table>'
         . '<Column ss:Width="120"/><Column ss:Width="60"/><Column ss:Width="220"/><Column ss:Width="160"/><Column ss:Width="80"/><Column ss:Width="80"/><Column ss:Width="90"/>'
         . '<Row>' . implode('', array_map($b, ['Fecha', 'Tipo', 'Descripción', 'Nombre', 'Ingreso', 'Egreso', 'Saldo'])) . '</Row>';
    if ($desde !== '') $out .= '<Row>' . $s('') . $s('') . $b('Saldo inicial') . $s('') . $s('') . $s('') . $bn($saldo_inicial) . '</Row>';
    foreach ($filas as $m) {
        $out .= '<Row>' . $s($m['fecha']) . $s($m['tipo']) . $s($m['desc']) . $s($m['persona'])
              . $n($m['ingreso'] ?: '') . $n($m['egreso'] ?: '') . $n($m['saldo']) . '</Row>';
    }
    $out .= '<Row>' . $b('Totales') . $s('') . $s('') . $s('') . $bn($tot_ing) . $bn($tot_egr) . $bn($saldo_inicial + $tot_ing - $tot_egr) . '</Row>'
          . '</Table></Worksheet></Workbook>';

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reporte_ingresos_egresos_' . date('Ymd') . '.xls"');
    echo $out;
    exit;
}

$titulo = 'Reporte de ingresos y egresos';
require __DIR__ . '/includes/header.php';
?>
<h1>Reporte de ingresos y egresos</h1>
<?php foreach ($errores as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>

<div class="resumen">
  <div class="card">Total ingresos<b><?= number_format($tot_ing, 2) ?></b></div>
  <div class="card">Total egresos<b><?= number_format($tot_egr, 2) ?></b></div>
  <div class="card">Saldo final<b><?= number_format($saldo_inicial + $tot_ing - $tot_egr, 2) ?></b></div>
</div>

<div class="toolbar">
  <form method="get" class="search">
    <label style="margin:0">Desde <input type="date" name="desde" value="<?= h($desde) ?>" style="width:auto;margin:0"></label>
    <label style="margin:0">Hasta <input type="date" name="hasta" value="<?= h($hasta) ?>" style="width:auto;margin:0"></label>
    <button type="submit">Filtrar</button>
    <?php if ($desde !== '' || $hasta !== ''): ?><a href="reporte.php">Limpiar</a><?php endif; ?>
  </form>
  <a class="btn btn-excel" href="?<?= h(http_build_query(array_filter(['desde' => $desde, 'hasta' => $hasta]) + ['exportar' => 1])) ?>">
    <svg class="ico" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="2" y="3" width="20" height="18" rx="2.5" fill="#fff"/><path d="M7.5 8l4 8M11.5 8l-4 8" stroke="#217346" stroke-width="2" stroke-linecap="round"/><path d="M14.5 9h4M14.5 12h4M14.5 15h4" stroke="#217346" stroke-width="1.6" stroke-linecap="round"/></svg>
    Exportar a Excel
  </a>
</div>

<div class="table-wrap">
<table>
  <thead><tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Nombre</th><th>Ingreso</th><th>Egreso</th><th>Saldo</th></tr></thead>
  <tbody>
  <?php if ($desde !== ''): ?>
    <tr><td colspan="6"><em>Saldo inicial</em></td><td class="num"><b><?= number_format($saldo_inicial, 2) ?></b></td></tr>
  <?php endif; ?>
  <?php foreach ($filas as $m): ?>
    <tr>
      <td><?= h($m['fecha']) ?></td>
      <td><?= h($m['tipo']) ?></td>
      <td><?= h($m['desc']) ?></td>
      <td><?= h($m['persona']) ?></td>
      <td class="num"><?= $m['ingreso'] ? number_format($m['ingreso'], 2) : '' ?></td>
      <td class="num"><?= $m['egreso'] ? number_format($m['egreso'], 2) : '' ?></td>
      <td class="num"><?= number_format($m['saldo'], 2) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$filas && !$errores): ?><tr><td colspan="7" class="center">Sin movimientos</td></tr><?php endif; ?>
  </tbody>
  <?php if ($filas): ?>
  <tfoot><tr><th colspan="4">Totales</th><th class="num"><?= number_format($tot_ing, 2) ?></th><th class="num"><?= number_format($tot_egr, 2) ?></th><th class="num"><?= number_format($saldo_inicial + $tot_ing - $tot_egr, 2) ?></th></tr></tfoot>
  <?php endif; ?>
</table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

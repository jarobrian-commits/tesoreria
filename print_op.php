<?php
declare(strict_types=1);
require_once __DIR__ . "/api/db.php";

$id_op = (int)($_GET["id_op"] ?? 0);
if ($id_op <= 0) { http_response_code(400); echo "Falta id_op"; exit; }

$pdo = db();

$st = $pdo->prepare("
  SELECT o.id_op, o.fecha_op, o.total, o.importe_pago, o.estado, o.medio_pago, o.observacion,
         p.razon_social, p.cuit
  FROM op_cabecera o
  JOIN proveedores p ON p.id_proveedor = o.id_proveedor
  WHERE o.id_op = ?
");
$st->execute([$id_op]);
$op = $st->fetch();
if (!$op) { http_response_code(404); echo "OP no encontrada"; exit; }

$st = $pdo->prepare("
  SELECT f.tipo, f.tipo_cbte, f.numero, f.fecha_emision, f.fecha_vencimiento, d.importe_imputado
  FROM op_detalle d
  JOIN facturas_proveedor f ON f.id_factura = d.id_factura
  WHERE d.id_op = ?
  ORDER BY f.fecha_emision, f.numero
");
$st->execute([$id_op]);
$items = $st->fetchAll();

// Pagos (op_pagos)
$st = $pdo->prepare("
  SELECT id_op_pago, medio_pago, importe, fecha_pago, detalle, banco, nro_cheque, fecha_cheque, fecha_vto
  FROM op_pagos
  WHERE id_op = ?
  ORDER BY id_op_pago
");
$st->execute([$id_op]);
$pagos = $st->fetchAll(PDO::FETCH_ASSOC);

// Retenciones (op_retenciones)
$st = $pdo->prepare("
  SELECT id_op_retencion, tipo_retencion, base_calculo, porcentaje, importe, detalle
  FROM op_retenciones
  WHERE id_op = ?
  ORDER BY id_op_retencion
");
$st->execute([$id_op]);
$retenciones = $st->fetchAll(PDO::FETCH_ASSOC);

$totalRetenciones = 0.0;
foreach ($retenciones as $r) {
  $totalRetenciones += (float)($r["importe"] ?? 0);
}

$totalPagado = 0.0;
foreach ($pagos as $p) { $totalPagado += (float)($p['importe'] ?? 0); }

$totalNeto = (float)($op['total'] ?? 0);
$saldo = $totalNeto - $totalRetenciones - $totalPagado;

function money($n){ return number_format((float)$n, 2, ",", "."); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <title>OP #<?= htmlspecialchars((string)$op["id_op"]) ?></title>
  <style>
    body{font-family:Arial, sans-serif; margin:24px; color:#111}
    h1{margin:0 0 6px}
    .muted{color:#555}
    .box{border:1px solid #bbb; padding:12px; border-radius:8px; margin:12px 0}
    table{width:100%; border-collapse:collapse; margin-top:10px}
    th,td{border-bottom:1px solid #ddd; padding:8px 6px; font-size:13px}
    th{text-align:left; background:#f6f6f6}
    .right{text-align:right}
    @media print { .no-print{display:none} }
  </style>
</head>
<body>
  <div class="no-print" style="margin-bottom:12px;">
    <button onclick="window.print()">Imprimir</button>
  </div>

  <h1>Orden de Pago #<?= htmlspecialchars((string)$op["id_op"]) ?></h1>
  <div class="muted">
    Fecha: <?= htmlspecialchars((string)$op["fecha_op"]) ?> |
    Estado: <?= htmlspecialchars((string)$op["estado"]) ?> |
    Medio: <?= htmlspecialchars((string)$op["medio_pago"]) ?>
  </div>

  <div class="box" style="display:flex; justify-content:space-between; gap:20px;">
    <div><span class="muted">Total facturas (neto)</span><br><b>$<?= money($totalNeto) ?></b></div>
    <div><span class="muted">Total retenciones</span><br><b>$<?= money($totalRetenciones) ?></b></div>
    <div><span class="muted">Total pagado</span><br><b>$<?= money($totalPagado) ?></b></div>
    <div>
      <span class="muted">Saldo (DEBE/HABER)</span><br>
      <b>$<?= money($saldo) ?></b>
      <div class="muted" style="font-size:12px; margin-top:4px;">
        <?php if (abs($saldo) < 0.01): ?>Saldo 0
        <?php elseif ($saldo > 0): ?>DEBE (falta pagar)
        <?php else: ?>HABER (queda crédito)
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="box">
    <b>Proveedor:</b> <?= htmlspecialchars((string)$op["razon_social"]) ?><br>
    <b>CUIT:</b> <?= htmlspecialchars((string)$op["cuit"]) ?><br>
    <?php if (!empty($op["observacion"])): ?>
      <b>Obs:</b> <?= nl2br(htmlspecialchars((string)$op["observacion"])) ?><br>
    <?php endif; ?>
  </div>

  <table>
    <thead>
      <tr>
        <th>Tipo</th>
        <th>Comprobante</th>
        <th>Emisión</th>
        <th>Venc.</th>
        <th class="right">Importe</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($items as $it): ?>
        <tr>
          <td><?= htmlspecialchars((string)$it["tipo"]) ?> <?= htmlspecialchars((string)($it["tipo_cbte"] ?? "")) ?></td>
          <td><?= htmlspecialchars((string)$it["numero"]) ?></td>
          <td><?= htmlspecialchars((string)($it["fecha_emision"] ?? "")) ?></td>
          <td><?= htmlspecialchars((string)($it["fecha_vencimiento"] ?? "")) ?></td>
          <td class="right"><?= money($it["importe_imputado"]) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="4" class="right"><b>Total facturas (neto)</b></td>
        <td class="right"><b><?= money($op["total"]) ?></b></td>
      </tr>
    </tfoot>
  </table>
  <h3 style="margin-top:18px;">Retenciones</h3>
<table>
  <thead>
    <tr>
      <th>Tipo</th>
      <th>Detalle</th>
      <th class="right">Base</th>
      <th class="right">%</th>
      <th class="right">Importe</th>
    </tr>
  </thead>
  <tbody>
    <?php if (count($retenciones) === 0): ?>
      <tr><td colspan="5" class="muted">Sin retenciones aplicadas</td></tr>
    <?php else: ?>
      <?php foreach($retenciones as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string)($r["tipo_retencion"] ?? "")) ?></td>
          <td><?= htmlspecialchars((string)($r["detalle"] ?? "")) ?></td>
          <td class="right"><?= money($r["base_calculo"] ?? 0) ?></td>
          <td class="right">
            <?= ($r["porcentaje"] !== null && $r["porcentaje"] !== "") ? money($r["porcentaje"]) : "" ?>
          </td>
          <td class="right"><?= money($r["importe"] ?? 0) ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="4" class="right"><b>Total retenciones</b></td>
      <td class="right"><b><?= money($totalRetenciones) ?></b></td>
    </tr>
  </tfoot>
</table>      
  <h3 style="margin-top:18px;">Pagos</h3>
  <table>
    <thead>
      <tr>
        <th>Medio</th>
        <th>Detalle</th>
        <th>Fecha</th>
        <th class="right">Importe</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($pagos) === 0): ?>
        <tr><td colspan="4" class="muted">Sin pagos cargados</td></tr>
      <?php else: ?>
        <?php foreach($pagos as $p): ?>
          <tr>
            <td><?= htmlspecialchars((string)($p["medio_pago"] ?? "")) ?></td>
            <td>
              <?php
                $medio = strtoupper((string)($p['medio_pago'] ?? ''));
                if ($medio === 'CHEQUE') {
                  $parts = [];
                  if (!empty($p['banco'])) $parts[] = 'Banco: ' . $p['banco'];
                  if (!empty($p['nro_cheque'])) $parts[] = 'N° ' . $p['nro_cheque'];
                  if (!empty($p['fecha_cheque'])) $parts[] = 'Emisión: ' . $p['fecha_cheque'];
                  if (!empty($p['fecha_vto'])) $parts[] = 'Vto: ' . $p['fecha_vto'];
                  echo htmlspecialchars(implode(' | ', $parts));
                } else {
                  echo htmlspecialchars((string)($p['detalle'] ?? ''));
                }
              ?>
            </td>
            <td><?= htmlspecialchars((string)($p["fecha_pago"] ?? "")) ?></td>
            <td class="right"><?= money($p["importe"] ?? 0) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="3" class="right"><b>Total pagado</b></td>
        <td class="right"><b><?= money($totalPagado) ?></b></td>
      </tr>
      <tr>
        <td colspan="3" class="right"><b>Saldo (DEBE/HABER)</b></td>
        <td class="right"><b><?= money($saldo) ?></b></td>
      </tr>
    </tfoot>
  </table>

  <div style="margin-top:48px; display:flex; gap:40px;">
    <div style="flex:1; border-top:1px solid #888; padding-top:6px;">Firma Tesorería</div>
    <div style="flex:1; border-top:1px solid #888; padding-top:6px;">Aprobación</div>
  </div>
</body>
</html>

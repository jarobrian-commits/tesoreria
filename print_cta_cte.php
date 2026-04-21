<?php
require_once __DIR__ . "/api/db.php";

$pdo = db();

$idp = (int)($_GET["id_proveedor"] ?? 0);
if ($idp <= 0) die("Proveedor inválido");

$desde = trim($_GET["desde"] ?? "");
$hasta = trim($_GET["hasta"] ?? "");

$desdeSql = null;
$hastaSql = null;

if ($desde !== "") {
  $dt = DateTime::createFromFormat("Y-m-d", $desde);
  if (!$dt || $dt->format("Y-m-d") !== $desde) die("Fecha desde inválida");
  $desdeSql = $desde;
}

if ($hasta !== "") {
  $dt = DateTime::createFromFormat("Y-m-d", $hasta);
  if (!$dt || $dt->format("Y-m-d") !== $hasta) die("Fecha hasta inválida");
  $hastaSql = $hasta;
}

$st = $pdo->prepare("SELECT razon_social, cuit FROM proveedores WHERE id_proveedor=?");
$st->execute([$idp]);
$prov = $st->fetch(PDO::FETCH_ASSOC);
if (!$prov) die("Proveedor no existe");

/* =========================
   FILTROS
========================= */

$whereFcNd = " fp.id_proveedor = ? AND fp.estado <> 'ANULADA' AND fp.tipo IN ('FC','ND') ";
$paramsFcNd = [$idp];

if ($desdeSql) {
  $whereFcNd .= " AND COALESCE(fp.fecha_emision, DATE(fp.fecha_carga)) >= ? ";
  $paramsFcNd[] = $desdeSql;
}
if ($hastaSql) {
  $whereFcNd .= " AND COALESCE(fp.fecha_emision, DATE(fp.fecha_carga)) <= ? ";
  $paramsFcNd[] = $hastaSql;
}

$whereNc = " fp.id_proveedor = ? AND fp.estado <> 'ANULADA' AND fp.tipo = 'NC' ";
$paramsNc = [$idp];

if ($desdeSql) {
  $whereNc .= " AND COALESCE(fp.fecha_emision, DATE(fp.fecha_carga)) >= ? ";
  $paramsNc[] = $desdeSql;
}
if ($hastaSql) {
  $whereNc .= " AND COALESCE(fp.fecha_emision, DATE(fp.fecha_carga)) <= ? ";
  $paramsNc[] = $hastaSql;
}

$wherePagos = " oc.id_proveedor = ? AND oc.estado <> 'ANULADA' ";
$paramsPagos = [$idp];

if ($desdeSql) {
  $wherePagos .= " AND p.fecha_pago >= ? ";
  $paramsPagos[] = $desdeSql;
}
if ($hastaSql) {
  $wherePagos .= " AND p.fecha_pago <= ? ";
  $paramsPagos[] = $hastaSql;
}

$whereRet = " oc.id_proveedor = ? AND oc.estado <> 'ANULADA' ";
$paramsRet = [$idp];

if ($desdeSql) {
  $whereRet .= " AND oc.fecha_op >= ? ";
  $paramsRet[] = $desdeSql;
}
if ($hastaSql) {
  $whereRet .= " AND oc.fecha_op <= ? ";
  $paramsRet[] = $hastaSql;
}

/* =========================
   SQL MOVIMIENTOS
========================= */

$sql = "
  SELECT fecha, tipo, referencia, detalle, debe, haber
  FROM (

    SELECT
      COALESCE(fp.fecha_emision, DATE(fp.fecha_carga)) AS fecha,
      CONCAT(fp.tipo,' ',fp.tipo_cbte) AS tipo,
      fp.numero AS referencia,
      CONCAT('Factura ', fp.numero) AS detalle,
      ABS(fp.importe_total) AS debe,
      0.00 AS haber
    FROM facturas_proveedor fp
    WHERE $whereFcNd

    UNION ALL

    SELECT
      COALESCE(fp.fecha_emision, DATE(fp.fecha_carga)) AS fecha,
      CONCAT(fp.tipo,' ',fp.tipo_cbte) AS tipo,
      fp.numero AS referencia,
      CONCAT('Nota crédito ', fp.numero) AS detalle,
      0.00 AS debe,
      ABS(fp.importe_total) AS haber
    FROM facturas_proveedor fp
    WHERE $whereNc

    UNION ALL

    SELECT
      p.fecha_pago AS fecha,
      'PAGO' AS tipo,
      CONCAT('OP#', p.id_op) AS referencia,
      CONCAT(
        p.medio_pago,
        IFNULL(CONCAT(' - ', p.detalle), ''),
        IFNULL(CONCAT(' - Banco: ', p.banco), ''),
        IFNULL(CONCAT(' - Chq: ', p.nro_cheque), '')
      ) AS detalle,
      0.00 AS debe,
      ABS(p.importe) AS haber
    FROM op_pagos p
    JOIN op_cabecera oc ON oc.id_op = p.id_op
    WHERE $wherePagos

    UNION ALL

    SELECT
      oc.fecha_op AS fecha,
      'RETENCION' AS tipo,
      CONCAT('OP#', r.id_op) AS referencia,
      CONCAT(
        r.tipo_retencion,
        IFNULL(CONCAT(' - ', r.detalle), '')
      ) AS detalle,
      0.00 AS debe,
      ABS(r.importe) AS haber
    FROM op_retenciones r
    JOIN op_cabecera oc ON oc.id_op = r.id_op
    WHERE $whereRet

  ) x
  ORDER BY fecha, referencia
";

$params = array_merge($paramsFcNd, $paramsNc, $paramsPagos, $paramsRet);

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

function money($n){
  return number_format((float)$n, 2, ",", ".");
}

$saldo = 0.0;
$totDebe = 0.0;
$totHaber = 0.0;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Cuenta corriente proveedor</title>
  <style>
    body{
      font-family: Arial, Helvetica, sans-serif;
      font-size: 13px;
      padding: 16px;
      color:#111;
    }
    table{
      border-collapse: collapse;
      width: 100%;
    }
    th,td{
      border:1px solid #ccc;
      padding:6px;
    }
    th{
      background:#f3f3f3;
    }
    .r{
      text-align:right;
    }
    .muted{
      color:#666;
    }
    .barra-filtros{
      margin-bottom:12px;
    }
    @media print{
      .barra-filtros{ display:none; }
      body{ padding:0; }
    }
  </style>
</head>
<body>

  <form method="get" class="barra-filtros">
    <input type="hidden" name="id_proveedor" value="<?= $idp ?>">

    <button type="button" onclick="window.print()">Imprimir</button>

    <label>Desde:</label>
    <input type="date" name="desde" value="<?= htmlspecialchars($desdeSql ?? "") ?>">

    <label>Hasta:</label>
    <input type="date" name="hasta" value="<?= htmlspecialchars($hastaSql ?? "") ?>">

    <button type="submit">Filtrar</button>
  </form>

  <h2>Cuenta corriente</h2>
  <div><b><?= htmlspecialchars($prov["razon_social"]) ?></b> — CUIT <?= htmlspecialchars($prov["cuit"]) ?></div>

  <?php if ($desdeSql || $hastaSql): ?>
    <div class="muted">
      Período: <?= htmlspecialchars($desdeSql ?: "inicio") ?> a <?= htmlspecialchars($hastaSql ?: "hoy") ?>
    </div>
  <?php endif; ?>

  <div class="muted">Generado: <?= date("Y-m-d H:i") ?></div>
  <hr>

  <table>
    <thead>
      <tr>
        <th>Fecha</th>
        <th>Movimiento</th>
        <th>Detalle</th>
        <th class="r">Debe</th>
        <th class="r">Haber</th>
        <th class="r">Saldo</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $r):
        $debe = (float)$r["debe"];
        $haber = (float)$r["haber"];
        $saldo += ($debe - $haber);
        $totDebe += $debe;
        $totHaber += $haber;
      ?>
      <tr>
        <td><?= htmlspecialchars($r["fecha"]) ?></td>
        <td><?= htmlspecialchars($r["tipo"]) ?> <?= htmlspecialchars($r["referencia"]) ?></td>
        <td><?= htmlspecialchars($r["detalle"]) ?></td>
        <td class="r"><?= $debe ? money($debe) : "" ?></td>
        <td class="r"><?= $haber ? money($haber) : "" ?></td>
        <td class="r"><?= money($saldo) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <th colspan="3" class="r">Totales</th>
        <th class="r"><?= money($totDebe) ?></th>
        <th class="r"><?= money($totHaber) ?></th>
        <th class="r"><?= money($saldo) ?></th>
      </tr>
    </tfoot>
  </table>

</body>
</html>
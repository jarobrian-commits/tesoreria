<?php
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../seguridad.php';
require_once __DIR__ . '/../api/db.php';

$cliente = $_SESSION['cliente'];
$usuario = $_SESSION['usuario'];

verificar_tesoreria();

$pdo = db();

/* PAGOS REALIZADOS HOY */
$pagos_hoy = 0;

try {
  $pagos_hoy = $pdo->query("
    SELECT IFNULL(SUM(importe),0)
    FROM op_pagos
    WHERE fecha_pago >= CURDATE()
      AND fecha_pago < CURDATE() + INTERVAL 1 DAY
  ")->fetchColumn();
} catch (Exception $e) {
  $pagos_hoy = 0;
}

/* FACTURAS QUE VENCEN HOY */
$vencen_hoy = $pdo->query("
SELECT IFNULL(SUM(importe_total),0)
FROM facturas_proveedor
WHERE fecha_vencimiento = CURDATE()
AND estado <> 'PAGADA'
")->fetchColumn();

/* FACTURAS QUE VENCEN ESTE MES */
$vencen_mes = $pdo->query("
SELECT IFNULL(SUM(importe_total),0)
FROM facturas_proveedor
WHERE MONTH(fecha_vencimiento) = MONTH(CURDATE())
AND YEAR(fecha_vencimiento) = YEAR(CURDATE())
AND estado <> 'PAGADA'
")->fetchColumn();

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Tesorería - Proveedores / Facturas / OP</title>
  

</head>
<body>
  <style>

body{
    font-family: Arial, Helvetica, sans-serif;
    background:#f5f7fb;
}

/* PANEL FINANCIERO */

.dashboard{
    display:flex;
    gap:20px;
    margin-bottom:25px;
}

.card{
    flex:1;
    background:white;
    padding:20px;
    border-radius:8px;
    box-shadow:0 2px 6px rgba(0,0,0,0.1);
}

.card h3{
    margin:0;
    font-size:16px;
    color:#666;
}

.card .valor{
    font-size:28px;
    font-weight:bold;
    margin-top:8px;
}

/* PROVEEDORES */

.proveedores-box{
    background:white;
    padding:20px;
    border-radius:8px;
    box-shadow:0 2px 6px rgba(0,0,0,0.1);
}

.proveedores-scroll{
    max-height:420px;
    overflow-y:auto;
    margin-top:10px;
}

/* TABLA */

table{
    width:100%;
    border-collapse:collapse;
}

th{
    text-align:left;
    background:#f0f3f8;
    padding:10px;
}

td{
    padding:10px;
    border-bottom:1px solid #eee;
}

/* BOTONES */

.btn{
    padding:6px 10px;
    border:none;
    border-radius:5px;
    cursor:pointer;
}

.btn-primary{
    background:#2563eb;
    color:white;
}

.btn-edit{
    background:#10b981;
    color:white;
}

/* RESPONSIVE */

@media (max-width:800px){

.dashboard{
    flex-direction:column;
}

}

</style>

<header class="topbar">
  <div class="brand">Tesorería</div>

  <nav class="tabs">
    <button class="tab active" data-tab="proveedores">Proveedores</button>
    <button class="tab" data-tab="facturas">Facturas</button>
    <button class="tab" data-tab="op">Órdenes de Pago</button>
  </nav>

  <a href="/menu.php?cliente=<?php echo urlencode($cliente); ?>" class="btn ghost">
    ← Menú principal
  </a>
</header>

<main class="container">

  <h2>Panel Financiero</h2>

  <div class="dashboard">

    <div class="card">
      <h3>Órdenes de Pago realizadas hoy</h3>
      <div class="valor">
        $<?= number_format($pagos_hoy,2,",",".") ?>
      </div>
    </div>

    <div class="card">
      <h3>Facturas que vencen hoy</h3>
      <div class="valor">
        $<?= number_format($vencen_hoy,2,",",".") ?>
      </div>
    </div>

    <div class="card">
      <h3>Facturas que vencen este mes</h3>
      <div class="valor">
        $<?= number_format($vencen_mes,2,",",".") ?>
      </div>
    </div>

  </div>

  <!-- PROVEEDORES -->
<section class="panel show" id="tab-proveedores">
<div class="panel-header">
        <h2>Proveedores</h2>
    <div class="row wrap">
  <input id="prov-q" class="input" placeholder="Buscar por CUIT o razón social">
  <select id="prov-estado" class="input" style="width:auto;">
    <option value="activos" selected>Activos</option>
    <option value="all">Todos</option>
    <option value="inactivos">Inactivos</option>
  </select>
  <button class="btn" id="prov-refresh">Buscar</button>
  <button class="btn" id="prov-export">Exportar Excel</button>
  <button class="btn primary" id="prov-new">Nuevo proveedor</button>
</div>
      <div class="card">
          <table class="table" id="prov-table">
          <thead>
  <tr>
    <th>ID</th>
    <th>Razón social</th>
    <th>CUIT</th>
    <th>IVA</th>
    <th>Email</th>
    <th>Teléfono</th>
    <th>Activo</th>
    <th class="right"></th>
  </tr>
</thead>

          <tbody></tbody>

        </table>
      </div>

            <!-- Modal proveedor -->
      <?php include __DIR__ . "/../partials/modal_proveedor.php"; ?>
    </section>

    <!-- FACTURAS -->
    <section class="panel" id="tab-facturas">
      <div class="panel-header">
        <h2>Facturas</h2>
        <div class="row wrap">
          <input id="fac-q" class="input" placeholder="Buscar CUIT / proveedor / nro">
          <select id="fac-estado" class="input">
            <option value="">(Estado: todos)</option>
            <option value="CARGADA">CARGADA</option>
            <option value="IMPUTADA">IMPUTADA</option>
          </select>
          <input id="fac-de" type="date" class="input" title="Desde emisión">
          <input id="fac-ha" type="date" class="input" title="Hasta emisión">
          <button class="btn" id="fac-refresh">Buscar</button>
          <button class="btn primary" id="fac-new">Nueva factura</button>
          <button class="btn" id="fac-export">Exportar Excel</button>

          <div class="fac-totalbox">
            <div class="muted">Total seleccionado</div>
            <div class="fac-totalval" id="fac-total-sel">0,00</div> 
          </div>
        </div>
      </div>

      <div class="card table-wrap">
        <table class="table" id="fac-table">
          <thead>
            <tr>
              <th class="right" style="width:40px;">
                <input type="checkbox" id="fac-chk-all" title="Seleccionar todas">
              </th>
              <th>Proveedor</th>
              <th>Tipo</th>
              <th>Cbte</th>
              <th>Factura</th>
              <th>Fecha ingreso</th>
              <th>Fecha emisión</th>
              <th>Fecha vencimiento</th>
              <th class="right">Importe</th>
              <th>Estado</th>
              <th>Nro OP</th>
              <th class="right" style="width:150px;">Acciones</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <!-- Modal factura -->
      <div class="modal" id="fac-modal">
        <div class="modal-content">
          <div class="modal-header">
            <h3 id="fac-modal-title">Nueva factura</h3>
            <button class="btn ghost" id="fac-close">✕</button>
          </div>

          <div class="modal-body">
            <input type="hidden" id="fac-idfactura" value="">

            <div class="grid3">
              <label>
                Proveedor *
                <select class="input" id="fac-idproveedor" required></select>
              </label>

              <label>
                Tipo *
                <select class="input" id="fac-tipo" required>
                  <option value="FC">FC - Factura</option>
                  <option value="NC">NC - Nota de Crédito</option>
                  <option value="ND">ND - Nota de Débito</option>
                </select>
              </label>

              <label>
                Tipo de comprobante *
                <select id="fac-tipo-cbte" class="input" required>
                  <option value="A">A - Responsable Inscripto</option>
                  <option value="B">B - Consumidor Final</option>
                  <option value="C">C - Exento</option>
                  <option value="NCA">NCA - Nota Crédito A</option>
                  <option value="NCB">NCB - Nota Crédito B</option>
                </select>
              </label>

              <label>
                Número *
                <input id="fac-numero" type="text" class="input"
                       placeholder="00000-00000000" maxlength="14" autocomplete="off" required>
              </label>

              <label>
                Fecha carga
                <input type="date" class="input" id="fac-fecha-carga">
              </label>

              <label>
                Fecha emisión *
                <input type="date" class="input" id="fac-fecha-emision" required>
              </label>

              <label>
                Fecha vencimiento
                <input type="date" class="input" id="fac-fecha-venc">
              </label>

              <label>
                Importe total *
                <input class="input" id="fac-importe" placeholder="0,00" required>
              </label>
              
              <label>
                Observación
                <input class="input" id="fac-obs" placeholder="Observaciones">
              </label>
              
              <label>
                PDF (opcional)
                <input type="file" id="fac-pdf" class="input" accept="application/pdf">
                <small class="muted" id="fac-pdf-info"></small>
              </label>
            </div>

            <!-- ITEMS FACTURA -->
            <div class="card mt-2">
              <div class="row" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <h3 style="margin:0;">Ítems de la factura *</h3>
                <button type="button" class="btn" id="fac-add-item">+ Agregar ítem</button>
              </div>

              <div style="overflow:auto; margin-top:10px;">
                <div class="table-wrap">
<table class="table" id="fac-items-table" style="min-width:980px;">
                  <thead>
                    <tr>
                      <th style="width:120px;">Código</th>
                      <th>Producto/Servicio *</th>
                      <th style="width:110px;" class="right">Cantidad *</th>
                      <th style="width:140px;" class="right">Precio Unit. *</th>
                      <th style="width:110px;" class="right">Bonifica %</th>
                      <th style="width:150px;" class="right">Imp. Bonif.</th>
                      <th style="width:140px;" class="right">Subtotal</th>
                      <th style="width:140px;" class="right">Total</th>
                      <th style="width:60px;"></th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
</div>
              </div>
              

              <div style="display:flex;justify-content:flex-end;gap:20px;margin-top:10px;">
                <div><b>Total ítems:</b> <span id="fac-items-total">0,00</span></div>
              </div>

              <div class="form-note">
                * Campos obligatorios. El Importe total debe coincidir con la suma de ítems.
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn" id="fac-cancel">Cancelar</button>
            <button class="btn primary" id="fac-save">Guardar factura</button>
          </div>
        </div>
      </div>
    </section>

    <!-- OP -->
        <section class="panel" id="tab-op">
      <div class="panel-header">
        <h2>Órdenes de Pago</h2>
        <div class="row wrap">
          <input id="op-q" class="input" placeholder="Buscar proveedor / CUIT / ID OP">
          <input id="op-desde" type="date" class="input" title="Desde fecha OP">
          <input id="op-hasta" type="date" class="input" title="Hasta fecha OP">
          <button class="btn" id="op-buscar">Buscar</button>
          <button class="btn ghost" id="op-refresh">Limpiar</button>
          <button class="btn primary" id="op-new">Nueva OP</button>
        </div>
      </div>

      <div class="card">
        <table class="table" id="op-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Fecha</th>
              <th>Proveedor</th>
              <th>CUIT</th>
              <th>Total OP</th>
              <th>Total pagado</th>
              <th>Estado</th>
              <th>Medio de pago</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </section>

    
  <!-- =========================
     MODAL CUENTA CORRIENTE PROVEEDOR
========================= -->
<div id="cta-modal" class="modal" style="display:none;">
  <div class="modal-content" style="max-width:1100px;">
    <div class="row" style="justify-content:space-between; align-items:center;">
      <h2 style="margin:0;">Cuenta corriente proveedor</h2>
      <button id="cta-close" class="btn ghost">Cerrar</button>
    </div>

    <div class="row" style="gap:12px; align-items:end; margin-top:10px;">
      <div style="flex:1;">
        <label>Proveedor</label>
        <div id="cta-proveedor" class="muted"></div>
      </div>
      <div>
        <label>Saldo final (Debe - Haber)</label>
        <div style="font-size:18px; font-weight:700;" id="cta-saldo-final">0,00</div>
      </div>
    </div>

    <div class="table-wrap">
<table class="table" id="cta-table" style="margin-top:12px;">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Tipo</th>
          <th>Referencia</th>
          <th>Descripción</th>
          <th class="right">Debe</th>
        <th class="right">Haber</th>
          <th class="right">Saldo</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
</div>
  </div>
</div>
</section>

<!-- CSS -->
<link rel="stylesheet" href="/assets/styles.css">
<!-- =========================
     VISOR PDF FACTURA
========================= -->
<div id="pdf-viewer-modal" class="modal" style="display:none;">
  <div class="modal-content" style="width:80%;height:90%;">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
      <h3>Factura PDF</h3>
      <button class="btn ghost" id="pdf-close">Cerrar</button>
    </div>

    <iframe id="pdf-frame"
      style="width:100%;height:90%;border:none;">
    </iframe>

  </div>
</div>

<!-- jQuery primero -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- core.js después -->
<script src="/assets/js/core.js"></script>

<!-- módulos -->
<script src="/assets/js/proveedores.js"></script>
<script src="/assets/js/facturas.js"></script>
<script src="/assets/js/op.js"></script>
<script>
$(document).ready(function () {

  const params = new URLSearchParams(window.location.search);
  const tab = params.get("tab");

  if (tab) {
    $(".tab").removeClass("active");
    $(".panel").removeClass("show");

    $(`.tab[data-tab="${tab}"]`).addClass("active");
    $(`#tab-${tab}`).addClass("show");
  }

});
</script>
</body>
</html>
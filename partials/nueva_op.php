<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Nueva OP</title>

  <link rel="stylesheet" href="/tesoreria/assets/styles.css">

  <style>
    .page-wrap{
      max-width: 1450px;
      margin: 60px auto 30px auto;
      padding: 0 24px;
    }

    .page-header{
      margin-bottom: 22px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
    }

    .page-header h2{
      margin:0;
    }

    .op-form-block{
      margin-bottom:14px;
      padding:14px;
      border-radius:10px;
      background: rgba(255,255,255,.03);
      border:1px solid rgba(255,255,255,.08);
    }

    .op-header-grid{
      display:grid;
      grid-template-columns: 1.4fr 1fr 1fr;
      gap:12px;
    }

    .op-header-grid label,
    .op-grid-wide label{
      display:flex;
      flex-direction:column;
      gap:4px;
      font-size:12px;
    }

    .op-header-grid .input,
    .op-grid-wide .input,
    .op-header-grid select,
    .op-grid-wide select{
      height:34px;
      min-height:34px;
      padding:6px 10px;
      font-size:13px;
    }

    .op-grid-wide{
      display:grid;
      grid-template-columns: repeat(4, 1fr);
      gap:10px;
    }

    .op-resumen{
      display:flex;
      justify-content:flex-end;
      gap:24px;
      margin-top:12px;
      flex-wrap:wrap;
    }

    .op-resumen div{
      min-width:160px;
      text-align:right;
    }

    .op-card{
      margin-top:14px;
      padding:12px;
      border:1px solid rgba(255,255,255,.08);
      border-radius:10px;
      background: rgba(255,255,255,.02);
    }

    .op-card-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      margin-bottom:10px;
      flex-wrap:wrap;
    }

    .op-filtros{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
      margin-bottom:10px;
    }

    .op-pagos-grid{
      display:grid;
      grid-template-columns: 1.3fr 1fr;
      gap:14px;
      align-items:start;
    }

    .page-footer{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:12px;
      margin-top:16px;
      flex-wrap:wrap;
    }

    @media (max-width: 980px){
      .op-header-grid,
      .op-grid-wide,
      .op-pagos-grid{
        grid-template-columns:1fr;
      }
    }
  </style>
</head>
<body>

<div style="display:flex; justify-content:space-between; margin-bottom:10px;">
  <a href="/tesoreria/menu.php">← Volver</a>
  <button onclick="window.close()">Cerrar</button>
</div>

<div class="page-wrap">

  <div class="page-header">
    <h2>Nueva Orden de Pago</h2>
    <div class="row wrap">
      <a href="/tesoreria/index.php?tab=op" class="btn" id="op-cancel-page">Cancelar</a>
      <button class="btn primary" id="op-save">Guardar OP</button>
    </div>
  </div>

  <div class="op-form-block">
    <div class="op-header-grid">
      <label>
        Proveedor *
        <select class="input need-proveedores" id="op-idproveedor" required></select>
      </label>

      <label>
        Fecha OP *
        <input type="date" class="input" id="op-fecha" required>
      </label>

      <label>
        Plazo acordado
        <input class="input" id="op-plazo-pago" readonly>
      </label>
    </div>

    <div style="margin-top:12px;">
      <label>
        Observación
        <input class="input" id="op-obs" placeholder="">
      </label>
    </div>
  </div>

  <div class="op-card">
    <div class="op-card-head">
      <h3 style="margin:0;">Retenciones</h3>
    </div>

    <div class="table-wrap">
      <table class="table" id="op-retenciones-table">
        <thead>
          <tr>
            <th>Tipo</th>
            <th class="right">Importe</th>
            <th class="center">Activa</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="3" class="center muted">Sin retenciones</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="op-resumen">
      <div><b>Total retenciones:</b> <span id="op-total-retenciones">0,00</span></div>
    </div>
  </div>

  <div class="op-card">
    <div class="op-filtros">
      <div class="row wrap">
        <input class="input" id="op-fac-q" placeholder="Buscar por número..." style="min-width:220px;">
        <button class="btn" id="op-fac-refresh">Buscar</button>
      </div>

      <div class="fac-totalbox">
        <div class="muted">Total OP (neto)</div>
        <div class="fac-totalval op-total-neto">0,00</div>
      </div>
    </div>

    <div class="table-wrap">
      <table class="table" id="op-facturas-table">
        <thead>
          <tr>
            <th style="width:40px"></th>
            <th>N° Factura</th>
            <th>Tipo</th>
            <th>Emisión</th>
            <th class="right">Importe</th>
            <th class="right">Importe OP</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

  <div class="op-card">
    <div class="op-card-head">
      <h3 style="margin:0;">Pagos</h3>
    </div>

    <div class="op-pagos-grid">
      <div>
        <div class="row wrap" style="gap:10px; align-items:end;">
          <div>
            <label>Medio</label>
            <select id="op-pago-medio" class="input right">
              <option value="EFECTIVO">EFECTIVO</option>
              <option value="CHEQUE">CHEQUE</option>
              <option value="TRANSFERENCIA">TRANSFERENCIA</option>
              <option value="OTRO">OTRO</option>
            </select>
          </div>

          <div>
            <label>Importe</label>
            <input id="op-pago-importe" class="input right" value="0,00" style="max-width:140px;">
          </div>

          <div id="op-otro-fields" style="display:none; flex:1;">
            <label>Detalle</label>
            <input id="op-pago-detalle" class="input" placeholder="Observación / referencia">
          </div>

          <div id="op-transf-fields" style="display:none; flex:1;">
            <label>Detalle transferencia</label>
            <input id="op-trx-detalle" class="input" placeholder="CBU / nro operación / etc">
          </div>
        </div>

        <div id="op-cheque-fields" class="row wrap" style="display:none; gap:10px; align-items:end; margin-top:10px;">
          <div>
            <label>Banco</label>
            <input id="op-chq-banco" class="input" style="max-width:160px;">
          </div>
          <div>
            <label>N° Cheque</label>
            <input id="op-chq-num" class="input" style="max-width:140px;">
          </div>
          <div>
            <label>Fecha emisión</label>
            <input id="op-chq-fecha" type="date" class="input" style="max-width:160px;">
          </div>
          <div>
            <label>Vencimiento</label>
            <input id="op-chq-vto" type="date" class="input" style="max-width:160px;">
          </div>
        </div>

        <div style="margin-top:10px;">
          <button id="op-pago-add" class="btn">Agregar pago</button>
        </div>
      </div>

      <div class="table-wrap">
        <table class="table" id="op-pagos-table">
          <thead>
            <tr>
              <th>Medio</th>
              <th>Detalle</th>
              <th class="right">Importe</th>
              <th class="right"></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <div class="op-resumen">
      <div><b>Total facturas (neto):</b> <span class="op-total-neto">0,00</span></div>
      <div><b>Total pagos:</b> <span id="op-total-pagado">0,00</span></div>
      <div><b>Saldo (DEBE/HABER):</b> <span id="op-saldo-cte">0,00</span></div>
    </div>

    <div id="op-msg" class="muted" style="margin-top:8px;"></div>

    <div class="form-note">
      * Seleccioná las facturas a imputar (opcional para pago a cuenta)
    </div>
  </div>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/tesoreria/assets/js/core.js"></script>
<script src="/tesoreria/assets/js/proveedores.js"></script>
<script src="/tesoreria/assets/js/op.js"></script>

<script>
$(document).ready(async function () {
  if (typeof initOPModal === "function") {
    initOPModal();
  }

  if (typeof fillProveedorSelects === "function") {
    await fillProveedorSelects();
  }

  await cargarPlazoPagoProveedor($("#op-idproveedor").val());
  await loadFacturasPendientesOP();
});
</script>

</body>
</html>
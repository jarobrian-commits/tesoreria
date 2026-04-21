<?php
require_once __DIR__ . '/api/auth.php';

$cliente = $_SESSION['cliente'];
$usuario = $_SESSION['usuario'];
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
    margin: 22px auto;
    padding: 0 18px 28px;
  }

  .page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:14px;
  }

  .page-header h2{
    margin:0;
    font-size:26px;
    line-height:1.1;
  }

  .op-card,
  .op-form-block{
    background:rgba(255,255,255,.03);
    border:1px solid rgba(255,255,255,.08);
    border-radius:12px;
    padding:12px;
  }

  .op-form-block{
    margin-bottom:14px;
  }

  .op-main-grid{
    display:grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap:14px;
    align-items:start;
  }

  .op-header-grid{
    display:grid;
    grid-template-columns: 1.5fr 1fr 1fr;
    gap:10px;
    align-items:end;
  }

  .op-header-grid label,
  .op-mini-label{
    display:flex;
    flex-direction:column;
    gap:4px;
    font-size:12px;
  }

  .op-header-grid .input,
  .op-header-grid select,
  .input-sm{
    height:32px;
    min-height:32px;
    padding:6px 10px;
    font-size:13px;
  }

  #op-obs{
    height:32px;
    min-height:32px;
  }

  .op-card-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-bottom:8px;
  }

  .op-card-head h3{
    margin:0;
    font-size:17px;
  }

  .op-side-stack{
    display:grid;
    gap:14px;
  }

  .op-filtros{
    display:flex;
    justify-content:space-between;
    align-items:end;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:8px;
  }

  .op-total-box{
    min-width:180px;
    text-align:right;
    padding:10px 12px;
    border:1px solid rgba(255,255,255,.08);
    border-radius:10px;
    background:rgba(255,255,255,.02);
  }

  .op-total-box .muted{
    font-size:12px;
  }

  .op-total-box .val{
    font-size:22px;
    font-weight:700;
    margin-top:2px;
  }

  .op-ret-resumen{
    display:flex;
    justify-content:flex-end;
    margin-top:8px;
    font-size:14px;
  }

  .table-wrap{
    overflow-x:auto;
  }

  #op-facturas-table th,
  #op-facturas-table td,
  #op-retenciones-table th,
  #op-retenciones-table td,
  #op-pagos-table th,
  #op-pagos-table td{
    padding:8px 10px;
    vertical-align:top;
  }

  .op-pagos-section{
    margin-top:14px;
  }

  .op-pagos-layout{
  display:grid;
  grid-template-columns: 390px 1fr;
  gap:14px;
  align-items:start;
}

  .op-pago-box,
  .op-pago-tabla-wrap{
    border:1px solid rgba(255,255,255,.08);
    border-radius:10px;
    background:rgba(255,255,255,.02);
    padding:10px;
  }

  .op-mini-grid{
    display:grid;
    grid-template-columns: 1fr 110px;
    gap:8px;
    align-items:end;
  }

  .op-mini-label span{
    font-size:12px;
    color:var(--muted);
  }

  .input-sm{
    font-size:13px !important;
  }

  .importe-op{
    text-align:right;
    font-weight:600;
  }

  .op-dyn-area{
    min-height:122px;
    margin-top:8px;
  }

  .op-dyn-block{
    width:100%;
  }

  .op-cheque-grid{
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap:8px;
  }

  .op-pago-actions{
    margin-top:10px;
  }

  #op-pagos-scroll{
    max-height:260px;
    overflow-y:auto;
    overflow-x:auto;
    scroll-behavior:smooth;
  }

  .op-resumen-pagos{
    margin-top:10px;
    padding-top:10px;
    border-top:1px solid rgba(255,255,255,.08);
    display:flex;
    gap:16px;
    flex-wrap:wrap;
    justify-content:flex-end;
    font-size:14px;
  }

  .op-msg-box{
    margin-top:8px;
    min-height:20px;
  }

  .op-footer-actions{
  position: sticky;
  bottom: 0;
  z-index: 50;

  background: linear-gradient(to top, rgba(10,15,30,0.95), rgba(10,15,30,0.8));
  backdrop-filter: blur(6px);

  border-top: 1px solid rgba(255,255,255,.08);
  padding: 10px 0;
  margin-top: 20px;
}

  @media (max-width: 1100px){
    .op-main-grid{
      grid-template-columns:1fr;
    }

    .op-pagos-layout{
      grid-template-columns:1fr;
    }
  }

  @media (max-width: 900px){
    .op-header-grid,
    .op-mini-grid,
    .op-cheque-grid{
      grid-template-columns:1fr;
    }

    .op-dyn-area{
      min-height:auto;
    }
  }

  .op-bottom-bar{
    position:sticky;
    bottom:0;
    z-index:100;
    margin-top:18px;
    background:linear-gradient(to top, rgba(8,12,24,.96), rgba(8,12,24,.88));
    backdrop-filter:blur(6px);
    border-top:1px solid rgba(255,255,255,.10);
    padding:10px 0;
  }

  .op-bottom-inner{
    max-width:1450px;
    margin:0 auto;
    padding:0 18px;
    display:flex;
  }

  .op-bottom-right{
    margin-left:auto;
    display:flex;
    gap:10px;
  }

.op-bottom-bar{
  position:sticky;
  bottom:0;
  z-index:100;
  margin-top:18px;

  background:linear-gradient(to top, rgba(8,12,24,.96), rgba(8,12,24,.88));
  backdrop-filter:blur(6px);

  border-top:1px solid rgba(255,255,255,.10);
  padding:10px 0;
}

.op-bottom-inner{
  max-width:1450px;
  margin:0 auto;
  padding:0 18px;
  text-align:right;
  display:flex;
  justify-content:flex-end;
  gap:10px;
}
  
</style>
  
</head>
<body>

<div class="page-wrap">

  <div class="page-header">
    <h2>Nueva Orden de Pago</h2>
    
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

  <div style="margin-top:10px;">
    <label>
      Observación
      <input class="input" id="op-obs">
    </label>
  </div>
</div>

<div class="op-main-grid">

  <div class="op-card">
    <div class="op-filtros">
      <div class="row wrap">
        <input class="input" id="op-fac-q" placeholder="Buscar por número..." style="min-width:220px;">
        <button class="btn" id="op-fac-refresh">Buscar</button>
      </div>

      <div class="op-total-box">
        <div class="muted">Total OP (neto)</div>
        <div class="val op-total-neto">0,00</div>
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

  <div class="op-side-stack" style="grid-column: span 2;">
    <div class="op-card">
      <div class="op-card-head">
        <h3>Retenciones</h3>
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

      <div class="op-ret-resumen">
        <div><b>Total retenciones:</b> <span id="op-total-retenciones">0,00</span></div>
      </div>
    </div>
  </div>

</div>

<div class="op-card op-pagos-section">
  <div class="op-card-head">
    <h3>Pagos</h3>
  </div>

  <div class="op-pagos-layout">
    <div class="op-pago-box">
      <div class="op-mini-grid">
        <label class="op-mini-label">
          <span>Medio</span>
          <select id="op-pago-medio" class="input input-sm">
            <option value="EFECTIVO">EFECTIVO</option>
            <option value="CHEQUE">CHEQUE</option>
            <option value="TRANSFERENCIA">TRANSFERENCIA</option>
            <option value="OTRO">OTRO</option>
          </select>
        </label>

        <label class="op-mini-label">
          <span>Importe</span>
          <input id="op-pago-importe" class="input input-sm right importe-op" value="0,00">
        </label>
      </div>

      <div class="op-dyn-area">
        <div id="op-otro-fields" class="op-dyn-block" style="display:none;">
          <label class="op-mini-label">
            <span>Detalle</span>
            <input id="op-pago-detalle" class="input input-sm" placeholder="Observación / referencia">
          </label>
        </div>

        <div id="op-transf-fields" class="op-dyn-block" style="display:none;">
          <label class="op-mini-label">
            <span>Detalle transferencia</span>
            <input id="op-trx-detalle" class="input input-sm" placeholder="CBU / nro operación / etc.">
          </label>
        </div>

        <div id="op-cheque-fields" class="op-dyn-block" style="display:none;">
          <div class="op-cheque-grid">
            <label class="op-mini-label">
              <span>Banco</span>
              <input id="op-chq-banco" class="input input-sm">
            </label>

            <label class="op-mini-label">
              <span>N° Cheque</span>
              <input id="op-chq-num" class="input input-sm">
            </label>

            <label class="op-mini-label">
              <span>Fecha emisión</span>
              <input id="op-chq-fecha" type="date" class="input input-sm">
            </label>

            <label class="op-mini-label">
              <span>Vencimiento</span>
              <input id="op-chq-vto" type="date" class="input input-sm">
            </label>
          </div>
        </div>
      </div>

      <div class="op-pago-actions">
        <button id="op-pago-add" class="btn">Agregar pago</button>
      </div>
    </div>

    <div class="op-pago-tabla-wrap">
      <div class="table-wrap" id="op-pagos-scroll">
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

      <div class="op-resumen-pagos">
        <div><b>Total facturas (neto):</b> <span class="op-total-neto">0,00</span></div>
        <div><b>Total pagos:</b> <span id="op-total-pagado">0,00</span></div>
        <div><b>Saldo (DEBE/HABER):</b> <span id="op-saldo-cte">0,00</span></div>
      </div>

      <div id="op-msg" class="muted op-msg-box"></div>
    </div>
  </div>

  <div class="form-note">
    * Seleccioná las facturas a imputar (opcional para pago a cuenta)
  </div>
</div>
<div class="op-bottom-bar">
  <div class="op-bottom-inner">

    <div class="op-bottom-right">
      <a href="/tesoreria/tesoreria/index.php?cliente=<?= urlencode($cliente) ?>&tab=op" class="btn">
        Cancelar
      </a>

      <button type="button" class="btn primary" id="op-save"> Guardar OP
</button>
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
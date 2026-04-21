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
  <title>Nueva factura</title>

  <link rel="stylesheet" href="/tesoreria/assets/styles.css">

  <style>
.header-compact{
  display:grid;
  grid-template-columns: 2fr 1fr 1.5fr 1.5fr;
  gap:10px;
  margin-bottom:14px;
}
.page-wrap{
  max-width: 1400px;
  margin: 60px auto 30px auto;  /* 👈 esto la baja */
  padding: 0 24px;
}

.header-compact label,
.grid-wide label{
  display:flex;
  flex-direction:column;
  gap:4px;
  font-size:12px;
}
.page-header{
  margin-bottom: 24px;
}

.header-compact{
  margin-top:20px;
}

.header-compact .input,
.grid-wide .input,
.header-compact select,
.grid-wide select{
  height:34px;
  min-height:34px;
  padding:6px 10px;
  font-size:13px;
}

.form-block{
  margin-bottom:14px;
  padding:12px;
  border-radius:10px;
  background: rgba(255,255,255,0.03);
  border:1px solid rgba(255,255,255,0.08);
}

.grid-wide{
  display:grid;
  grid-template-columns: repeat(4, 1fr);
  gap:10px;
}

.items-card{
  margin-top:14px;
  padding:12px;
  border:1px solid rgba(255,255,255,.08);
  border-radius:10px;
  background: rgba(255,255,255,.02);
}

.items-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  margin-bottom:10px;
  flex-wrap:wrap;
}

.page-footer{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  margin-top:14px;
  flex-wrap:wrap;
}
  </style>
</head>
<body>

<div style="display:flex; justify-content:space-between; margin-bottom:10px;">
  <a href="/tesoreria/menu.php">← Volver</a>
  <button onclick="window.close()">Cerrar</button>
</div>

<div class="header-compact">

  <label>
    Proveedor *
    <select class="input" id="fac-idproveedor" required></select>
  </label>

  <label>
    Tipo *
    <select class="input" id="fac-tipo" required>
      <option value="FC">FC</option>
      <option value="NC">NC</option>
      <option value="ND">ND</option>
    </select>
  </label>

  <label>
    Tipo comprobante *
    <select id="fac-tipo-cbte" class="input" required>
      <option value="A">A</option>
      <option value="B">B</option>
      <option value="C">C</option>
    </select>
  </label>

  <label>
    Número *
    <input id="fac-numero" type="text" class="input"
           placeholder="00000-00000000" maxlength="14" required>
  </label>

</div>

<div class="form-block">
  <div class="grid-wide">

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
      <input id="fac-importe" type="text" class="input" autocomplete="off" inputmode="decimal">
    </label>

  </div>
</div>

<div class="form-block">
  <div class="grid-wide">

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
</div>

<div class="items-card">
  <div class="items-head">
    <h3 style="margin:0;">Ítems de la factura *</h3>
    <button type="button" class="btn" id="fac-add-item">+ Agregar ítem</button>
  </div>

  <div class="table-wrap">
    <table class="table" id="fac-items-table">
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

  <div style="display:flex;justify-content:flex-end;gap:20px;margin-top:10px;">
    <div><b>Total ítems:</b> <span id="fac-items-total">0,00</span></div>
  </div>

  <div class="form-note">
    * Campos obligatorios. El Importe total debe coincidir con la suma de ítems.
  </div>
</div>

<div class="page-footer">
  <a href="../tesoreria/index.php?tab=facturas" class="btn" id="fac-cancel">Cancelar</a>

  <div class="right-box">
    <button class="btn primary" id="fac-save">Guardar factura</button>
  </div>
</div>

      <div style="display:flex;justify-content:flex-end;gap:20px;margin-top:10px;">
        <div><b>Total ítems:</b> <span id="fac-items-total">0,00</span></div>
      </div>

     
    </div>

    

  </div>
</div>

<script src="/tesoreria/assets/js/jquery-3.7.1.min.js"></script>
<script src="/tesoreria/assets/js/core.js?v=2"></script>
<script src="/tesoreria/assets/js/proveedores.js?v=2"></script>
<script src="/tesoreria/assets/js/facturas.js?v=2"></script>

<script>
$(document).ready(function () {
  if (typeof resetFacturaModalNueva === "function") {
    resetFacturaModalNueva();
  }

  if (typeof fillProveedorSelects === "function") {
    fillProveedorSelects();
  }
});
</script>
</body>
</html>
<?php
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../seguridad.php';

$cliente = $_SESSION['cliente'];
$usuario = $_SESSION['usuario'];

verificar_caja();
?>

<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Caja chica</title>

<link rel="stylesheet" href="../assets/styles.css">

<style>
  .cc-grid { display:grid; grid-template-columns: 380px 1fr; gap:16px; }
  .cc-card { border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:16px; background:rgba(255,255,255,.02); }
  .cc-row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .cc-grow { flex: 1 1 auto; }
  .cc-kv { display:grid; grid-template-columns: 150px 1fr; gap:6px 10px; font-size:14px; }
  .cc-muted { opacity:.7; }
  .cc-right { text-align:right; }
  .cc-table { width:100%; border-collapse:collapse; font-size:14px; }
  .cc-table th, .cc-table td { padding:8px; border-bottom:1px solid rgba(255,255,255,.08); }
  .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; border:1px solid rgba(255,255,255,.15); }
  .cc-neg { color:#ff6b6b; }
  .cc-pos { color:#6bff9c; }
</style>
</head>

<body>

<div class="container">

  <!-- HEADER -->
  <div class="cc-row" style="justify-content:space-between; margin-bottom:16px;">
    <div>
      <h2 style="margin:0;">Caja chica</h2>
      <div id="msg" class="cc-muted"></div>
    </div>
    <div>
      <a href="/menu.php" class="btn ghost">← Volver al menú</a>
      

<button id="btn-historial" class="btn ghost">
Histórico de cajas
</button>
    </div>
  </div>

  <div class="cc-grid">

    <!-- PANEL IZQUIERDO -->
    <div class="cc-card">

      <h3 style="margin-top:0;">Caja actual</h3>

      <div class="cc-kv">
        <div>Estado:</div> <div id="cc-estado">—</div>
        <div>Fecha apertura:</div> <div id="cc-fecha-ap">—</div>
        <div>Monto inicial:</div> <div>$ <span id="cc-monto-inicial-view">0.00</span></div>
        <div>Ingresos:</div> <div>$ <span id="cc-ing">0.00</span></div>
        <div>Gastos:</div> <div>$ <span id="cc-gastos">0.00</span></div>
        <div><strong>Saldo calculado:</strong></div>
        <div><strong id="saldo">$ 0.00</strong></div>
      </div>

      <hr>

      <h4>Abrir caja</h4>
      <div class="cc-row">
        <input type="number" id="cc-monto-inicial" step="0.01" placeholder="Monto inicial">
        <button id="btn-abrir" class="btn">Abrir caja</button>
      </div>

      <hr>

      <h4>Cerrar caja</h4>

      <div class="cc-row">
        <input type="number" id="cierre-declarado" step="0.01" placeholder="Monto contado">
      </div>

      <div style="margin-top:10px;">
        Diferencia: <strong id="cc-diferencia">$ 0.00</strong>
      </div>

      <div style="margin-top:10px;">
      <button id="btn-cerrar" class="btn">Cerrar caja</button>
      <button id="btn-reset-caja" class="btn ghost" type="button">Poner en cero</button>
      </div>

      

    </div>

          
    <!-- PANEL DERECHO -->
    <div class="cc-card">

      <h3 style="margin-top:0;">Agregar movimiento</h3>

      <div class="cc-row">
        <select id="mov-tipo">
          <option value="GASTO">GASTO</option>
          <option value="INGRESO">INGRESO</option>
        </select>

        <input type="text" id="mov-concepto" class="cc-grow" placeholder="Concepto">

        <input type="number" id="mov-importe" step="0.01" placeholder="Importe">

        <input type="text" id="mov-ncomp" placeholder="Nro comprobante">

        <select id="mov-tcomp">
          <option value="TICKET">TICKET</option>
          <option value="FACT">FACT</option>
          <option value="RECIBO">RECIBO</option>
        </select>

        <button id="btn-agregar" class="btn">Guardar</button>
      </div>

      

      <hr>

      <table class="table" id="mov-table">
<thead>
<tr>
<th>Fecha</th>
<th>Tipo</th>
<th>Concepto</th>
<th>Importe</th>
<th>Comprobante</th>
</tr>
</thead>

<tbody id="mov-tbody">
</tbody>
</table>
    </div>

  </div>
<hr>
<!-- HISTORICO -->
      <div id="historial-box" class="card">
      

      <h3>Histórico de cajas</h3>

     <div class="historial-scroll">
<table class="table">

      <thead>
      <tr>
      <th>ID</th>
      <th>Apertura</th>
      <th>Cierre</th>
      <th>Inicial</th>
      <th>Final</th>
      <th>Diferencia</th>
      <th>Acción</th>
      </tr>
      </thead>

      <tbody id="hist-tbody"></tbody>

      </table>

      </div>




<script>
  window.CAJA_AJAX_URL = 'ajax_caja_chica.php?cliente=<?php echo urlencode($cliente); ?>';
</script>

<script src="../assets/js/caja_chica.js"></script>

</body>
</html>
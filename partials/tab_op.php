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

      <a class="btn primary"
         href="/tesoreria/op_nueva.php?cliente=<?= urlencode($cliente ?? ($_SESSION["cliente"] ?? "demo")) ?>"
         target="_blank"
         rel="noopener noreferrer">
        Nueva OP
      </a>
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
          <th class="right">Total</th>
          <th class="right">Importe pago</th>
          <th>Estado</th>
          <th>Medio pago</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>

  <?php include __DIR__ . "/modal_op.php"; ?>
</section>

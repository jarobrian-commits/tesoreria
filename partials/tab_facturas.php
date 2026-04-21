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
          <input id="fac-de" type="date" class="input" title="Desde vencimiento">
          <input id="fac-ha" type="date" class="input" title="Hasta vencimiento">
          <button class="btn" id="fac-refresh">Buscar</button>
          <a class="btn primary"
   href="/tesoreria/factura_nueva.php?cliente=<?= urlencode($cliente ?? ($_SESSION["cliente"] ?? "demo")) ?>"
   target="_blank"
   rel="noopener noreferrer">
  Nueva factura
</a>
          
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
      <?php include __DIR__ . "/modal_factura.php"; ?>
    </section>

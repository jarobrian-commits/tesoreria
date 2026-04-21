<!-- PROVEEDORES -->
    <section class="panel show" id="tab-proveedores">
      <div class="panel-header">
        <h2>Proveedores</h2>
       <div class="row">
        <input id="prov-q" class="input" placeholder="Buscar por CUIT o razón social">
        <button class="btn" id="prov-refresh">Buscar</button>

        <select id="prov-estado" class="input" style="width:auto">
          <option value="activos" selected>Activos</option>
          <option value="all">Todos</option>
          <option value="inactivos">Inactivos</option>
        </select>

  <button class="btn primary" id="prov-new">Nuevo proveedor</button>
</div>

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
      <?php include __DIR__ . "/modal_proveedor.php"; ?>
    </section>

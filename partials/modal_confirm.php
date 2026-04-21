<!-- Modal confirmación -->
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

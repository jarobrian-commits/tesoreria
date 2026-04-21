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

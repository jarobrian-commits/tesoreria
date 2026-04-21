<!-- Modal OP -->
<div class="modal" id="op-modal">
        <div class="modal-content">
          <div class="modal-header">
            <h3>Nueva Orden de Pago</h3>
            <button class="btn ghost" id="op-close">✕</button>
          </div>

          <div class="modal-body">
            <div class="grid3">
              <label>
                Proveedor *
                <select class="input" id="op-idproveedor" required></select>
              </label>

              <label>
                Fecha OP *
                <input type="date" class="input" id="op-fecha" required>
              </label>

              <!--<label>
                Medio de pago *
                <select class="input" id="op-mediopago" required>
                  <option value="TRANSFERENCIA">TRANSFERENCIA</option>
                  <option value="CHEQUE">CHEQUE</option>
                  <option value="EFECTIVO">EFECTIVO</option>
                  <option value="MIXTO">MIXTO</option>
                  <option value="OTRO">OTRO</option>
                </select>
              </label>-->

              <!--<label>
                Importe pago *
                <input class="input right" id="op-importe-pago" placeholder="0,00" required>
              </label>-->

              <label>
                Plazo acordado
                <input class="input" id="op-plazo-pago" readonly>
              </label>

              <label class="grow" style="grid-column: span 2;">
                Observación
                <input class="input" id="op-obs" placeholder="">
              </label>
            </div>

            <div class="card" style="margin-top:10px;">
          <h4>Retenciones</h4>

          <table class="table" id="op-retenciones-table">
            <thead>
              <tr>
                <th>Tipo</th>
                <th class="right">Importe</th>
                <th class="center">Activa</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="3" class="center muted">Sin retenciones</td></tr>
            </tbody>
          </table>

          <div class="row space" style="margin-top:10px;">
            <div>Total retenciones:</div>
            <div id="op-total-retenciones">0,00</div>
          </div>
        </div>

            <div class="card" style="margin-top:12px;">
              <div class="row space">
                <div class="row">
                  <input class="input" id="op-fac-q" placeholder="Buscar por número..." style="min-width:220px;">
                  <button class="btn" id="op-fac-refresh">Buscar</button>
                </div>

                <div class="fac-totalbox">
                  <div class="muted">Total OP (neto)</div>
                  <div class="fac-totalval op-total-op">0,00</div>
                </div>
              </div>

           <div class="table-wrap">
<table class="table" id="op-facturas-table" style="margin-top:10px;">
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

        <h3>Pagos</h3>

        <div class="row" style="gap:10px; align-items:end;">
          <div>
            <label>Medio</label>
            <select id="op-pago-medio"class="input right">
              <option value="EFECTIVO">EFECTIVO</option>
              <option value="CHEQUE">CHEQUE</option>
              <option value="TRANSFERENCIA">TRANSFERENCIA</option>
            </select>
          </div>

          <div>
            <label>Importe</label>
            <input id="op-pago-importe" class="input right" value="0,00" style="max-width:140px;">
          </div>

          <div id="op-cheque-fields" class="row" style="display:none; gap:10px; align-items:end;">
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

          <div id="op-transf-fields" style="display:none; flex:1;">
            <label>Detalle transferencia</label>
            <input id="op-trx-detalle" class="input" placeholder="CBU / nro operación / etc">
          </div>

          <button id="op-pago-add" class="btn">Agregar pago</button>
        </div>

        <div class="table-wrap">
<table class="table" id="op-pagos-table" style="margin-top:10px;">
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

        <div class="row" style="justify-content:space-between; margin-top:10px;">
          <div>Total facturas (neto): <b class="op-total-facturas">0,00</b></div>
          <div>Total pagos: <b id="op-total-pagado">0,00</b></div>
          <div>Saldo (DEBE/HABER): <b id="op-saldo-cte">0,00</b></div>
        </div>
        <div id="op-msg" class="muted" style="margin-top:6px;"></div>


              
              <div class="form-note">
                * Seleccioná las facturas a imputar (opcional para pago a cuenta)
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn" id="op-cancel">Cancelar</button>
            <button class="btn primary" id="op-save">Guardar OP</button>
          </div>
        </div>
      </div>

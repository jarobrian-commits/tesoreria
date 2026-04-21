<!-- Modal proveedor -->
<div class="modal" id="prov-modal">
  <div class="modal-content prov-modal-content">
    <div class="modal-header">
      <h3 id="prov-title">Nuevo proveedor</h3>
    </div>

    <div class="modal-body">
      <input type="hidden" id="prov-id" value="">

      <div class="grid2 prov-form-grid">
        <label>
          Razón social *
          <input class="input" id="prov-razon" required>
        </label>

        <label>
          CUIT (11 dígitos) *
          <input class="input" id="prov-cuit" maxlength="11" required>
        </label>

        <label>
          Condición IVA *
          <select id="prov-iva" class="input" required>
            <option value="">Seleccionar</option>
            <option value="IVA Responsable Inscripto">IVA Responsable Inscripto</option>
            <option value="IVA Sujeto Exento">IVA Sujeto Exento</option>
            <option value="Consumidor Final">Consumidor Final</option>
            <option value="Responsable Monotributo">Responsable Monotributo</option>
            <option value="Sujeto no Categorizado">Sujeto no Categorizado</option>
            <option value="Proveedor del Exterior">Proveedor del Exterior</option>
            <option value="Cliente del Exterior">Cliente del Exterior</option>
            <option value="IVA Liberado – Ley Nº 19.640">IVA Liberado – Ley Nº 19.640</option>
            <option value="Monotributista Social">Monotributista Social</option>
            <option value="IVA No Alcanzado">IVA No Alcanzado</option>
            <option value="Monotributo Trabajador Independiente Promovido">Monotributo Trabajador Independiente Promovido</option>
          </select>
        </label>

        <label>
          Email *
          <input type="email" id="prov-email" class="input" required>
        </label>

        <label>
          Teléfono *
          <input class="input" id="prov-telefono" required>
        </label>

        <label>
          Celular
          <input class="input" id="prov-celular">
        </label>

        <label style="grid-column:1 / -1;">
          Domicilio *
          <input class="input" id="prov-domicilio" required>
        </label>

        <label>
          Provincia *
          <select id="prov-provincia" class="input" required>
            <option value="">Seleccionar</option>
            <option value="Buenos Aires">Buenos Aires</option>
            <option value="CABA">CABA</option>
            <option value="Catamarca">Catamarca</option>
            <option value="Chaco">Chaco</option>
            <option value="Chubut">Chubut</option>
            <option value="Córdoba">Córdoba</option>
            <option value="Corrientes">Corrientes</option>
            <option value="Entre Ríos">Entre Ríos</option>
            <option value="Formosa">Formosa</option>
            <option value="Jujuy">Jujuy</option>
            <option value="La Pampa">La Pampa</option>
            <option value="La Rioja">La Rioja</option>
            <option value="Mendoza">Mendoza</option>
            <option value="Misiones">Misiones</option>
            <option value="Neuquén">Neuquén</option>
            <option value="Río Negro">Río Negro</option>
            <option value="Salta">Salta</option>
            <option value="San Juan">San Juan</option>
            <option value="San Luis">San Luis</option>
            <option value="Santa Cruz">Santa Cruz</option>
            <option value="Santa Fe">Santa Fe</option>
            <option value="Santiago del Estero">Santiago del Estero</option>
            <option value="Tierra del Fuego">Tierra del Fuego</option>
            <option value="Tucumán">Tucumán</option>
          </select>
        </label>

        <label>
          Plazo de pago
          <select id="prov-plazo-pago" class="input">
            <option value="">Seleccionar</option>
            <option value="Anticipado">Anticipado</option>
            <option value="7 días">7 días</option>
            <option value="15 días">15 días</option>
            <option value="30 días">30 días</option>
            <option value="45 días">45 días</option>
            <option value="60 días">60 días</option>
            <option value="30/60 días">30/60 días</option>
            <option value="30/60/90 días">30/60/90 días</option>
            <option value="30/60/90/120 días">30/60/90/120 días</option>
            <option value="30/60/90/120/150 días">30/60/90/120/150 días</option>
            <option value="30/60/90/120/150/180 días">30/60/90/120/150/180 días</option>
          </select>
        </label>

        <label style="grid-column:1 / -1;">
          Notas
          <textarea id="prov-notas" class="input" rows="3"></textarea>
        </label>

        <label class="check" style="grid-column:1 / -1;">
          <input type="checkbox" id="prov-activo" checked> Activo
        </label>
      </div>

      <div class="form-note">
        * Campos obligatorios
      </div>
    </div>

      <div class="prov-ret-toggle-wrap">
  <button type="button" class="btn ghost" id="prov-ret-toggle">
    Retenciones
  </button>
</div>

<div class="bloque-retenciones" style="display:none;">
  <div class="prov-section-title">Retenciones del proveedor</div>

  <input type="hidden" id="ret-id" value="">

  <div class="grid2 prov-ret-grid">
    <label>
      Tipo
      <select id="ret-tipo" class="input">
        <option value="">Seleccionar</option>
        <option value="CONVENIO">Convenio</option>
        <option value="IIBB">IIBB</option>
        <option value="GANANCIAS">Ganancias</option>
        <option value="IVA">IVA</option>
        <option value="OTRA">Otra</option>
      </select>
    </label>

    <label>
      Modo
      <select id="ret-modo" class="input">
        <option value="PORCENTAJE">Porcentaje</option>
        <option value="FIJO">Fijo</option>
        <option value="MANUAL">Manual</option>
      </select>
    </label>

    <label>
      %
      <input type="text" id="ret-porcentaje" class="input" placeholder="0,00">
    </label>

    <label>
      Importe fijo
      <input type="text" id="ret-fijo" class="input" placeholder="0,00">
    </label>

    <label>
      Monto mínimo
      <input type="text" id="ret-minimo" class="input" placeholder="0,00">
    </label>

    <label>
      Activa
      <select id="ret-activo" class="input">
        <option value="1">Sí</option>
        <option value="0">No</option>
      </select>
    </label>

    <label style="grid-column:1 / -1;">
      Detalle
      <input type="text" id="ret-detalle" class="input" placeholder="Detalle / observación">
    </label>
  </div>

  <div class="prov-ret-actions">
    <button type="button" class="btn primary" id="ret-save-btn">Guardar retención</button>
    <button type="button" class="btn" id="ret-clear-btn">Limpiar</button>
  </div>

  <div class="table-wrap">
    <table class="table" id="prov-retenciones-table">
      <thead>
        <tr>
          <th>Tipo</th>
          <th>Modo</th>
          <th class="right">%</th>
          <th class="right">Fijo</th>
          <th class="right">Mínimo</th>
          <th>Detalle</th>
          <th>Activa</th>
          <th class="right">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td colspan="8" class="center muted">Sin retenciones cargadas</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

    <div class="modal-footer">
      <button class="btn" id="prov-cancel" type="button">Cancelar</button>
      <button class="btn primary" id="prov-save" type="button">Guardar</button>
    </div>
  </div>
</div>
// assets/js/proveedores.js
/* =========================
   PROVEEDORES
========================= */

function limpiarErrorCampo(id){
  $("#" + id).removeClass("input-error");
}

function marcarError(id, mensaje){
  $("#prov-modal .input, #prov-modal select, #prov-modal textarea").removeClass("input-error");
  $("#" + id).addClass("input-error").focus();
  if (typeof msg === "function") {
    msg(mensaje);
  } else {
    alert(mensaje);
  }
}

function soloNumeros(texto){
  return String(texto || "").replace(/\D+/g, "");
}

function validarEmailBasico(email){
  if (!email) return true;
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/* =========================
   CUIT / AFIP
========================= */

function validarCUIT(cuit){

  
  const s = soloNumeros(cuit);
  if (s.length !== 11) return false;

  const mult = [5,4,3,2,7,6,5,4,3,2];
  let suma = 0;

  for (let i = 0; i < 10; i++) {
    suma += parseInt(s[i], 10) * mult[i];
  }

  let mod = 11 - (suma % 11);
  if (mod === 11) mod = 0;
  if (mod === 10) mod = 9;

  return mod === parseInt(s[10], 10);
}
$(document).on("blur", "#prov-cuit", function () {
  const limpio = soloNumeros($(this).val()).slice(0, 11);
  $(this).val(limpio);

  if (!limpio) return;

  if (limpio.length !== 11 || !validarCUIT(limpio)) {
    $(this).addClass("input-error");
    return;
  }

  $(this).removeClass("input-error");
  autocompletarIVADesdeCUIT(false);
});

function sugerirCondicionIVADesdeCUIT(cuit){
  const s = soloNumeros(cuit);
  if (s.length < 2) return "";

  const prefijo = s.slice(0, 2);

  // Heurística práctica, editable por el usuario
  if (["20", "23", "24", "27"].includes(prefijo)) {
    return "Consumidor Final";
  }

  if (["30", "33", "34"].includes(prefijo)) {
    return "IVA Responsable Inscripto";
  }

  return "";
}

function autocompletarIVADesdeCUIT(forzar = false){
  const cuit = $("#prov-cuit").val();
  const sugerido = sugerirCondicionIVADesdeCUIT(cuit);
  const actual = $("#prov-iva").val();

  if (!sugerido) return;

  if (forzar || !actual) {
    $("#prov-iva").val(sugerido);
  }
}

/* =========================
   BOTÓN GUARDAR
========================= */

function setProvSaveBusy(isBusy){
  const $btn = $("#prov-save");
  if (!$btn.length) return;

  if (isBusy) {
    $btn.prop("disabled", true).addClass("is-loading");
    if (!$btn.data("original-text")) {
      $btn.data("original-text", $btn.text());
    }
    $btn.text("Guardando...");
  } else {
    $btn.prop("disabled", false).removeClass("is-loading");
    $btn.text($btn.data("original-text") || "Guardar");
  }
}

/* =========================
   VALIDACIÓN
========================= */

function validarProveedorPayload(payload){

  if (!payload.razon_social) {
    marcarError("prov-razon", "Debe ingresar la razón social");
    return false;
  }

  payload.cuit = soloNumeros(payload.cuit);

  if (!payload.cuit) {
    marcarError("prov-cuit", "Debe ingresar el CUIT");
    return false;
  }

  if (payload.cuit.length !== 11) {
    marcarError("prov-cuit", "El CUIT debe tener 11 dígitos");
    return false;
  }

  if (!validarCUIT(payload.cuit)) {
    marcarError("prov-cuit", "El CUIT no es válido");
    return false;
  }

  if (!payload.condicion_iva) {
    marcarError("prov-iva", "Debe seleccionar la condición IVA");
    return false;
  }

  if (!payload.email) {
    marcarError("prov-email", "Debe ingresar el email");
    return false;
  }

  if (!validarEmailBasico(payload.email)) {
    marcarError("prov-email", "El email no es válido");
    return false;
  }

  if (!payload.telefono) {
    marcarError("prov-telefono", "Debe ingresar el teléfono");
    return false;
  }

  if (!payload.domicilio) {
    marcarError("prov-domicilio", "Debe ingresar el domicilio");
    return false;
  }

  
  if (!payload.provincia) {
    marcarError("prov-provincia", "Debe seleccionar la provincia");
    return false;
  }

  
  return true;
}

/* =========================
   FORM
========================= */

function limpiarFormularioProveedor(){
  $("#prov-id").val("");
  $("#prov-retenciones-table tbody").empty();
  $(".bloque-retenciones").hide();

  $("#prov-razon").val("");
  $("#prov-cuit").val("");
  $("#prov-iva").val("");
  $("#prov-email").val("");
  $("#prov-telefono").val("");
  $("#prov-celular").val("");
  $("#prov-domicilio").val("");
  $("#prov-provincia").val("");
  $("#prov-plazo-pago").val("");
  $("#prov-notas").val("");
  $("#prov-activo").prop("checked", true);

  $("#prov-modal .input, #prov-modal select, #prov-modal textarea")
    .prop("disabled", false)
    .removeClass("input-error");

  $("#prov-save").show();
  $("#prov-edit-btn").remove();

  PROV_RETENCIONES = [];
  clearRetencionForm();
  renderProveedorRetenciones();

  setProvSaveBusy(false);
}

function buildProvPayload(){
  return {
    id_proveedor: parseInt($("#prov-id").val() || "0", 10),
    razon_social: ($("#prov-razon").val() || "").trim(),
    cuit: soloNumeros($("#prov-cuit").val()),
    condicion_iva: ($("#prov-iva").val() || "").trim(),
    email: ($("#prov-email").val() || "").trim(),
    telefono: ($("#prov-telefono").val() || "").trim(),
    celular: ($("#prov-celular").val() || "").trim(),
    domicilio: ($("#prov-domicilio").val() || "").trim(),
    provincia: ($("#prov-provincia").val() || "").trim(),
    plazo_pago: ($("#prov-plazo-pago").val() || "").trim(),
    notas: ($("#prov-notas").val() || "").trim(),
    activo: $("#prov-activo").is(":checked") ? 1 : 0
  };
}

/* =========================
   LISTADO
========================= */

async function loadProveedores() {
  try {
    const q = ($("#prov-q").val() || "").trim();
    const estado = $("#prov-estado").length ? $("#prov-estado").val() : "all";

    const res = await apiGet("proveedores_list", { q, estado });
    const rows = Array.isArray(res) ? res : (res.data || res.proveedores || []);

    renderProveedores(rows);
  } catch (e) {
    alertErr(e);
  }
}
function getProvTbody() {
  if ($("#prov-tbody").length) return $("#prov-tbody");
  return $("#prov-table tbody");
}

function renderProveedores(rows) {
  const tbody = getProvTbody();
  tbody.empty();

  rows.forEach(r => {
    const badge = r.activo ? "" : ' <span class="badge bg-secondary">INACTIVO</span>';

    tbody.append(`
      <tr class="${r.activo ? "" : "row-inactivo"}">
        <td>${r.id_proveedor}</td>
        <td>${escapeHtml(r.razon_social || "")}${badge}</td>
        <td>${escapeHtml(r.cuit || "")}</td>
        <td>${escapeHtml(r.condicion_iva || "")}</td>
        <td>${escapeHtml(r.email || "")}</td>
        <td>${escapeHtml(r.telefono || "")}</td>
        <td>${r.activo ? "Sí" : "No"}</td>
        <td class="right">
          <button type="button" class="btn ghost prov-cta" data-id="${r.id_proveedor}">Cuenta corriente</button>
          <button type="button" class="btn ghost prov-view" data-id="${r.id_proveedor}">Ver</button>
        </td>
      </tr>
    `);
  });
}

/* =========================
   EVENTOS DE TABLA
========================= */

$("#prov-export").on("click", function () {
  const q = ($("#prov-q").val() || "").trim();
  const estado = $("#prov-estado").length ? $("#prov-estado").val() : "activos";
  const url = `${api}?action=proveedores_export&q=${encodeURIComponent(q)}&estado=${encodeURIComponent(estado)}`;
  window.open(url, "_blank");
});

$(document).on("click", ".prov-cta", function () {
  const id = parseInt($(this).data("id"), 10);
  if (!id) return;
  window.open(`../print_cta_cte.php?id_proveedor=${id}`, "_blank");
});

$(document).off("click.prov", ".prov-view").on("click.prov", ".prov-view", async function () {
  const id = $(this).data("id");

  try {
    const r = await apiGet("proveedores_get", { id_proveedor: id });

    $("#prov-id").val(r.id_proveedor);
    $("#prov-razon").val(r.razon_social || "");
    $("#prov-cuit").val(r.cuit || "");
    $("#prov-iva").val(r.condicion_iva || "");
    $("#prov-email").val(r.email || "");
    $("#prov-telefono").val(r.telefono || "");
    $("#prov-celular").val(r.celular || "");
    $("#prov-domicilio").val(r.domicilio || "");
    $("#prov-provincia").val(r.provincia || "");
    $("#prov-plazo-pago").val(r.plazo_pago || "");
    $("#prov-notas").val(r.notas || "");
    $("#prov-activo").prop("checked", !!Number(r.activo));

    $("#prov-modal input, #prov-modal select, #prov-modal textarea")
      .prop("disabled", true)
      .removeClass("input-error");

    $("#prov-save").hide();
    $("#prov-modal h3").text("Ver proveedor");

    clearRetencionForm();
    await loadProveedorRetenciones(r.id_proveedor);
    $(".bloque-retenciones").hide();

    if ($("#prov-edit-btn").length === 0) {
      $("#prov-modal .modal-footer").prepend(
        '<button type="button" class="btn" id="prov-edit-btn">Editar</button>'
      );
    }

    showModal("#prov-modal");

  } catch (e) {
    alertErr(e);
  }
});

$(document).off("click.prov", "#prov-edit-btn").on("click.prov", "#prov-edit-btn", function () {
  $("#prov-modal input, #prov-modal select, #prov-modal textarea").prop("disabled", false);
  $("#prov-save").show();
  $("#prov-modal h3").text("Editar proveedor");
  $("#prov-id").prop("disabled", false);
  $(this).remove();
  $("#prov-razon").focus();
  setProvSaveBusy(false);
});

/* =========================
   SELECTS DE PROVEEDORES
========================= */

async function fillProveedorSelects() {
  try {
    const res = await apiGet("proveedores_select", { q: "" });
    const rows = Array.isArray(res) ? res : (res.data || []);

    const options = rows.map(r =>
      `<option value="${r.id_proveedor}">
        ${escapeHtml(r.razon_social)} (${r.cuit})
      </option>`
    ).join("");

    const defaultOption = '<option value="">-- Seleccionar --</option>';

    $("select.need-proveedores").each(function () {
      $(this).html(defaultOption + options);
    });

    if ($("#fac-idproveedor").length) {
      $("#fac-idproveedor").html(defaultOption + options);
    }

    if ($("#op-idproveedor").length) {
      $("#op-idproveedor").html(defaultOption + options);
    }

  } catch (e) {
    console.error("Error cargando proveedores:", e);
  }
}
/* =========================
   EVENTOS GENERALES
========================= */

$(document).ready(function () {

  $("#prov-buscar, #prov-refresh").on("click", async function (e) {
  e.preventDefault();
  e.stopPropagation();

  await loadProveedores();
  $("#prov-q").val("").trigger("input");
});

  if ($("#prov-estado").length) {
    $("#prov-estado").on("change", loadProveedores);
  }

  if ($("#prov-q").length) {
  $("#prov-q").on("keydown", async function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      await loadProveedores();
      $("#prov-q").val("").trigger("input");
    }
  });
}

  $(document).on("input", "#prov-cuit", function () {
  const limpio = soloNumeros($(this).val()).slice(0, 11);
  $(this).val(limpio);
  limpiarErrorCampo("prov-cuit");

  if (limpio.length >= 2) {
    autocompletarIVADesdeCUIT(false);
  }

  if (limpio.length === 11) {
    if (!validarCUIT(limpio)) {
      $(this).addClass("input-error");
      return;
    }
  }

  $(this).removeClass("input-error");
});

  

  $("#prov-razon, #prov-iva, #prov-email, #prov-telefono, #prov-celular, #prov-domicilio, #prov-provincia, #prov-notas, #prov-plazo-pago")
    .on("input change", function () {
      $(this).removeClass("input-error");
    });

  $("#prov-new").on("click", function () {
  limpiarFormularioProveedor();
  $("#prov-modal h3").text("Nuevo proveedor");
  $(".bloque-retenciones").hide();
  showModal("#prov-modal");
  $("#prov-razon").focus();
});

  $("#prov-save").on("click", async function () {
  try {
    if ($(this).prop("disabled")) return;

    const payload = buildProvPayload();
    if (!validarProveedorPayload(payload)) return;

    setProvSaveBusy(true);

    let resp;
    if (payload.id_proveedor) {
      resp = await apiPost("proveedores_update", payload);
    } else {
      resp = await apiPost("proveedores_create", payload);
    }

    const nuevoId =
      payload.id_proveedor ||
      resp?.id_proveedor ||
      resp?.data?.id_proveedor ||
      resp?.insert_id ||
      0;

    await loadProveedores();
    await fillProveedorSelects();

    if (!payload.id_proveedor && nuevoId) {
  $("#prov-id").val(nuevoId);
  $("#prov-modal h3").text("Editar proveedor");
  $(".bloque-retenciones").show();
  clearRetencionForm();
  await loadProveedorRetenciones(nuevoId);

  if (typeof msg === "function") {
    msg("Proveedor guardado. Ahora ya podés cargar retenciones.");
  } else {
    alert("Proveedor guardado. Ahora ya podés cargar retenciones.");
  }
  return;
}

    hideModal("#prov-modal");

  } catch (e) {
    alertErr(e);
  } finally {
    setProvSaveBusy(false);
  }
});

  $("#prov-close-x, #prov-cancel").on("click", function (e) {
    e.preventDefault();
    e.stopPropagation();
    hideModal("#prov-modal");
    setProvSaveBusy(false);
  });

  if ($("#prov-table").length || $("#prov-tbody").length) {
    loadProveedores();
  }

  });

  let PROV_RETENCIONES = [];

function retFmt(v){
  if(v === null || v === undefined || v === "") return "";
  return Number(v).toLocaleString("es-AR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

function clearRetencionForm(){
  $("#ret-id").val("");
  $("#ret-tipo").val("");
  $("#ret-modo").val("PORCENTAJE");
  $("#ret-porcentaje").val("");
  $("#ret-fijo").val("");
  $("#ret-minimo").val("");
  $("#ret-detalle").val("");
  $("#ret-activo").val("1");
}

async function loadProveedorRetenciones(id_proveedor){
  if(!id_proveedor){
    PROV_RETENCIONES = [];
    renderProveedorRetenciones();
    return;
  }

  const res = await apiGet("proveedor_retenciones_list", { id_proveedor });
  const rows = Array.isArray(res) ? res : (res.data || []);
  PROV_RETENCIONES = Array.isArray(rows) ? rows : [];
  renderProveedorRetenciones();
}

$(document).on("click", "#prov-ret-toggle", function(){
  $(".bloque-retenciones").slideToggle(150);
});

function renderProveedorRetenciones(){
  const $tb = $("#prov-retenciones-table tbody");
  $tb.empty();

  if(!PROV_RETENCIONES.length){
    $tb.append(`
      <tr>
        <td colspan="8" class="text-center text-muted">Sin retenciones cargadas</td>
      </tr>
    `);
    return;
  }

  PROV_RETENCIONES.forEach(r => {
    $tb.append(`
      <tr>
        <td>${r.tipo_retencion || ""}</td>
        <td>${r.modo_calculo || ""}</td>
        <td class="text-end">${retFmt(r.porcentaje)}</td>
        <td class="text-end">${retFmt(r.importe_fijo)}</td>
        <td class="text-end">${retFmt(r.monto_minimo)}</td>
        <td>${r.detalle || ""}</td>
        <td>${Number(r.activo) ? "Sí" : "No"}</td>
        <td class="right">
          <button type="button" class="btn ghost ret-edit" data-id="${r.id_retencion}">Editar</button>
          <button type="button" class="btn ghost ret-del" data-id="${r.id_retencion}">Borrar</button>
        </td>
      </tr>
    `);
  });
}

$(document).on("click", "#ret-save-btn", async function(){
  const id_proveedor = parseInt($("#prov-id").val(), 10) || 0;
  const id_retencion = parseInt($("#ret-id").val(), 10) || 0;

  console.log("ID PROVEEDOR:", id_proveedor);
  console.log("VALOR #prov-id:", $("#prov-id").val());
  console.log("CUIT:", $("#prov-cuit").val());

  if(!id_proveedor){
    alert("Primero guardá el proveedor o abrí uno existente.");
    return;
  }

  const payload = {
    id_proveedor,
    tipo_retencion: $("#ret-tipo").val(),
    modo_calculo: $("#ret-modo").val(),
    porcentaje: $("#ret-porcentaje").val(),
    importe_fijo: $("#ret-fijo").val(),
    monto_minimo: $("#ret-minimo").val(),
    detalle: $("#ret-detalle").val(),
    activo: $("#ret-activo").val()
  };

  try{
    if(id_retencion > 0){
      await apiPost("proveedor_retencion_update", {
        id_retencion,
        ...payload
      });
    } else {
      await apiPost("proveedor_retencion_add", payload);
    }

    clearRetencionForm();
    await loadProveedorRetenciones(id_proveedor);
  }catch(e){
    alert(e.message || e);
  }
});

$(document).on("click", "#ret-clear-btn", function(){
  clearRetencionForm();
});

$(document).on("click", ".ret-edit", function(){
  const id = parseInt($(this).data("id"), 10);
  const r = PROV_RETENCIONES.find(x => Number(x.id_retencion) === id);
  if(!r) return;

  $("#ret-id").val(r.id_retencion);
  $("#ret-tipo").val(r.tipo_retencion || "");
  $("#ret-modo").val(r.modo_calculo || "PORCENTAJE");
  $("#ret-porcentaje").val(r.porcentaje ?? "");
  $("#ret-fijo").val(r.importe_fijo ?? "");
  $("#ret-minimo").val(r.monto_minimo ?? "");
  $("#ret-detalle").val(r.detalle ?? "");
  $("#ret-activo").val(String(r.activo ?? 1));
});

$(document).on("click", ".ret-del", async function(){
  const id_retencion = parseInt($(this).data("id"), 10);
  const id_proveedor = parseInt($("#prov-id").val(), 10) || 0;

  if(!id_retencion) return;
  if(!confirm("¿Desactivar esta retención?")) return;

  try{
    await apiPost("proveedor_retencion_delete", { id_retencion });
    await loadProveedorRetenciones(id_proveedor);
  }catch(e){
    alert(e.message || e);
  }
});
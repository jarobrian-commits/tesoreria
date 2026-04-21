/* =========================
   ÓRDENES DE PAGO
========================= */

let OP_PAGOS = [];
let OP_RETENCIONES = [];

/* =========================
   RETENCIONES
========================= */

async function loadOPRetenciones(id_proveedor){
  if(!id_proveedor){
    OP_RETENCIONES = [];
    renderOPRetenciones();
    $("#op-total-retenciones").text("0,00");
    return;
  }

  const res = await apiGet("proveedor_retenciones_list", { id_proveedor });
  const rows = Array.isArray(res) ? res : (res.data || []);

  OP_RETENCIONES = rows.map(r => ({
    ...r,
    activo: Number(r.activo ?? 1),
    importe_calculado: 0
  }));

  recalcOP();
}

function calcularRetenciones(base){
  let total = 0;

  OP_RETENCIONES.forEach(r => {
    let imp = 0;
    const activa = Number(r.activo ?? 1) === 1;

    if (!activa) {
      r.importe_calculado = 0;
      return;
    }

    const montoMinimo = Number(r.monto_minimo || 0);

    if (montoMinimo > 0 && Number(base) < montoMinimo) {
      r.importe_calculado = 0;
      return;
    }

    if (String(r.modo_calculo || "").toUpperCase() === "PORCENTAJE") {
      imp = Number(base || 0) * (Number(r.porcentaje || 0) / 100);
    } else if (String(r.modo_calculo || "").toUpperCase() === "FIJO") {
      imp = Number(r.importe_fijo || 0);
    } else {
      imp = Number(r.importe_calculado || 0);
    }

    r.importe_calculado = imp;
    total += imp;
  });

  return total;
}

function renderOPRetenciones(){
  const tbody = $("#op-retenciones-table tbody").empty();

  if(!OP_RETENCIONES.length){
    tbody.append(`
      <tr>
        <td colspan="3" class="center muted">Sin retenciones</td>
      </tr>
    `);
    return;
  }

  OP_RETENCIONES.forEach((r, idx) => {
    tbody.append(`
      <tr data-idx="${idx}">
        <td>${escapeHtml(r.tipo_retencion || "")}</td>
        <td class="right">${money(r.importe_calculado || 0)}</td>
        <td class="center">
          <input type="checkbox" class="op-ret-activa" ${Number(r.activo ?? 1) ? "checked" : ""}>
        </td>
      </tr>
    `);
  });
}

/* =========================
   PAGOS / IMPORTES
========================= */

function clampImporteMaxOP(v){
  const n = parseARS(v);
  return Math.min(n, 999999999.99);
}

function safeTrimVal(sel){
  return String($(sel).val() || "").trim();
}
function formatARS(n){
  const v = Number(n || 0);
  return v.toLocaleString("es-AR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}


function normalizarImporteTexto(v){
  v = String(v || "").replace(/[^\d,\.]/g, "");
  v = v.replace(/\./g, ",");

  let partes = v.split(",");
  if (partes.length > 2) {
    partes = [partes[0], partes.slice(1).join("")];
  }

  let entero = partes[0] || "";
  let decimal = partes[1] || "";

  if (entero.length > 9) {
    entero = entero.slice(0, 9);
  }

  decimal = decimal.slice(0, 2);

  return (partes.length > 1) ? `${entero},${decimal}` : entero;
}

function bindFormatoImportesOP(){
  $(document)
    .off("input.formatoOP", "#op-pago-importe, .op-imp")
    .on("input.formatoOP", "#op-pago-importe, .op-imp", function () {
      const original = $(this).val() || "";
      const normalizado = normalizarImporteTexto(original);
      $(this).val(normalizado);
    });

  $(document)
    .off("blur.formatoOP", "#op-pago-importe, .op-imp")
    .on("blur.formatoOP", "#op-pago-importe, .op-imp", function () {
      const actual = String($(this).val() || "").trim();
      const n = actual ? Math.min(parseARS(actual), 999999999.99) : 0;
      $(this).val(formatARS(n));
      recalcOP();
    });

  $(document)
    .off("focus.formatoOP", "#op-pago-importe, .op-imp")
    .on("focus.formatoOP", "#op-pago-importe, .op-imp", function () {
      const actual = parseARS($(this).val());
      if (!actual) $(this).val("");
    });
}

function pagoDetalle(p){
  const m = (p.medio_pago || "").toUpperCase();

  if (m === "CHEQUE"){
    return `Banco: ${p.banco || ""} - N°: ${p.nro_cheque || ""} - Emisión: ${p.fecha_cheque || ""} - Vto: ${p.fecha_vto || ""}`.trim();
  }

  return (p.detalle || "");
}

function renderOPPagos(){
  const tbody = $("#op-pagos-table tbody").empty();
  let total = 0;

  OP_PAGOS.forEach((p, idx) => {
    total += Number(p.importe || 0);

    tbody.append(`
      <tr data-idx="${idx}">
        <td>${escapeHtml(p.medio_pago || "")}</td>
        <td>${escapeHtml(pagoDetalle(p))}</td>
        <td class="right">${money(p.importe || 0)}</td>
        <td class="right">
          <button class="btn ghost op-pago-del">X</button>
        </td>
      </tr>
    `);
  });

  return total;
}

$(document).on("click", "#op-save-bottom", function(e){
  e.preventDefault();
  $("#op-save").trigger("click");
});

/* =========================
   RECÁLCULO GENERAL OP
========================= */

function recalcOP(){
  let neto = 0;

  $("#op-facturas-table tbody tr").each(function(){
    const $tr = $(this);
    if (!$tr.find(".op-chk").is(":checked")) return;

    const tipo = String($tr.data("tipo") || "").toUpperCase();
    const imp = parseARS($tr.find(".op-imp").val());

    neto += (tipo === "NC") ? -Math.abs(imp) : Math.abs(imp);
  });

  const totalRetenciones = calcularRetenciones(neto);

  $(".op-total-neto").text(money(neto));
  $("#op-total-netenciones, #op-total-retenciones").text(money(totalRetenciones));

  renderOPRetenciones();

  const totalPagado = renderOPPagos();
  $("#op-total-pagado").text(money(totalPagado));

  const saldo = neto - totalRetenciones - totalPagado;
  $("#op-saldo-cte").text(money(saldo));

  if (Math.abs(saldo) < 0.01) {
    $("#op-msg").text("Saldo 0: pago exacto.");
  } else if (saldo > 0) {
    $("#op-msg").text("DEBE: falta pagar.");
  } else {
    $("#op-msg").text("HABER: pagaste de más (queda crédito a favor).");
  }
}

function calcOPTotal() {
  recalcOP();
}

/* =========================
   HANDLERS PAGOS
========================= */

$(document).on("click", "#op-pago-add", function(e){
  e.preventDefault();

  $("#op-pago-importe").val(formatARS(parseARS($("#op-pago-importe").val())));

  const medio = $("#op-pago-medio").val();
  const importe = parseARS($("#op-pago-importe").val());

  if (!importe || importe <= 0) {
    $("#op-msg").text("Importe inválido.");
    $("#op-pago-importe").focus();
    return;
  }

  let pago = {
    fecha_pago: $("#op-fecha").val() || null,
    medio_pago: medio,
    importe: importe,
    detalle: null,
    banco: null,
    nro_cheque: null,
    fecha_cheque: null,
    fecha_vto: null
  };

  if (medio === "CHEQUE") {
    pago.banco = safeTrimVal("#op-chq-banco") || null;
    pago.nro_cheque = safeTrimVal("#op-chq-num") || null;
    pago.fecha_cheque = $("#op-chq-fecha").val() || null;
    pago.fecha_vto = $("#op-chq-vto").val() || null;
    pago.detalle = `${pago.banco || ""} ${pago.nro_cheque || ""}`.trim() || null;
  }

  if (medio === "TRANSFERENCIA") {
    pago.detalle = safeTrimVal("#op-trx-detalle") || null;
  }

  if (medio === "OTRO") {
    pago.detalle = safeTrimVal("#op-pago-detalle") || null;
  }

  OP_PAGOS.push(pago);

  $("#op-pago-importe").val("0,00");
  $("#op-trx-detalle").val("");
  $("#op-pago-detalle").val("");
  $("#op-chq-banco").val("");
  $("#op-chq-num").val("");
  $("#op-chq-fecha").val(todayISO());
  $("#op-chq-vto").val("");

  $("#op-pago-medio").val("EFECTIVO");
  refreshPagoUI();

  recalcOP();
  $("#op-msg").text("");

  const wrap = document.querySelector("#op-pagos-scroll");
  if (wrap) {
    wrap.scrollTop = wrap.scrollHeight;
  }

  const saldo = document.querySelector("#op-saldo-cte");
  if (saldo) {
    saldo.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }
});

$(document).on("click", ".op-pago-del", function(e){
  e.preventDefault();

  const idx = parseInt($(this).closest("tr").data("idx"), 10);
  if (isNaN(idx)) return;

  OP_PAGOS.splice(idx, 1);
  recalcOP();
  $("#op-msg").text("");
});
/* =========================
   ANULAR OP
========================= */

$(document).on("click", ".op-anular", async function () {
  const id = $(this).data("id");

  if (!confirm(`¿Anular la OP ${id}? Las facturas volverán a estado CARGADA.`)) {
    return;
  }

  try {
    await apiPost("op_anular", { id_op: id });

    alert("OP anulada correctamente");

    await loadOP();
    if (typeof loadFacturas === "function") {
      await loadFacturas();
    }

  } catch (e) {
    alertErr(e);
  }
});

/* =========================
   LISTADO OP
========================= */

async function loadOP(limpiarDespues = false) {
  try {
    const q = (String($("#op-q").val() || "")).trim();
    const desde = (String($("#op-desde").val() || "")).trim();
    const hasta = (String($("#op-hasta").val() || "")).trim();

    const res = await apiGet("op_list", { q, desde, hasta });
    const rows = Array.isArray(res) ? res : (res.data || []);
    const tbody = $("#op-table tbody").empty();

    rows.forEach(r => {
      tbody.append(`
        <tr>
          <td>${r.id_op}</td>
          <td>${(r.fecha_op || "").toString().slice(0,10)}</td>
          <td>${escapeHtml(r.razon_social || "")}</td>
          <td>${escapeHtml(r.cuit || "")}</td>
          <td class="right">${money(r.total || 0)}</td>
          <td class="right">${money(r.importe_pago || 0)}</td>
          <td>${escapeHtml(r.estado || "")}</td>
          <td>${escapeHtml(r.medio_pago || "")}</td>
          <td class="right">
            <a class="btn ghost" href="../print_op.php?id_op=${r.id_op}" target="_blank">Imprimir</a>
            ${(String(r.estado || "").toUpperCase() !== "ANULADA")
              ? `<button type="button" class="btn ghost op-anular" data-id="${r.id_op}">Anular</button>`
              : ""}
          </td>
        </tr>
      `);
    });
  if (limpiarDespues) {
      $("#op-q").val("");
      $("#op-desde").val("");
      $("#op-hasta").val("");
    }

  } catch (e) {
    alertErr(e);
  }
}

/* =========================
   FACTURAS PENDIENTES PARA OP
========================= */

async function loadFacturasPendientesOP() {
  try {
    const idp = parseInt($("#op-idproveedor").val(), 10);

    if (!idp) {
      $("#op-facturas-table tbody").empty();
      $(".op-total-neto").text("0,00");
      $("#op-total-pagado").text("0,00");
      $("#op-saldo-cte").text("0,00");
      $("#op-total-retenciones").text("0,00");
      $("#op-msg").text("");
      return;
    }

    const res = await apiGet("facturas_list", { q: "", estado: "CARGADA" });
    const rows = Array.isArray(res) ? res : (res.data || []);
    const facturasProveedor = rows.filter(f => parseInt(f.id_proveedor, 10) === idp);

    const q = ($("#op-fac-q").val() || "").trim();
    const factFiltradas = q
      ? facturasProveedor.filter(f => String(f.numero || "").includes(q))
      : facturasProveedor;

    const tbody = $("#op-facturas-table tbody").empty();

    factFiltradas.forEach(f => {
      const impSigned = signedImporteByTipo(f.tipo, f.importe_total);
      const impAbs = Math.abs(Number(f.importe_total || 0));

      tbody.append(`
        <tr data-id="${f.id_factura}" data-tipo="${f.tipo}" data-imp="${impAbs}">
          <td><input type="checkbox" class="op-chk"></td>
          <td>${escapeHtml(f.numero || "")}</td>
          <td>${escapeHtml(f.tipo || "")}</td>
          <td>${(f.fecha_emision || "").toString().slice(0,10)}</td>
          <td class="right">${money(impSigned)}</td>
          <td class="right">
            <input class="input right op-imp" value="${fmt2(impAbs)}" style="max-width:140px;">
          </td>
        </tr>
      `);
    });

    recalcOP();

  } catch (e) {
    alertErr(e);
  }
}

/* =========================
   AUXILIARES OP
========================= */

async function cargarPlazoPagoProveedor(idProveedor){
  try {
    if (!idProveedor) {
      $("#op-plazo-pago").val("");
      return;
    }

    const r = await apiGet("proveedores_get", { id_proveedor: idProveedor });
    $("#op-plazo-pago").val(r.plazo_pago || "");
  } catch (e) {
    $("#op-plazo-pago").val("");
    console.error("No se pudo cargar el plazo de pago", e);
  }
}

function refreshPagoUI(){
  const m = $("#op-pago-medio").val();

  $("#op-cheque-fields").toggle(m === "CHEQUE");
  $("#op-transf-fields").toggle(m === "TRANSFERENCIA");
  $("#op-otro-fields").toggle(m === "OTRO");
}
$(document).on("change", "#op-pago-medio", function(){
  refreshPagoUI();
});

function initOPModal() {
  $("#op-fecha").val(todayISO());
  $("#op-obs").val("");
  $("#op-fac-q").val("");

  OP_PAGOS = [];
  OP_RETENCIONES = [];

  $("#op-pago-importe").val("0,00");
  $("#op-trx-detalle").val("");
  $("#op-pago-detalle").val("");
  $("#op-chq-banco").val("");
  $("#op-chq-num").val("");
  $("#op-chq-fecha").val(todayISO());
  $("#op-chq-vto").val("");
  $("#op-plazo-pago").val("");

  $("#op-pago-medio").val("EFECTIVO");
  refreshPagoUI();

  renderOPPagos();
  renderOPRetenciones();

  $("#op-facturas-table tbody").empty();

  $(".op-total-neto").text("0,00");
  $("#op-total-pagado").text("0,00");
  $("#op-total-retenciones").text("0,00");
  $("#op-saldo-cte").text("0,00");
  $("#op-msg").text("");

  $(document).off("change.op", ".op-chk");
  $(document).off("input.op", ".op-imp");

  $(document).on("change.op", ".op-chk", recalcOP);
    bindFormatoImportesOP();
}

/* =========================
   EVENTOS PRINCIPALES
========================= */

$(document).ready(async function () {
$("#op-buscar").on("click", function (e) {
  e.preventDefault();
  loadOP(true);
});

$("#op-refresh").on("click", function (e) {
  e.preventDefault();
  $("#op-q").val("");
  $("#op-desde").val("");
  $("#op-hasta").val("");
  loadOP(false);
});

$("#op-q, #op-desde, #op-hasta").on("keydown", function (e) {
  if (e.key === "Enter") {
    e.preventDefault();
    loadOP(true);
  }
});
  if ($("#op-table").length) {
    loadOP();
  }

  $("#op-new").off("click.op").on("click.op", function (e) {
  e.preventDefault();
  const cliente = new URLSearchParams(window.location.search).get("cliente") || "demo";
  window.open(`/tesoreria/op_nueva.php?cliente=${encodeURIComponent(cliente)}`, "_blank");
});

  $("#op-close, #op-cancel").on("click", function (e) {
    e.preventDefault();
    if (typeof hideModal === "function") {
      hideModal("#op-modal");
    }
  });

  $("#op-idproveedor").on("change", async function () {
    await loadOPRetenciones($(this).val());
    await cargarPlazoPagoProveedor($(this).val());
    await loadFacturasPendientesOP().catch(alertErr);
  });

  $("#op-fac-refresh").on("click", function (e) {
    e.preventDefault();
    loadFacturasPendientesOP().catch(alertErr);
  });

  $("#op-fac-q").on("keydown", function (e) {
    if (e.key === "Enter") loadFacturasPendientesOP().catch(alertErr);
  });

  $("#op-save").on("click", async function (e) {
    e.preventDefault();

    try {
      const idp = parseInt($("#op-idproveedor").val(), 10);
      if (!idp) return alert("Seleccioná un proveedor.");

      const fecha_op = $("#op-fecha").val();
      if (!fecha_op) return alert("Ingresá la fecha de la OP.");

      const facturas = [];
      const imputaciones = {};

      $("#op-facturas-table tbody tr").each(function () {
        const $tr = $(this);
        if (!$tr.find(".op-chk").is(":checked")) return;

        const idf = parseInt($tr.data("id"), 10);
        if (!idf) return;

        facturas.push(idf);

        const imp = parseARS($tr.find(".op-imp").val());
        if (imp > 0) imputaciones[idf] = imp;
      });

      const totalPagos = OP_PAGOS.reduce((a, p) => a + Number(p.importe || 0), 0);
      if (facturas.length === 0 && totalPagos <= 0) {
        alert("Seleccioná al menos una factura o cargá un pago a cuenta.");
        return;
      }

      const payload = {
        id_proveedor: idp,
        fecha_op,
        observacion: ($("#op-obs").val() || "").trim() || null,
        facturas,
        imputaciones,
        pagos: OP_PAGOS,
        retenciones: OP_RETENCIONES
      };

      const res = await apiPost("op_create_from_facturas", payload);

      if (typeof hideModal === "function") {
        hideModal("#op-modal");
      }

      if ($("#op-table").length) await loadOP();
      if (typeof loadFacturas === "function" && $("#fac-table").length) await loadFacturas();

      alert(`OP #${res.id_op} creada correctamente.`);

      if (!$("#op-modal").length || !$("#op-modal").is(":visible")) {
        const cliente = new URLSearchParams(window.location.search).get("cliente") || "demo";
        window.location.href = `/tesoreria/tesoreria/index.php?cliente=${encodeURIComponent(cliente)}&tab=op`;
      }

    } catch (err) {
      alertErr(err);
    }
  });

    // Inicialización para op_nueva.php
  if ($("#op-idproveedor").length && $("#op-facturas-table").length) {
    initOPModal();

    if (typeof fillProveedorSelects === "function") {
      await fillProveedorSelects();
    }

    // si hay opciones, seleccionar la primera real y disparar change
    if ($("#op-idproveedor option").length > 1) {
      $("#op-idproveedor").prop("selectedIndex", 1).trigger("change");
    } else {
      await cargarPlazoPagoProveedor($("#op-idproveedor").val());
      await loadFacturasPendientesOP().catch(alertErr);
    }
  }
});

/* =========================
   SELECTS DE PROVEEDORES
========================= */

async function fillProveedorSelects() {
  try {
    const res = await apiGet("proveedores_list", { q: "" });
    const rows = Array.isArray(res) ? res : (res.data || res.proveedores || []);

    const options = rows.map(r =>
      `<option value="${r.id_proveedor}">${escapeHtml(r.razon_social)} (${r.cuit})</option>`
    ).join("");

    const defaultOption = '<option value="">-- Seleccionar --</option>';

    $("select.need-proveedores").each(function() {
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
   INICIALIZACIÓN / GLOBALES
========================= */

$(document).on("click", "#prov-close", function (e) {
  e.preventDefault();
  e.stopPropagation();
  hideModal("#prov-modal");
});

$(document).on("click", "#prov-cancel", function (e) {
  e.preventDefault();
  hideModal("#prov-modal");
});

$(document).on("submit", "form", function(e) {
  e.preventDefault();
});

$(document).on("keypress", "input", function(e) {
  if (e.which === 13) {
    e.preventDefault();
    $(this).blur();
  }
});
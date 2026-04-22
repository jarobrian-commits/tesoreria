/* =========================
   FACTURAS (listado + modal)
   - Normaliza ítems con clases .fac-*
   - Recalcula bonificación y totales
   - Evita dobles bindings usando namespaces
========================= */

/* ---------- Helpers defensivos ---------- */
if (typeof attachCbteMask !== "function") {
  // Si no hay máscara implementada, no hacemos nada.
  window.attachCbteMask = function () {};
}

if (typeof signedImporteByTipo !== "function") {
  // Fallback: NC negativo, el resto positivo
  window.signedImporteByTipo = function (tipo, importe) {
    const t = String(tipo || "").toUpperCase().trim();
    const n = Number(importe || 0);
    return (t === "NC") ? -Math.abs(n) : Math.abs(n);
  };
}

function clearItemsUI() {
  $("#fac-items-table tbody").empty();
  $("#fac-items-total").text("0,00");
}

function addItemRow(data = {}) {
  const d = {
    codigo: data.codigo || "",
    descripcion: data.descripcion || "",
    cantidad: (data.cantidad != null ? formatARS(data.cantidad) : "1,00"),
  precio_unit: (data.precio_unit != null ? formatARS(data.precio_unit) : "0,00"),
    bonifica: (data.bonifica != null ? formatARS(data.bonifica) : "0,00")
  };

  const $tb = $("#fac-items-table tbody");

  const tr = $(
    `\
    <tr class="fac-item-row">
      <td><input class="input fac-codigo" value="${escapeHtml(String(d.codigo))}" /></td>
      <td><input class="input fac-detalle" value="${escapeHtml(String(d.descripcion))}" /></td>
      <td class="right"><input class="input fac-cantidad" type="text" value="${escapeHtml(String(d.cantidad))}" /></td>
      <td class="right"><input class="input fac-precio" type="text" value="${escapeHtml(String(d.precio_unit))}" /></td>
      <td class="right"><input class="input fac-bonifica" type="text" value="${escapeHtml(String(d.bonifica))}" /></td>
      <td class="right"><span class="fac-imp-bonif">0,00</span></td>
      <td class="right"><span class="fac-subtotal">0,00</span></td>
      <td class="right"><span class="fac-total">0,00</span></td>
      <td class="right"><button type="button" class="btn ghost fac-item-del">Quitar</button></td>
    </tr>`
  );

  $tb.append(tr);
}

function collectFacturaItems() {
  const items = [];

  $("#fac-items-table tbody tr.fac-item-row").each(function () {
    const $tr = $(this);

    const codigo = ($tr.find(".fac-codigo").val() || "").toString().trim();
    const descripcion = ($tr.find(".fac-detalle").val() || "").toString().trim();

    const cantidad = parseARS($tr.find(".fac-cantidad").val());
    const precio_unit = parseARS($tr.find(".fac-precio").val());
    const bonifica = parseARS($tr.find(".fac-bonifica").val());

    // Ignorar filas totalmente vacías
    const hasSomething = (codigo !== "") || (descripcion !== "") || (cantidad !== 0) || (precio_unit !== 0) || (bonifica !== 0);
    if (!hasSomething) return;

    items.push({
      codigo,
      descripcion,
      cantidad,
      precio_unit,
      bonifica
    });
  });

  return items;
}
function parseARS(v){
  let s = String(v || "").trim();
  if(!s) return 0;

  s = s.replace(/\s/g, "");

  if (s.includes(",") && s.includes(".")) {
    s = s.replace(/\./g, "").replace(",", ".");
  } else if (s.includes(",")) {
    s = s.replace(",", ".");
  }

  const n = Number(s);
  return isNaN(n) ? 0 : n;
}

function formatARS(n){
  return Number(n || 0).toLocaleString("es-AR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

function formatARSInput(v){
  const n = parseARS(v);
  return formatARS(n);
}

function clampImporteMax(v){
  const n = parseARS(v);
  return Math.min(n, 999999999.99);
}

function recalcAllItems() {
  let sum = 0;

  $("#fac-items-table tbody tr.fac-item-row").each(function () {
    const $tr = $(this);

    const cantidad = parseARS($tr.find(".fac-cantidad").val());
    const precio = parseARS($tr.find(".fac-precio").val());
    let bonp = parseARS($tr.find(".fac-bonifica").val());
    if (!Number.isFinite(bonp)) bonp = 0;
    if (bonp < 0) bonp = 0;
    if (bonp > 100) bonp = 100;

    const subtotal = cantidad * precio;
    const impBonif = subtotal * (bonp / 100);
    const total = subtotal - impBonif;

    $tr.find(".fac-imp-bonif").text(fmt2(impBonif));
    $tr.find(".fac-subtotal").text(fmt2(subtotal));
    $tr.find(".fac-total").text(fmt2(total));

    sum += total;
  });

  $("#fac-items-total").text(fmt2(sum));
  return sum;
}

/* ---------- Modal: readonly / reset / view ---------- */

function setFacturaModalReadonly(readonly) {
  const $elements = $("#fac-modal input, #fac-modal select, #fac-modal textarea, #fac-add-item, .fac-item-del");
  $elements.prop("disabled", readonly);

  $("#fac-close, #fac-cancel").prop("disabled", false);
  $("#fac-save").toggle(!readonly);
}

function resetFacturaModalNueva() {
  $("#fac-modal-title").text("Nueva factura");
  $("#fac-idfactura").val("");

  $("#fac-fecha-carga").val(todayISO());
  $("#fac-fecha-emision").val(todayISO());
  $("#fac-fecha-venc").val("");

  $("#fac-tipo").val("FC");
  $("#fac-tipo-cbte").val("A");
  $("#fac-numero").val("");
  $("#fac-obs").val("");
  $("#fac-importe").val("");
  $("#fac-pdf").val("");

  clearItemsUI();
  addItemRow({ cantidad: 1, precio_unit: 0, bonifica: 0 });
  recalcAllItems();

  setFacturaModalReadonly(false);
  attachCbteMask();
}

async function openFacturaForView(id_factura) {
  try {
    const data = await apiGet("facturas_get", { id_factura });
    const cab = data.cab;
    const items = data.items || [];

    $("#fac-modal-title").text(`Factura #${cab.id_factura} (${cab.estado})`);
    const $sel = $("#fac-idproveedor");
const idp = String(cab.id_proveedor);

if ($sel.find(`option[value="${idp}"]`).length === 0) {
  // proveedor no está (probablemente inactivo), lo agrego “solo lectura”
  $sel.append(
    `<option value="${idp}" selected disabled>${escapeHtml(cab.razon_social)} (${cab.cuit}) - INACTIVO</option>`
  );
}

$sel.val(idp);


    $("#fac-idproveedor").val(cab.id_proveedor);
    $("#fac-tipo").val(cab.tipo || "FC");
    $("#fac-tipo-cbte").val(cab.tipo_cbte || "A");
    $("#fac-numero").val(cab.numero || "");
    $("#fac-obs").val(cab.observacion || "");

    const formatDate = (v) => (v ? String(v).slice(0, 10) : "");
    $("#fac-fecha-carga").val(formatDate(cab.fecha_carga));
    $("#fac-fecha-emision").val(formatDate(cab.fecha_emision));
    $("#fac-fecha-venc").val(formatDate(cab.fecha_vencimiento));

    $("#fac-importe").val(cab.importe_total != null ? fmt2(cab.importe_total) : "");

    clearItemsUI();
    if (items.length === 0) {
      addItemRow({ cantidad: 1, precio_unit: 0, bonifica: 0 });
    } else {
      items.forEach(it => {
        addItemRow({
          codigo: it.codigo || "",
          descripcion: it.descripcion || "",
          cantidad: it.cantidad || 1,
          precio_unit: it.precio_unit || 0,
          bonifica: it.bonifica_porc || it.bonifica || 0
        });
      });
    }

    recalcAllItems();

    const readonly = (cab.estado !== "CARGADA") || !!cab.id_op;
    setFacturaModalReadonly(readonly);

    attachCbteMask();
    showModal("#fac-modal");
  } catch (e) {
    alertErr(e);
  }
}

/* ---------- Listado ---------- */

async function loadFacturas() {
  if (!$("#fac-table").length) return;
  try {
    const q = ($("#fac-q").val() || "").trim();
    const estado = ($("#fac-estado").val() || "");
    const desde_emision = ($("#fac-de").val() || "");
    const hasta_emision = ($("#fac-ha").val() || "");

    const rows = await apiGet("facturas_list", { q, estado, desde_emision, hasta_emision });
    const tbody = $("#fac-table tbody").empty();

    rows.forEach(r => {
      const impSigned = signedImporteByTipo(r.tipo, r.importe_total);

      tbody.append(`
        <tr data-id="${r.id_factura}" data-imp="${r.importe_total}" data-tipo="${escapeHtml(r.tipo || "")}">
          <td class="right"><input type="checkbox" class="fac-chk"></td>
          <td>${escapeHtml(r.razon_social || "")}</td>
          <td>${escapeHtml(r.tipo || "")}</td>
          <td>${escapeHtml(r.tipo_cbte || "")}</td>
          <td>${escapeHtml(r.numero || "")}</td>
          <td>${(r.fecha_carga || "").toString().slice(0,10)}</td>
          <td>${(r.fecha_emision || "").toString().slice(0,10)}</td>
          <td>${(r.fecha_vencimiento || "").toString().slice(0,10)}</td>
          <td class="right">${money(impSigned)}</td>
          <td>${escapeHtml(r.estado || "")}</td>
          <td>${r.id_op || ""}</td>
          <td class="right">
            ${r.pdf_path ? `<button class="btn ghost fac-pdf" data-file="${escapeHtml(r.pdf_path)}" title="Ver PDF">📎</button>` : ""}
            <button type="button" class="btn ghost fac-view" data-id="${r.id_factura}">🔍</button>
            ${(r.estado === "CARGADA" && !r.id_op) ? `<button type="button" class="btn ghost fac-del" data-id="${r.id_factura}">Borrar</button>` : ""}
          </td>
        </tr>
      `);
    });

    $("#fac-chk-all").prop("checked", false).prop("indeterminate", false);
    updateFacturasSelectedTotal();
    refreshFacChkAllState();
  } catch (e) {
    alertErr(e);
  }
}
function updateFacturasSelectedTotal() {
  let sum = 0;
  $("#fac-table tbody tr").each(function () {
    const $tr = $(this);
    if (!$tr.find(".fac-chk").is(":checked")) return;
    const tipo = $tr.data("tipo");
    const imp = $tr.data("imp");
    sum += signedImporteByTipo(tipo, imp);
  });
  $("#fac-total-sel").text(money(sum));
}

function refreshFacChkAllState() {
  const $all = $("#fac-chk-all");
  const $chks = $("#fac-table tbody .fac-chk");
  const total = $chks.length;
  const checked = $chks.filter(":checked").length;

  if (total === 0) {
    $all.prop("checked", false).prop("indeterminate", false);
    return;
  }
  if (checked === 0) {
    $all.prop("checked", false).prop("indeterminate", false);
  } else if (checked === total) {
    $all.prop("checked", true).prop("indeterminate", false);
  } else {
    $all.prop("checked", false).prop("indeterminate", true);
  }
}

/* ---------- Bindings (namespaced para no duplicar) ---------- */

$(document).ready(function () {
  
  $("#fac-export").on("click", function () {
  const q = ($("#fac-q").val() || "").trim();
  const estado = ($("#fac-estado").val() || "").trim();
  const desde_emision = ($("#fac-de").val() || "").trim();
  const hasta_emision = ($("#fac-ha").val() || "").trim();

  const url =
    `${api}?action=facturas_export`
    + `&q=${encodeURIComponent(q)}`
    + `&estado=${encodeURIComponent(estado)}`
    + `&desde_emision=${encodeURIComponent(desde_emision)}`
    + `&hasta_emision=${encodeURIComponent(hasta_emision)}`;

  window.open(url, "_blank");
});


  // Selección
  $(document).off("change.fact", ".fac-chk").on("change.fact", ".fac-chk", function () {
    updateFacturasSelectedTotal();
    refreshFacChkAllState();
  });

  $(document).off("change.fact", "#fac-chk-all").on("change.fact", "#fac-chk-all", function () {
    const on = $(this).is(":checked");
    $("#fac-table tbody .fac-chk").prop("checked", on);
    updateFacturasSelectedTotal();
    refreshFacChkAllState();
  });

  // Listado
  $("#fac-refresh").off("click.fact").on("click.fact", async function () {
  await loadFacturas();

  $("#fac-q").val("");
  $("#fac-estado").val("");
  $("#fac-de").val("");
  $("#fac-ha").val("");
});

  // Nueva
  $("#fac-new").off("click.fact").on("click.fact", function (e) {
  e.preventDefault();
  const cliente = new URLSearchParams(window.location.search).get("cliente") || "demo";
  window.open(`/factura_nueva.php?cliente=${encodeURIComponent(cliente)}`, "_blank");
});

  // Ver
  $(document).off("click.fact", ".fac-view").on("click.fact", ".fac-view", function () {
    const id = $(this).data("id");
    openFacturaForView(id);
  });

  // Borrar
  $(document).off("click.fact", ".fac-del").on("click.fact", ".fac-del", async function () {
    const id = $(this).data("id");
    if (!confirm(`¿Borrar factura ${id}? (solo si está CARGADA y sin OP)`)) return;
    try {
      await apiPost("facturas_delete", { id_factura: id });
      await loadFacturas();
      alert("Factura borrada correctamente.");
    } catch (e) {
      alertErr(e);
    }
  });

  // Cerrar modal
  $("#fac-close").off("click.fact").on("click.fact", function (e) {
  e.preventDefault();
  hideModal("#fac-modal");
});

  // Agregar ítem
  $("#fac-add-item").off("click.fact").on("click.fact", function (e) {
  e.preventDefault();

  addItemRow({ cantidad: 1, precio_unit: 0, bonifica: 0 });
  recalcAllItems();

  const $newRow = $("#fac-items-table tbody tr").last();
  const $wrap = $("#fac-items-table").closest(".table-wrap");

  if ($newRow.length) {
    if ($wrap.length) {
      $wrap.animate({
        scrollTop: $wrap.scrollTop() + $newRow.position().top
      }, 200);
    } else {
      $("html, body").animate({
        scrollTop: $newRow.offset().top - 120
      }, 200);
    }

    $newRow.find(".fac-codigo").focus();
  }
});

  // Quitar ítem
  $(document).off("click.fact", ".fac-item-del").on("click.fact", ".fac-item-del", function (e) {
    e.preventDefault();
    $(this).closest("tr").remove();
    recalcAllItems();
  });

  // Recalcular al tipear
$(document)
  .off("input.fact", ".fac-cantidad, .fac-precio, .fac-bonifica")
  .on("input.fact", ".fac-cantidad, .fac-precio, .fac-bonifica", function () {
    let v = $(this).val() || "";

    // PRECIO UNITARIO: máximo 999999999,99
    if ($(this).hasClass("fac-precio")) {
      v = v.replace(/[^\d,]/g, "");

      let partes = v.split(",");
      if (partes.length > 2) {
        partes = [partes[0], partes.slice(1).join("")];
      }

      let entero = partes[0] || "";
      let decimal = partes[1] || "";

      if (entero.length > 9) {
        alert("El máximo permitido en Precio Unitario es 999.999.999,99");
        entero = entero.slice(0, 9);
      }

      decimal = decimal.slice(0, 2);

      v = (partes.length > 1) ? `${entero},${decimal}` : entero;
    } else {
      v = v.replace(/[^\d,.\-]/g, "");
    }

    $(this).val(v);
    recalcAllItems();
  });

  
$(document)
  .off("blur.fact", ".fac-cantidad, .fac-precio")
  .on("blur.fact", ".fac-cantidad, .fac-precio", function () {
    const actual = ($(this).val() || "").trim();

    if (!actual) {
      $(this).val("0,00");
    } else {
      let n = parseARS(actual);

      if ($(this).hasClass("fac-precio")) {
        if (n > 999999999.99) {
          alert("El máximo permitido en Precio Unitario es 999.999.999,99");
        }
        n = Math.min(n, 999999999.99);
      }

      $(this).val(formatARS(n));
    }

    recalcAllItems();
  });

$(document)
  .off("blur.fact", ".fac-bonifica")
  .on("blur.fact", ".fac-bonifica", function () {
    let n = parseARS($(this).val());

    if (!Number.isFinite(n)) n = 0;
    if (n < 0) n = 0;
    if (n > 100) n = 100;

    $(this).val(formatARS(n));
    recalcAllItems();
  });

$(document)
  .off("input.fact", "#fac-importe")
  .on("input.fact", "#fac-importe", function () {
    let v = $(this).val();

    // permitir solo números y una coma
    v = v.replace(/[^\d,]/g, "");

    const partes = v.split(",");

    // si hay más de una coma, dejar solo la primera
    if (partes.length > 2) {
      v = partes[0] + "," + partes.slice(1).join("");
    }

    let entero = partes[0] || "";
    let decimal = partes[1] || "";

    // máximo 9 dígitos enteros
    if (entero.length > 9) {
      alert("El máximo permitido en Importe Total es 999.999.999,99");
      entero = entero.slice(0, 9);
    }

    // máximo 2 decimales
    decimal = decimal.slice(0, 2);

    // reconstruir
    if (v.includes(",")) {
      $(this).val(entero + "," + decimal);
    } else {
      $(this).val(entero);
    }
  });

$(document)
  .off("blur.fact", "#fac-importe")
  .on("blur.fact", "#fac-importe", function () {
    const actual = ($(this).val() || "").trim();
    if (!actual) return;

    const n = Math.min(parseARS(actual), 999999999.99);
    $(this).val(formatARS(n));
  });

  $("#fac-save").off("click.fact").on("click.fact", async function () {
    try {
      const idProveedor = parseInt($("#fac-idproveedor").val(), 10);
      if (!idProveedor) {
        alert("Seleccioná un proveedor.");
        return;
      }

      const numeroDigits = onlyDigits($("#fac-numero").val());
      if (!numeroDigits || numeroDigits.length === 0) {
        alert("Ingresá el número de comprobante.");
        $("#fac-numero").focus();
        return;
      }
      const numeroCanon = formatCbteNumberCanon(numeroDigits);

      const items = collectFacturaItems();
      if (!items.length) {
        alert("La factura debe tener al menos un ítem.");
        return;
      }

      const totalItems = recalcAllItems();
      const totalCab = parseARS($("#fac-importe").val());

      const a = Math.round((totalCab || 0) * 100) / 100;
      const b = Math.round((totalItems || 0) * 100) / 100;

      if (!Number.isFinite(a) || a === 0) {
        alert("Ingresá el importe total antes de guardar.");
        $("#fac-importe").focus();
        return;
      }

      if (Math.abs(a - b) > 0.01) {
        const dif = Math.round((a - b) * 100) / 100;
        alert(
          "Los totales no coinciden:\n\n" +
          "Total factura: $" + fmt2(a) + "\n" +
          "Total ítems: $" + fmt2(b) + "\n" +
          "Diferencia: $" + fmt2(dif)
        );
        return;
      }

      const facturaData = {
        id_proveedor: idProveedor,
        tipo: $("#fac-tipo").val(),
        tipo_cbte: $("#fac-tipo-cbte").val(),
        numero: numeroCanon,
        fecha_carga: $("#fac-fecha-carga").val(),
        fecha_emision: $("#fac-fecha-emision").val(),
        fecha_vencimiento: $("#fac-fecha-venc").val() || null,
        observacion: ($("#fac-obs").val() || "").trim(),
        importe_total: a,
        items
      };

      const res = await apiPost("facturas_create_full", facturaData);

      const file = $("#fac-pdf")[0]?.files?.[0];
      if (file) {
        try {
          const fd = new FormData();
          fd.append("id_factura", res.id_factura);
          fd.append("pdf", file);
          await apiPostForm("facturas_upload_pdf", fd);
        } catch (uploadError) {
          console.warn("Error al subir PDF:", uploadError);
        }
      }

      alert(`Factura #${res.id_factura} creada correctamente.`);
const cliente = new URLSearchParams(window.location.search).get("cliente") || "demo";
      window.location.href = `/index.php?cliente=${encodeURIComponent(cliente)}&tab=facturas`;
    } catch (e) {
      alertErr(e);
    }
  });

  // Carga inicial del listado (si estás en la pestaña facturas, igual sirve)
  if ($("#fac-table").length) {
    loadFacturas();
  }
});
/* =========================
   UX: TAB en último ítem -> agrega nueva línea
   - Evita depender del botón "Agregar ítem"
========================= */
(function () {
  // engancha una sola vez (por si se recarga el script)
  $(document)
    .off("keydown.facTabAdd", "#fac-items-table tbody input")
    .on("keydown.facTabAdd", "#fac-items-table tbody input", function (e) {
      if (e.key !== "Tab" || e.shiftKey) return;

      const $lastRow = $("#fac-items-table tbody tr").last();
      if (!$lastRow.length) return;

      const $inputs = $lastRow.find("input");
      if (!$inputs.length) return;

      const lastInput = $inputs.last()[0];
      if (this !== lastInput) return;

      // Estamos en el último input de la última fila => agregar fila nueva
      e.preventDefault();

      if ($("#fac-add-item").length) {
        $("#fac-add-item").trigger("click");
      } else if (typeof window.addItemRow === "function") {
        window.addItemRow({ cantidad: 1, precio_unit: 0, bonifica: 0 });
      } else {
        console.warn("No existe #fac-add-item ni addItemRow() para agregar una nueva línea.");
        return;
      }

      // foco al primer input de la nueva fila
      const $newLastRow = $("#fac-items-table tbody tr").last();
      const $focus =
        $newLastRow.find("input.fac-codigo").first().length
          ? $newLastRow.find("input.fac-codigo").first()
          : $newLastRow.find("input").first();
      if ($focus.length) {
  const $wrap = $("#fac-items-table").closest(".table-wrap");

  if ($wrap.length) {
    $wrap.animate({
      scrollTop: $wrap.scrollTop() + $newLastRow.position().top
    }, 200);
  } else {
    $("html, body").animate({
      scrollTop: $newLastRow.offset().top - 120
    }, 200);
  }

  $focus.focus();
}
    });
    // =============================
// VISOR PDF FACTURA
// =============================

$(document).on("click", ".fac-pdf", function(){

  const file = $(this).data("file");

  const url = "/uploads/facturas/" + file.replace(/^.*[\\/]/,'') + "?t=" + Date.now();

  $("#pdf-frame").attr("src", url);

  $("#pdf-viewer-modal").show();

});

$("#pdf-close").on("click", function(){

  $("#pdf-viewer-modal").hide();

  $("#pdf-frame").attr("src","");

});
})();


// assets/js/core.js
// Núcleo: utilidades + API + helpers comunes

const CLIENTE = new URLSearchParams(window.location.search).get("cliente") || "demo";
window.API = "/tesoreria/api/ajax.php";

/* =========================
   UTILIDADES
========================= */

function money(n) {
  const v = Number(n || 0);
  return v.toLocaleString("es-AR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmt2(n) {
  const v = Number(n || 0);
  return v.toLocaleString("es-AR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function signedImporteByTipo(tipo, importe) {
  const t = String(tipo || "").toUpperCase().trim();
  const imp = Math.abs(Number(importe || 0));
  return (t === "NC") ? -imp : imp;
}

// Parse números estilo argentino (1.234,56)
function parseARS(v) {
  let s = String(v ?? "").trim();
  if (!s) return 0;

  s = s.replace(/[^\d,.-]/g, "");

  if (s.includes(",") && s.includes(".")) {
    s = s.replace(/\./g, "").replace(",", ".");
  } else if (s.includes(",")) {
    s = s.replace(",", ".");
  }

  const n = Number(s);
  return Number.isFinite(n) ? n : 0;
}

function escapeHtml(s) {
  return String(s ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function todayISO() {
  const d = new Date();
  const pad = (x) => String(x).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function alertErr(e) {
  alert(e?.message || String(e));
}

/* =========================
   API (AJAX)
========================= */

async function apiGet(action, params = {}) {
  const qs = new URLSearchParams({
    cliente: CLIENTE,
    action,
    ...params
  }).toString();

  const r = await fetch(`${API}?${qs}`, {
    headers: { "Accept": "application/json" }
  });

  const j = await r.json();
  if (!j.ok) throw new Error(j.error || "Error");
  return j.data;
}

async function apiPost(action, body = {}) {
  const r = await fetch(
    `${API}?cliente=${encodeURIComponent(CLIENTE)}&action=${encodeURIComponent(action)}`,
    {
      method: "POST",
      headers: { "Content-Type": "application/json", "Accept": "application/json" },
      body: JSON.stringify(body)
    }
  );

  const j = await r.json();
  if (!j.ok) throw new Error(j.error || "Error");
  return j.data;
}

async function apiPostForm(action, formData) {
  const r = await fetch(
    `${API}?cliente=${encodeURIComponent(CLIENTE)}&action=${encodeURIComponent(action)}`,
    {
      method: "POST",
      body: formData
    }
  );

  const j = await r.json();
  if (!j.ok) throw new Error(j.error || "Error");
  return j.data;
}

$(document).ready(async function () {
  if (typeof fillProveedorSelects === "function") {
    await fillProveedorSelects();
  }
});

/* =========================
   MODALES
========================= */

function showModal(sel) {
  $(sel).addClass("show");
  $("body").addClass("modal-open");
}

function hideModal(sel) {
  $(sel).removeClass("show");
  $("body").removeClass("modal-open");
}

/* =========================
   FORMATO NÚMERO DE COMPROBANTE
========================= */

function onlyDigits(s) {
  return String(s || "").replace(/\D/g, "");
}

function formatCbteNumberSoft(raw) {
  const d = onlyDigits(raw).slice(0, 13); // 5+8
  const pto = d.slice(0, 5);
  const num = d.slice(5, 13);
  if (num.length === 0) return pto;
  return `${pto}-${num}`;
}

function formatCbteNumberCanon(raw) {
  const d = onlyDigits(raw).slice(0, 13);
  const pto = d.slice(0, 5).padStart(5, "0");
  const num = d.slice(5, 13).padStart(8, "0");
  return `${pto}-${num}`;
}

function attachCbteMask() {
  const $n = $("#fac-numero");
  if (!$n.length) return;

  $n.off("input.cbtemask blur.cbtemask");

  $n.on("input.cbtemask", function () {
    this.value = formatCbteNumberSoft(this.value);
  });

  $n.on("blur.cbtemask", function () {
    this.value = formatCbteNumberCanon(this.value);
  });
}

/* =========================
   TABS
========================= */

$(document).ready(function () {
  $(".tab").on("click", function () {
    $(".tab").removeClass("active");
    $(this).addClass("active");
    const t = $(this).data("tab");
    $(".panel").removeClass("show");
    $(`#tab-${t}`).addClass("show");
  });
});

/* =========================
   HANDLERS GLOBALES
========================= */

// Prevenir envío de formularios
$(document).on("submit", "form", function (e) {
  e.preventDefault();
});

// Manejar Enter en inputs
$(document).on("keypress", "input", function (e) {
  if (e.which === 13) {
    e.preventDefault();
    $(this).blur();
  }
});
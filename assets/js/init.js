/* =========================
   MAIN INIT (tabs + carga inicial)
========================= */

$(document).ready(async function () {
  try {
    // Tabs
    $(".tab").on("click", function () {
      const tab = $(this).data("tab"); // proveedores|facturas|op
      $(".tab").removeClass("active");
      $(this).addClass("active");

      $(".panel").removeClass("show");
      $("#tab-" + tab).addClass("show");
    });

    // Cargar combos de proveedores (para Facturas y OP)
    if (typeof fillProveedorSelects === "function") {
      await fillProveedorSelects();
    }

    // Cargas iniciales (si existen funciones)
    if (typeof loadFacturas === "function") await loadFacturas();
    if (typeof loadOP === "function") await loadOP();

    // Dejar tab Proveedores activa por defecto (ya viene activa en HTML, pero por las dudas)
    $(".tab.active").trigger("click");

  } catch (e) {
    console.error("Init error:", e);
  }
});

// =============================
// CONFIG
// =============================

const DEBUG = false;
const RESPONSABLE_ID = 1;

let LAST_CAJA_ID = null;
let CAJA_ACTUAL_ID = null;


// =============================
// HELPERS
// =============================

function $(id){
  return document.getElementById(id);
}

function msg(t){
  const el = $('msg');
  if(el) el.textContent = t || '';
}

function debugLog(o){
  if(DEBUG) console.log(o);
}

function hoyLocalISO(){
  const d = new Date();
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
}

function resetVistaCaja(){
  LAST_CAJA_ID = null;

  if($('cc-id')) $('cc-id').textContent = '—';
  if($('cc-estado')) $('cc-estado').textContent = '—';
  if($('cc-fecha-ap')) $('cc-fecha-ap').textContent = '—';

  if($('cc-monto-inicial-view')) $('cc-monto-inicial-view').textContent = '0.00';
  if($('cc-gastos')) $('cc-gastos').textContent = '0.00';
  if($('cc-ing')) $('cc-ing').textContent = '0.00';
  if($('saldo')) $('saldo').textContent = '$ 0.00';
  if($('cc-diferencia')) $('cc-diferencia').textContent = '$ 0.00';

  if($('cc-monto-inicial')) $('cc-monto-inicial').value = '';
  if($('cierre-declarado')) $('cierre-declarado').value = '';
  if($('mov-concepto')) $('mov-concepto').value = '';
  if($('mov-importe')) $('mov-importe').value = '';
  if($('mov-ncomp')) $('mov-ncomp').value = '';
  if($('mov-tipo')) $('mov-tipo').value = 'GASTO';
  if($('mov-tcomp')) $('mov-tcomp').value = 'TICKET';

  const tb = $('mov-tbody');
  if(tb){
    tb.innerHTML = '<tr><td colspan="6">Sin movimientos</td></tr>';
  }

  if($('btn-abrir')) $('btn-abrir').disabled = false;
}


// =============================
// AJAX
// =============================

async function postCaja(data){

  const fd = new FormData();

  Object.keys(data).forEach(k=>{
    fd.append(k, data[k]);
  });

  const url = window.CAJA_AJAX_URL || "ajax_caja_chica.php";

  const r = await fetch(url, {
    method: 'POST',
    body: fd
  });

  const text = await r.text();

  let json;

  try{
    json = JSON.parse(text);
  }
  catch(e){
    console.error(text);
    throw new Error("Respuesta no JSON");
  }

  if(!json.ok){
    throw new Error(json.error || "Error backend");
  }

  return json;
}


// =============================
// RENDER CAJA
// =============================

function renderCaja(payload){

  const caja = payload?.caja;
  const tot  = payload?.totales || {};
  const movs = payload?.movimientos || [];

  if(caja){

    if($('cc-id')) $('cc-id').textContent = caja.id;
    if($('cc-estado')) $('cc-estado').textContent = caja.estado;
    if($('cc-fecha-ap')) $('cc-fecha-ap').textContent = caja.fecha_apertura;

    if($('cc-monto-inicial-view')){
      $('cc-monto-inicial-view').textContent =
        Number(caja.monto_inicial).toFixed(2);
    }

    if($('cc-diferencia')){
      $('cc-diferencia').textContent =
        '$ ' + Number(caja.diferencia_cierre || 0).toFixed(2);
    }
  }

  if($('cc-gastos'))
    $('cc-gastos').textContent =
      Number(tot.gastos || 0).toFixed(2);

  if($('cc-ing'))
    $('cc-ing').textContent =
      Number(tot.ingresos || 0).toFixed(2);

  if($('saldo')){
    $('saldo').textContent =
      '$ ' + Number(tot.saldo_calculado || 0).toFixed(2);
  }

  const tb = $('mov-tbody');

  if(!tb) return;

  tb.innerHTML = '';

  if(!movs.length){
    tb.innerHTML =
      `<tr><td colspan="6">Sin movimientos</td></tr>`;
    return;
  }

  movs.forEach(m=>{

    const tr = document.createElement('tr');
    const importe = Number(m.importe || 0);

    tr.innerHTML = `
      <td>${m.fecha}</td>
      <td>${m.tipo}</td>
      <td>${m.concepto}</td>
      <td class="right">$ ${importe.toFixed(2)}</td>
      <td>${(m.tipo_comprobante ?? '') + ' ' + (m.nro_comprobante ?? '')}</td>
      <td>
        <button class="btn ghost btn-corregir" data-id="${m.id}">
          Corregir
        </button>
      </td>
    `;

    tb.appendChild(tr);

  });

}


// =============================
// CAJA ACTUAL
// =============================

async function verCaja(){

  if(!LAST_CAJA_ID)
    throw new Error("No hay caja abierta");

  const r = await postCaja({
    get_caja: 1,
    caja_id: LAST_CAJA_ID
  });

  renderCaja(r.data);
}


// =============================
// DETECTAR CAJA ABIERTA
// =============================

async function detectarCajaAbierta(){

  try{

    const r = await postCaja({
      get_caja_abierta: 1,
      responsable_id: RESPONSABLE_ID
    });

    if(r?.data?.caja_id){

      CAJA_ACTUAL_ID = r.data.caja_id;
      LAST_CAJA_ID = r.data.caja_id;

      if($('btn-abrir'))
        $('btn-abrir').disabled = true;

      await verCaja();

    } else {
      CAJA_ACTUAL_ID = null;
    }

  } catch(e){
    debugLog(e.message);
  }

}


// =============================
// ABRIR CAJA
// =============================

async function abrirCaja(){

  const monto =
    parseFloat($('cc-monto-inicial')?.value || 0);

  if(!(monto > 0))
    throw new Error("Monto inicial inválido");

  const r = await postCaja({
    crear_caja: 1,
    responsable_id: RESPONSABLE_ID,
    fecha_apertura: hoyLocalISO(),
    monto_inicial: monto
  });

  LAST_CAJA_ID = r.data.caja_id;
  CAJA_ACTUAL_ID = r.data.caja_id;

  $('cc-monto-inicial').value = '';

  if($('btn-abrir'))
    $('btn-abrir').disabled = true;

  await verCaja();
}


// =============================
// AGREGAR MOVIMIENTO
// =============================

async function agregarMovDesdeForm(){

  if(!LAST_CAJA_ID)
    throw new Error("No hay caja abierta");

  const concepto =
    $('mov-concepto')?.value.trim();

  const importe =
    parseFloat($('mov-importe')?.value || 0);

  const tipo =
    $('mov-tipo')?.value || 'GASTO';

  if(!concepto)
    throw new Error("Falta concepto");

  if(!(importe > 0))
    throw new Error("Importe inválido");

  const nro_comp = $('mov-ncomp')?.value || '';
  const tipo_comp = $('mov-tcomp')?.value || '';

  await postCaja({
    agregar_mov: 1,
    caja_id: LAST_CAJA_ID,
    fecha: hoyLocalISO(),
    tipo: tipo,
    concepto: concepto,
    importe: importe,
    nro_comprobante: nro_comp,
    tipo_comprobante: tipo_comp
  });

  $('mov-concepto').value = '';
  $('mov-importe').value = '';
  $('mov-ncomp').value = '';
  $('mov-tcomp').value = 'TICKET';

  $('mov-concepto').focus();

  await verCaja();
}


// =============================
// CERRAR CAJA
// =============================

async function cerrarCajaDesdeForm(){

  if(!LAST_CAJA_ID)
    throw new Error("No hay caja abierta");

  const monto = parseFloat($('cierre-declarado')?.value);

  if (isNaN(monto) || monto < 0) {
    msg("Ingrese el dinero contado para cerrar caja");
    return;
  }

  const r = await postCaja({
    cerrar_caja: 1,
    caja_id: LAST_CAJA_ID,
    monto_final_declarado: monto
  });

  $('cierre-declarado').value = '';

  const d = r.data || {};

  let leyenda = 'Sin diferencias';

  if (d.tipo_ajuste === 'FALTANTE') {
    leyenda = `Faltante de caja: ${Number(d.importe_ajuste || 0).toFixed(2)}`;
  } else if (d.tipo_ajuste === 'SOBRANTE') {
    leyenda = `Sobrante de caja: ${Number(d.importe_ajuste || 0).toFixed(2)}`;
  }

  resetVistaCaja();

  msg(
    `Caja cerrada | Teórico: ${Number(d.saldo_teorico || 0).toFixed(2)} | ` +
    `Declarado: ${Number(d.monto_final_declarado || monto).toFixed(2)} | ` +
    `${leyenda}`
  );

  await detectarCajaAbierta();
}

// =============================
// HISTORICO
// =============================

async function cargarHistorial(){

  const r = await postCaja({
    listar_cajas: 1
  });

  renderListadoCajas(r.data.cajas || []);
}

function renderListadoCajas(cajas){

  const tb = $('hist-tbody');

  if(!tb) return;

  tb.innerHTML = '';

  if(!cajas.length){
    tb.innerHTML = '<tr><td colspan="7">Sin cajas</td></tr>';
    return;
  }

  cajas.forEach(c=>{

    const tr = document.createElement('tr');

    tr.innerHTML = `
      <td>${c.id}</td>
      <td>${c.fecha_apertura}</td>
      <td>${c.fecha_cierre ?? '-'}</td>
      <td>$ ${Number(c.monto_inicial).toFixed(2)}</td>
      <td>$ ${Number(c.monto_final_declarado ?? 0).toFixed(2)}</td>
      <td>$ ${Number(c.diferencia_cierre ?? 0).toFixed(2)}</td>
      <td>
        <button class="btn ghost" onclick="verCajaHistorica(${c.id})">
          Ver movimientos
        </button>
      </td>
    `;

    tb.appendChild(tr);

  });

}

async function verCajaHistorica(id){
  LAST_CAJA_ID = id;
  await verCaja();
}

async function volverCajaActual(){
  if(CAJA_ACTUAL_ID){
    LAST_CAJA_ID = CAJA_ACTUAL_ID;
    await verCaja();
    msg('Mostrando caja actual');
  } else {
    msg('No hay caja abierta');
  }
}


// =============================
// BOTONES
// =============================

function wireUI(){

  if($('btn-abrir')){
    $('btn-abrir').onclick = async ()=>{
      msg('Abriendo...');
      try{
        await abrirCaja();
        msg('Caja abierta');
      }
      catch(e){
        msg(e.message);
      }
    };
  }

  if($('btn-agregar')){
    $('btn-agregar').onclick = async ()=>{
      msg('Guardando...');
      try{
        await agregarMovDesdeForm();
        msg('Movimiento agregado');
      }
      catch(e){
        msg(e.message);
      }
    };
  }

  if($('btn-cerrar')){
    $('btn-cerrar').onclick = async ()=>{
      msg('Cerrando...');
      try{
        await cerrarCajaDesdeForm();
      }
      catch(e){
        msg(e.message);
      }
    };
  }

  if($('btn-historial')){
    $('btn-historial').onclick = async ()=>{
      try{
        await cargarHistorial();
        msg('Histórico cargado');
      }
      catch(e){
        msg(e.message);
      }
    };
  }

  if($('btn-reset-caja')){
    $('btn-reset-caja').onclick = ()=>{
      resetVistaCaja();
      msg('Vista puesta en cero');
    };
  }

  document.addEventListener("click", async function(e){

  if(e.target.classList.contains("btn-corregir")){

    const id = parseInt(e.target.dataset.id, 10);

    if(!id){
      msg("Movimiento inválido");
      return;
    }

    if(!confirm("¿Desea corregir este movimiento?"))
      return;

    try{
      msg("Corrigiendo...");

      await postCaja({
        corregir_mov: 1,
        mov_id: id
      });

      msg(`Movimiento #${id} corregido`);

      await verCaja();
    }
    catch(err){
      msg(err.message || "Error al corregir movimiento");
    }
  }

});

}


// =============================
// INIT
// =============================

window.addEventListener('DOMContentLoaded', async ()=>{

  wireUI();
  await detectarCajaAbierta();

});
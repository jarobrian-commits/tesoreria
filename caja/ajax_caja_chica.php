<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION["usuario"])) {
  http_response_code(401);
  echo json_encode([
    "ok" => false,
    "error" => "Sesión no válida"
  ]);
  exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../api/log.php';
require_once __DIR__ . '/../api/db.php';

$pdo = db();

if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "error" => "No se detectó PDO"
  ], JSON_UNESCAPED_UNICODE);
  exit;
}
/* =========================
   HELPERS (compatibles)
========================= */
function json_ok($data = null, string $msg = 'OK'): void {
  echo json_encode(["ok" => true, "msg" => $msg, "data" => $data], JSON_UNESCAPED_UNICODE);
  exit;
}

function json_err(string $msg, int $code = 400, $extra = null): void {
  http_response_code($code);
  $out = ["ok" => false, "error" => $msg];
  if ($extra !== null) $out["data"] = $extra;
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
  exit;
}

// Tu proyecto parece usar jexit() y current_user_id(). Si no existen, los definimos.
if (!function_exists('jexit')) {
  function jexit(bool $ok, string $msg, $data = null): void {
    if ($ok) json_ok($data, $msg);
    json_err($msg, 400, $data);
  }
}

if (!function_exists('current_user_id')) {
  function current_user_id(): ?int {
    // Ajustá esto si tu sesión usa otro nombre
    foreach (['user_id','usuario_id','id_usuario','uid'] as $k) {
      if (isset($_SESSION[$k]) && is_numeric($_SESSION[$k])) return (int)$_SESSION[$k];
    }
    // fallback: si viene por POST
    if (isset($_POST['user_id']) && is_numeric($_POST['user_id'])) return (int)$_POST['user_id'];
    return null;
  }
}

function table_exists(PDO $pdo, string $table): bool {
  $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
  $stmt->execute([$table]);
  return (bool)$stmt->fetchColumn();
}

function column_exists(PDO $pdo, string $table, string $col): bool {
  $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
  $stmt->execute([$table, $col]);
  return (bool)$stmt->fetchColumn();
}

function get_caja_for_update(PDO $pdo, int $caja_id): array {
  $q = $pdo->prepare("SELECT * FROM caja_chica WHERE id=? FOR UPDATE");
  $q->execute([$caja_id]);
  $caja = $q->fetch(PDO::FETCH_ASSOC);
  return $caja ?: [];
}

function caja_estado(PDO $pdo, int $caja_id): ?string {
  $q = $pdo->prepare("SELECT estado FROM caja_chica WHERE id=?");
  $q->execute([$caja_id]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  return $row['estado'] ?? null;
}

function saldo_calculado(PDO $pdo, int $caja_id): float {
  // Caja chica: NO hay aprobado/rechazado. El saldo se calcula con TODOS los movimientos no anulados.
  $has_anulado = column_exists($pdo, 'caja_chica_mov', 'anulado');
  $sql = "
    SELECT
      c.monto_inicial
      + IFNULL(SUM(CASE WHEN m.tipo='INGRESO' THEN m.importe ELSE 0 END),0)
      - IFNULL(SUM(CASE WHEN m.tipo='GASTO'   THEN m.importe ELSE 0 END),0)
      AS saldo
    FROM caja_chica c
    LEFT JOIN caja_chica_mov m
      ON m.caja_id = c.id
     " . ($has_anulado ? " AND m.anulado = 0" : "") . "
    WHERE c.id = ?
    GROUP BY c.id
  ";
  $q = $pdo->prepare($sql);
  $q->execute([$caja_id]);
  $v = $q->fetchColumn();
  return $v === false || $v === null ? 0.0 : (float)$v;
}

/* =========================
   ENDPOINTS (flags POST)
========================= */

// 1) CREAR CAJA (ABIERTA)
if (isset($_POST['crear_caja'])) {

  $fecha        = $_POST['fecha_apertura'] ?? null; // 'YYYY-MM-DD'
  $responsable  = (int)($_POST['responsable_id'] ?? 0);
  $monto        = (float)($_POST['monto_inicial'] ?? 0);
  $descripcion  = $_POST['descripcion'] ?? null;
  $moneda       = $_POST['moneda'] ?? 'ARS';

  if (!$fecha || !$responsable) {
    jexit(false, 'Faltan datos obligatorios (fecha_apertura, responsable_id)');
  }

  // Validar que no haya caja ABIERTA para ese responsable
  $q = $pdo->prepare("SELECT id FROM caja_chica WHERE responsable_id=? AND estado='ABIERTA' LIMIT 1");
  $q->execute([$responsable]);
  if ($q->fetchColumn()) {
    jexit(false, 'Ya existe una caja ABIERTA para ese responsable');
  }

  $sql = "INSERT INTO caja_chica
          (fecha_apertura, responsable_id, monto_inicial, descripcion, moneda, estado, created_by)
          VALUES (?,?,?,?,?,'ABIERTA',?)";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    $fecha,
    $responsable,
    $monto,
    $descripcion,
    $moneda,
    current_user_id()
  ]);

  $caja_id = $pdo->lastInsertId();

  // LOG
  $usuario = $_SESSION["usuario"] ?? "sistema";
  registrar_log($usuario,"Apertura de caja ID ".$caja_id,"caja_chica");

  jexit(true, 'Caja creada', [
    'caja_id' => (int)$caja_id
  ]);
}

// 2) AGREGAR MOVIMIENTO (por defecto PENDIENTE)
if (isset($_POST['agregar_mov'])) {

  $caja_id   = (int)($_POST['caja_id'] ?? 0);
  $fecha     = $_POST['fecha'] ?? null; // 'YYYY-MM-DD'
  $tipo      = $_POST['tipo'] ?? 'GASTO'; // GASTO | INGRESO
  $concepto  = trim((string)($_POST['concepto'] ?? ''));
  $importe   = (float)($_POST['importe'] ?? 0);

  // Campos opcionales
  $tipo_comprobante = $_POST['tipo_comprobante'] ?? null;
  $nro_comprobante  = $_POST['nro_comprobante'] ?? null;
  $proveedor_id     = isset($_POST['proveedor_id']) && $_POST['proveedor_id'] !== '' ? (int)$_POST['proveedor_id'] : null;
  $cuenta_contable_id = isset($_POST['cuenta_contable_id']) && $_POST['cuenta_contable_id'] !== '' ? (int)$_POST['cuenta_contable_id'] : null;
  $centro_costo_id  = isset($_POST['centro_costo_id']) && $_POST['centro_costo_id'] !== '' ? (int)$_POST['centro_costo_id'] : null;
  $observaciones    = $_POST['observaciones'] ?? null;

  if (!$caja_id || !$fecha || $concepto === '' || $importe <= 0) {
    jexit(false, 'Datos inválidos (caja_id, fecha, concepto, importe)');
  }

  if (!in_array($tipo, ['GASTO','INGRESO'], true)) {
    jexit(false, 'Tipo inválido (GASTO/INGRESO)');
  }

  // validar caja abierta
  $estado = caja_estado($pdo, $caja_id);
  if ($estado !== 'ABIERTA') {
    jexit(false, 'La caja no está ABIERTA');
  }

  $cols = ['caja_id','fecha','tipo','concepto','importe','created_by'];
  $vals = [$caja_id, $fecha, $tipo, $concepto, $importe, current_user_id()];

  if (column_exists($pdo,'caja_chica_mov','estado')) {
    $cols[] = 'estado';
    $vals[] = 'APROBADO';
  }

  if ($tipo_comprobante !== null && column_exists($pdo,'caja_chica_mov','tipo_comprobante')) {
    $cols[] = 'tipo_comprobante'; $vals[] = $tipo_comprobante;
  }

  if ($nro_comprobante !== null && column_exists($pdo,'caja_chica_mov','nro_comprobante')) {
    $cols[] = 'nro_comprobante'; $vals[] = $nro_comprobante;
  }

  if ($proveedor_id !== null && column_exists($pdo,'caja_chica_mov','proveedor_id')) {
    $cols[] = 'proveedor_id'; $vals[] = $proveedor_id;
  }

  if ($cuenta_contable_id !== null && column_exists($pdo,'caja_chica_mov','cuenta_contable_id')) {
    $cols[] = 'cuenta_contable_id'; $vals[] = $cuenta_contable_id;
  }

  if ($centro_costo_id !== null && column_exists($pdo,'caja_chica_mov','centro_costo_id')) {
    $cols[] = 'centro_costo_id'; $vals[] = $centro_costo_id;
  }

  if ($observaciones !== null && column_exists($pdo,'caja_chica_mov','observaciones')) {
    $cols[] = 'observaciones'; $vals[] = $observaciones;
  }

  $placeholders = implode(',', array_fill(0, count($cols), '?'));
  $sql = "INSERT INTO caja_chica_mov (" . implode(',', $cols) . ") VALUES ($placeholders)";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($vals);

  $mov_id = $pdo->lastInsertId();

  // LOG (DESPUÉS DE GUARDAR)
  $usuario = $_SESSION["usuario"] ?? "sistema";
  registrar_log($usuario,"Movimiento caja chica ID ".$mov_id,"caja_chica");

  jexit(true, 'Movimiento agregado', [
    'mov_id' => (int)$mov_id
  ]);
}
// 5) REPONER CAJA (reposicion + mov INGRESO APROBADO + link opcional mov_id)
if (isset($_POST['reponer_caja'])) {

  $caja_id = (int)($_POST['caja_id'] ?? 0);
  $fecha   = $_POST['fecha'] ?? date('Y-m-d');
  $importe = (float)($_POST['importe'] ?? 0);
  $medio   = $_POST['medio'] ?? 'EFECTIVO';
  $referencia = $_POST['referencia'] ?? null;
  $observaciones = $_POST['observaciones'] ?? null;
  $asiento_id = isset($_POST['asiento_id']) && $_POST['asiento_id'] !== '' ? (int)$_POST['asiento_id'] : null;

  if (!$caja_id || $importe <= 0) jexit(false, 'Datos inválidos (caja_id, importe)');

  $estado = caja_estado($pdo, $caja_id);
  if ($estado !== 'ABIERTA') jexit(false, 'La caja no está ABIERTA');

  if (!table_exists($pdo,'caja_chica_reposicion')) {
    jexit(false, 'No existe la tabla caja_chica_reposicion');
  }

  $has_mov_link = column_exists($pdo,'caja_chica_reposicion','mov_id');

  try {
    $pdo->beginTransaction();

    // Insert reposición
    $cols = ['caja_id','fecha','importe','medio','created_by'];
    $vals = [$caja_id,$fecha,$importe,$medio,current_user_id()];

    if ($referencia !== null && column_exists($pdo,'caja_chica_reposicion','referencia')) { $cols[]='referencia'; $vals[]=$referencia; }
    if ($observaciones !== null && column_exists($pdo,'caja_chica_reposicion','observaciones')) { $cols[]='observaciones'; $vals[]=$observaciones; }
    if ($asiento_id !== null && column_exists($pdo,'caja_chica_reposicion','asiento_id')) { $cols[]='asiento_id'; $vals[]=$asiento_id; }

    $ph = implode(',', array_fill(0,count($cols),'?'));
    $pdo->prepare("INSERT INTO caja_chica_reposicion (".implode(',',$cols).") VALUES ($ph)")->execute($vals);
    $repos_id = (int)$pdo->lastInsertId();

    // Insert movimiento INGRESO APROBADO
    $concepto = "Reposición ($medio)";
    $movCols = ['caja_id','fecha','tipo','concepto','importe','estado','created_by'];
    $movVals = [$caja_id,$fecha,'INGRESO',$concepto,$importe,'APROBADO',current_user_id()];

    if (column_exists($pdo,'caja_chica_mov','tipo_comprobante')) {
      $movCols[]='tipo_comprobante'; $movVals[]='RECIBO';
    }
    if ($referencia !== null && column_exists($pdo,'caja_chica_mov','nro_comprobante')) {
      $movCols[]='nro_comprobante'; $movVals[]=$referencia;
    }
    if ($observaciones !== null && column_exists($pdo,'caja_chica_mov','observaciones')) {
      $movCols[]='observaciones'; $movVals[]=$observaciones;
    }

    $ph2 = implode(',', array_fill(0,count($movCols),'?'));
    $pdo->prepare("INSERT INTO caja_chica_mov (".implode(',',$movCols).") VALUES ($ph2)")->execute($movVals);
    $mov_id = (int)$pdo->lastInsertId();

    // Link opcional
    if ($has_mov_link) {
      $pdo->prepare("UPDATE caja_chica_reposicion SET mov_id=? WHERE id=?")->execute([$mov_id,$repos_id]);
    }
$pdo->commit();

$usuario = $_SESSION["usuario"] ?? "sistema";
registrar_log($usuario,"Reposición caja chica ID ".$repos_id,"caja_chica");

jexit(true, 'Reposición registrada', [
      'reposicion_id' => $repos_id,
      'mov_id' => $mov_id
    ]);

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jexit(false, 'Error en reposición: ' . $e->getMessage());
  }
}

if (isset($_POST['corregir_mov'])) {

    $mov_id = (int)($_POST['mov_id'] ?? 0);
    if (!$mov_id) {
        jexit(false, 'Movimiento inválido');
    }

    $sql = "SELECT * FROM caja_chica_mov WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$mov_id]);
    $mov = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mov) {
        jexit(false, 'Movimiento no encontrado');
    }

    $estado = caja_estado($pdo, (int)$mov['caja_id']);
    if ($estado !== 'ABIERTA') {
        jexit(false, 'Solo se pueden corregir movimientos de una caja ABIERTA');
    }

    $tipo_original = strtoupper((string)$mov['tipo']);
    if (!in_array($tipo_original, ['GASTO', 'INGRESO'], true)) {
        jexit(false, 'Solo se pueden corregir movimientos GASTO o INGRESO');
    }

    $tipo_reverso = $tipo_original === 'GASTO' ? 'INGRESO' : 'GASTO';
    $importe_reverso = abs((float)$mov['importe']);

    $sql = "INSERT INTO caja_chica_mov
            (caja_id, fecha, tipo, concepto, importe, tipo_comprobante, nro_comprobante, created_by)
            VALUES (?,?,?,?,?,?,?,?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $mov['caja_id'],
        date('Y-m-d'),
        $tipo_reverso,
        'Reverso mov ' . $mov_id,
        $importe_reverso,
        $mov['tipo_comprobante'] ?? null,
        $mov['nro_comprobante'] ?? null,
        current_user_id()
    ]);

    $nuevo_id = (int)$pdo->lastInsertId();

    $usuario = $_SESSION["usuario"] ?? "sistema";
    registrar_log($usuario, "Corrección movimiento caja chica ID ".$mov_id." reversado con mov ".$nuevo_id, "caja_chica");

    jexit(true, 'Movimiento corregido', [
        'mov_original' => $mov_id,
        'mov_reverso' => $nuevo_id
    ]);
}
// ============================
// HISTORICO DE CAJAS
// ============================
if (isset($_POST['listar_cajas'])) {

    $sql = "
        SELECT
            id,
            fecha_apertura,
            fecha_cierre,
            monto_inicial,
            monto_final_declarado,
            diferencia_cierre,
            estado
        FROM caja_chica
        ORDER BY id DESC
        LIMIT 100
    ";

    $st = $pdo->query($sql);

    $cajas = $st->fetchAll(PDO::FETCH_ASSOC);

    jexit(true, 'OK', [
        "cajas" => $cajas
    ]);
}

// 6) GET CAJA (saldo + movimientos) sin vista vw
if (isset($_POST['get_caja'])) {

  $caja_id = (int)($_POST['caja_id'] ?? 0);
  if (!$caja_id) jexit(false, 'Caja inválida');

  $q = $pdo->prepare("SELECT * FROM caja_chica WHERE id=?");
  $q->execute([$caja_id]);
  $caja = $q->fetch(PDO::FETCH_ASSOC);
  if (!$caja) jexit(false, 'Caja inexistente');

  // movimientos
  $q = $pdo->prepare("
    SELECT *
    FROM caja_chica_mov
    WHERE caja_id=?
    ORDER BY fecha DESC, id DESC
  ");
  $q->execute([$caja_id]);
  $movs = $q->fetchAll(PDO::FETCH_ASSOC);

  // totales (sin aprobaciones)
  $q = $pdo->prepare("
    SELECT
      IFNULL(SUM(CASE WHEN tipo='GASTO' THEN importe ELSE 0 END),0) gastos,
      IFNULL(SUM(CASE WHEN tipo='INGRESO' THEN importe ELSE 0 END),0) ingresos
    FROM caja_chica_mov
    WHERE caja_id=?
  ");
  $q->execute([$caja_id]);
  $tot = $q->fetch(PDO::FETCH_ASSOC) ?: ['gastos'=>0,'ingresos'=>0];

  $saldo = saldo_calculado($pdo, $caja_id);

  jexit(true, 'OK', [
    'caja' => $caja,
    'totales' => [
      'gastos' => (float)$tot['gastos'],
      'ingresos' => (float)$tot['ingresos'],
      'saldo_calculado' => $saldo,
    ],
    'movimientos' => $movs
  ]);
}
// 0) GET CAJA ABIERTA POR RESPONSABLE
if (isset($_POST['get_caja_abierta'])) {

  $responsable  = (int)($_POST['responsable_id'] ?? 0);
  if (!$responsable) jexit(false, 'Falta responsable_id');

  $q = $pdo->prepare("
    SELECT id
    FROM caja_chica
    WHERE responsable_id=? AND estado='ABIERTA'
    ORDER BY id DESC
    LIMIT 1
  ");
  $q->execute([$responsable]);
  $id = $q->fetchColumn();

  jexit(true, 'OK', [
    'caja_id' => $id ? (int)$id : null
  ]);
}


// 7) CERRAR CAJA (guarda cierre en caja_chica si existen columnas, y cambia estado)
if (isset($_POST['cerrar_caja'])) {

  $caja_id = (int)($_POST['caja_id'] ?? 0);
  if (!$caja_id) jexit(false, 'Caja inválida');

  // Compat: puede venir como monto_final o monto_final_declarado
  $decl = null;
  if (isset($_POST['monto_final_declarado'])) $decl = (float)$_POST['monto_final_declarado'];
  elseif (isset($_POST['monto_final'])) $decl = (float)$_POST['monto_final'];
  elseif (isset($_POST['saldo_final'])) $decl = (float)$_POST['saldo_final'];

  if ($decl === null) {
    jexit(false, 'Falta monto_final_declarado (o monto_final)');
  }

  // Caja chica: no existe "pendiente", así que no bloqueamos el cierre.

  $obs = $_POST['observaciones_cierre'] ?? null;

  // Verificar columnas de cierre (ALTERs)
  $needs = ['fecha_cierre','monto_final_declarado','diferencia_cierre','observaciones_cierre','closed_by'];
  $missing = [];
  foreach ($needs as $c) {
    if (!column_exists($pdo,'caja_chica',$c)) $missing[] = $c;
  }

  try {
    $pdo->beginTransaction();

    $caja = get_caja_for_update($pdo, $caja_id);
    if (!$caja || ($caja['estado'] ?? '') !== 'ABIERTA') {
      $pdo->rollBack();
      jexit(false, 'La caja no está ABIERTA');
    }

    $saldo_teorico = saldo_calculado($pdo, $caja_id);
$dif = $decl - $saldo_teorico;

$ajuste_generado = false;
$tipo_ajuste = 'NINGUNO';
$importe_ajuste = 0.0;

// ==============================
// GENERAR AJUSTE AUTOMÁTICO
// ==============================
if (abs($dif) > 0.009) {

  $tipo = $dif > 0 ? 'INGRESO' : 'GASTO';

  $concepto = $dif > 0
    ? 'Sobrante de caja'
    : 'Faltante de caja';

  $importe = abs($dif);

  $sql = "INSERT INTO caja_chica_mov
          (caja_id, fecha, tipo, concepto, importe, created_by)
          VALUES (?, CURDATE(), ?, ?, ?, ?)";

  $pdo->prepare($sql)->execute([
    $caja_id,
    $tipo,
    $concepto,
    $importe,
    current_user_id()
  ]);

  $ajuste_generado = true;
  $tipo_ajuste = $dif > 0 ? 'SOBRANTE' : 'FALTANTE';
  $importe_ajuste = $importe;
}

$saldo_final_ajustado = saldo_calculado($pdo, $caja_id);

    // Si están las columnas, guardamos todo. Si no, cerramos igual y avisamos.
    if (count($missing) === 0) {
      $sql = "UPDATE caja_chica
              SET estado='CERRADA',
                  fecha_cierre=NOW(),
                  monto_final_declarado=?,
                  diferencia_cierre=?,
                  observaciones_cierre=?,
                  closed_by=?
              WHERE id=? AND estado='ABIERTA'";
      $pdo->prepare($sql)->execute([$decl, $dif, $obs, current_user_id(), $caja_id]);
    } else {
      $pdo->prepare("UPDATE caja_chica SET estado='CERRADA' WHERE id=? AND estado='ABIERTA'")
          ->execute([$caja_id]);
    }

    $pdo->commit();

$usuario = $_SESSION["usuario"] ?? "sistema";
registrar_log($usuario,"Cierre de caja ID ".$caja_id,"caja_chica");

jexit(true, 'Caja cerrada', [
  'saldo_teorico' => round($saldo_teorico, 2),
  'saldo_final_ajustado' => round($saldo_final_ajustado, 2),
  'monto_final_declarado' => round($decl, 2),
  'diferencia_cierre' => round($dif, 2),
  'ajuste_generado' => $ajuste_generado,
  'tipo_ajuste' => $tipo_ajuste,
  'importe_ajuste' => round($importe_ajuste, 2),
  'warning' => count($missing) ? ('Faltan columnas de cierre en caja_chica: ' . implode(', ', $missing)) : null
]);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jexit(false, 'Error al cerrar caja: ' . $e->getMessage());
  }
 
}

// Si no se pidió nada:
json_err('Acción no reconocida. Enviá uno de estos flags: crear_caja, agregar_mov, reponer_caja, get_caja, get_caja_abierta, cerrar_caja', 400);

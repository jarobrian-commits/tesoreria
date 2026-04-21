<?php
declare(strict_types=1);

session_start();
if (empty($_SESSION["logueado"]) || empty($_SESSION["usuario"]) || empty($_SESSION["cliente"])) {
    json_err("Sesión no válida",401);
}

$usuario = $_SESSION["usuario"] ?? "sistema";

require_once __DIR__ . "/log.php";
require_once 'db.php';

$action = $_GET["action"] ?? $_POST["action"] ?? "";
if ($action === "") json_err("Falta parámetro 'action'");

if (!in_array($action, ['proveedores_export', 'facturas_export'], true)) {
    header("Content-Type: application/json; charset=utf-8");
}



function sin_tildes($cadena) {
    $buscar = ['á','é','í','ó','ú','Á','É','Í','Ó','Ú','ñ','Ñ'];
    $reemplazar = ['a','e','i','o','u','A','E','I','O','U','n','N'];
    return str_replace($buscar, $reemplazar, $cadena);
}
/* =========================
   HELPER FUNCTIONS
========================= */
function validar_cuit(string $cuit): bool {
    $cuit = preg_replace('/\D+/', '', $cuit);
    if (strlen($cuit) !== 11) return false;

    $mult = [5,4,3,2,7,6,5,4,3,2];
    $suma = 0;

    for ($i = 0; $i < 10; $i++) {
        $suma += ((int)$cuit[$i]) * $mult[$i];
    }

    $mod = 11 - ($suma % 11);
    if ($mod === 11) $mod = 0;
    if ($mod === 10) $mod = 9;

    return $mod === (int)$cuit[10];
}

function validar_email_basico(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
/**
 * Retorna respuesta JSON exitosa
 */
function json_ok($data = null): void {
    echo json_encode(["ok" => true, "data" => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Retorna respuesta JSON con error
 */
function json_err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(["ok" => false, "error" => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Convierte string a float manejando formatos locales
 */
function toFloat($v): float {
    if ($v === null || $v === '') return 0.0;
    if (is_numeric($v)) return (float)$v;
    
    $s = trim((string)$v);
    $s = preg_replace('/[^\d,.\-]/', '', $s);
    
    // Manejar formato argentino (1.234,56)
    if (str_contains($s, ",") && str_contains($s, ".")) {
        $s = str_replace(".", "", $s); // Eliminar separadores de miles
        $s = str_replace(",", ".", $s); // Convertir coma decimal a punto
    } elseif (str_contains($s, ",")) {
        $s = str_replace(",", ".", $s); // Solo coma decimal
    }
    
    $n = (float)$s;
    return is_finite($n) ? $n : 0.0;
}

/**
 * Aplica signo según tipo de comprobante
 */
function signedByTipo(string $tipo, float $importe): float {
    $t = strtoupper(trim($tipo));
    $imp = abs($importe);
    return ($t === "NC") ? -$imp : $imp;
}

/**
 * Obtiene y decodifica JSON del body de la petición
 */
function input_json(): array {
    $raw = file_get_contents("php://input");
    if (!$raw) return [];
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}

/**
 * Maneja errores de duplicado en base de datos
 */
function handleDuplicateError(PDOException $e, string $customMessage = null): void {
    if ($e->errorInfo[1] == 1062) { // MySQL/MariaDB duplicate key error
        $msg = $customMessage ?? "El registro ya existe en la base de datos";
        json_err($msg);
    }
    throw $e;
}

/* =========================
   MAIN ROUTER
========================= */



try {
    $pdo = db();
    
    // ==================== PROVEEDORES ====================
    
    if ($action === "proveedores_list") {
  $q = trim($_GET["q"] ?? "");
  $estado = $_GET["estado"] ?? "activos"; // activos|all|inactivos

  $qDigits = preg_replace("/\D+/", "", $q);

  $whereEstado = "";
  if ($estado === "activos")   $whereEstado = " AND activo = 1 ";
  if ($estado === "inactivos") $whereEstado = " AND activo = 0 ";

  $sql = "
  SELECT id_proveedor, razon_social, cuit, condicion_iva, email, telefono, domicilio, activo, created_at
  FROM proveedores
  WHERE 1=1
    $whereEstado
    AND (
      razon_social LIKE :likeText
      OR (:qDigits <> '' AND cuit LIKE :likeCuit)
      OR (:qDigits = '' AND cuit LIKE :likeText)
    )
  ORDER BY created_at DESC, id_proveedor DESC
  LIMIT 200
";

  $st = $pdo->prepare($sql);
  $st->execute([
    ":likeText" => "%$q%",
    ":qDigits"  => $qDigits,
    ":likeCuit" => "%$qDigits%"
  ]);

  json_ok($st->fetchAll(PDO::FETCH_ASSOC));
}

if ($action === "proveedores_select") {
    $q = trim($_GET["q"] ?? "");
    $estado = $_GET["estado"] ?? "activos"; // activos | all | inactivos
    $qDigits = preg_replace("/\D+/", "", $q);

    $where = " WHERE 1=1 ";
    $params = [];

    if ($estado === "activos") {
        $where .= " AND p.activo = 1 ";
    } elseif ($estado === "inactivos") {
        $where .= " AND p.activo = 0 ";
    }

    if ($q !== "") {
        $where .= " AND (
            p.razon_social LIKE :likeText
            OR p.cuit LIKE :likeDigits
        ) ";
        $params[":likeText"] = "%$q%";
        $params[":likeDigits"] = ($qDigits !== "" ? "%$qDigits%" : "%$q%");
    }

    $sql = "
        SELECT
            p.id_proveedor,
            p.razon_social,
            p.cuit,
            p.condicion_iva,
            p.activo
        FROM proveedores p
        $where
        ORDER BY p.razon_social ASC
        LIMIT 200
    ";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    json_ok($rows);
}

if ($action === "proveedores_export") {
    registrar_log($usuario, "Exportación proveedores", "proveedores");

    $q = trim($_GET["q"] ?? "");
    $estado = $_GET["estado"] ?? "activos"; // activos|all|inactivos
    $qDigits = preg_replace("/\D+/", "", $q);

    $whereEstado = "";
    if ($estado === "activos")   $whereEstado = " AND activo = 1 ";
    if ($estado === "inactivos") $whereEstado = " AND activo = 0 ";

    $sql = "
        SELECT
            id_proveedor,
            razon_social,
            cuit,
            condicion_iva,
            email,
            telefono,
            domicilio,
            activo
        FROM proveedores
        WHERE 1=1
          $whereEstado
          AND (
            razon_social LIKE :likeText
            OR (:qDigits <> '' AND cuit LIKE :likeCuit)
            OR (:qDigits = '' AND cuit LIKE :likeText)
          )
        ORDER BY created_at DESC, id_proveedor DESC
    ";

    $st = $pdo->prepare($sql);
    $st->execute([
        ":likeText" => "%$q%",
        ":qDigits"  => $qDigits,
        ":likeCuit" => "%$qDigits%"
    ]);

    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $filename = "proveedores_" . date("Ymd_His") . ".csv";
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";

    $out = fopen("php://output", "w");

    fputcsv($out, [
        "ID",
        "Razon social",
        "CUIT",
        "IVA",
        "Email",
        "Telefono",
        "Domicilio",
        "Activo"
    ], ";");

    foreach ($rows as $r) {
        fputcsv($out, [
            $r["id_proveedor"] ?? "",
            $r["razon_social"] ?? "",
            $r["cuit"] ?? "",
            $r["condicion_iva"] ?? "",
            $r["email"] ?? "",
            $r["telefono"] ?? "",
            $r["domicilio"] ?? "",
            ((int)($r["activo"] ?? 0) === 1 ? "SI" : "NO"),
        ], ";");
    }

    fclose($out);
    exit;
}

if ($action === "facturas_export") {
    registrar_log($usuario, "Exportación facturas", "facturas");

    $q = trim($_GET["q"] ?? "");
    $estado = trim($_GET["estado"] ?? "");
    $desde = trim($_GET["desde_emision"] ?? "");
    $hasta = trim($_GET["hasta_emision"] ?? "");

    $qDigits = preg_replace("/\D+/", "", $q);

    $where = " WHERE 1=1 ";
    $params = [];

    if ($estado !== "") {
        $where .= " AND f.estado = :estado ";
        $params[":estado"] = $estado;
    }

    if ($desde !== "") {
        $where .= " AND f.fecha_emision >= :desde ";
        $params[":desde"] = $desde;
    }

    if ($hasta !== "") {
        $where .= " AND f.fecha_emision <= :hasta ";
        $params[":hasta"] = $hasta;
    }

    if ($q !== "") {
        $where .= " AND (
            p.razon_social LIKE :likeText
            OR p.cuit LIKE :likeDigits
            OR f.numero LIKE :likeText
        ) ";
        $params[":likeText"] = "%$q%";
        $params[":likeDigits"] = ($qDigits !== "" ? "%$qDigits%" : "%$q%");
    }

    $sql = "
        SELECT
            f.id_factura,
            f.fecha_carga,
            f.fecha_emision,
            f.fecha_vencimiento,
            f.tipo,
            f.tipo_cbte,
            f.numero,
            f.estado,
            f.importe_total,
            p.id_proveedor,
            p.razon_social,
            p.cuit
        FROM facturas_proveedor f
        JOIN proveedores p ON p.id_proveedor = f.id_proveedor
        $where
        ORDER BY f.id_factura DESC
    ";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $filename = "facturas_" . date("Ymd_His") . ".csv";
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";

    $out = fopen("php://output", "w");

    fputcsv($out, [
        "ID Factura",
        "Fecha carga",
        "Fecha emision",
        "Vencimiento",
        "Proveedor",
        "CUIT",
        "Tipo",
        "Tipo cbte",
        "Numero",
        "Estado",
        "Importe total"
    ], ";");

    foreach ($rows as $r) {
        fputcsv($out, [
            $r["id_factura"] ?? "",
            $r["fecha_carga"] ?? "",
            $r["fecha_emision"] ?? "",
            $r["fecha_vencimiento"] ?? "",
            $r["razon_social"] ?? "",
            $r["cuit"] ?? "",
            $r["tipo"] ?? "",
            $r["tipo_cbte"] ?? "",
            $r["numero"] ?? "",
            $r["estado"] ?? "",
            $r["importe_total"] ?? "",
        ], ";");
    }

    fclose($out);
    exit;
}
      // ==================== PROVEEDOR RETENCIONES ====================

if ($action === "proveedor_retenciones_list") {
    $id_proveedor = intval($_GET["id_proveedor"] ?? 0);
    if ($id_proveedor <= 0) json_err("Proveedor inválido");

    $sql = "SELECT
                id_retencion,
                id_proveedor,
                tipo_retencion,
                porcentaje,
                importe_fijo,
                modo_calculo,
                detalle,
                monto_minimo,
                activo,
                created_at,
                updated_at
            FROM proveedor_retenciones
            WHERE id_proveedor = ?
            ORDER BY id_retencion DESC";

    $st = $pdo->prepare($sql);
    $st->execute([$id_proveedor]);

    json_ok($st->fetchAll(PDO::FETCH_ASSOC));
}

if ($action === "proveedor_retencion_add") {
    $j = input_json();

    $id_proveedor   = intval($j["id_proveedor"] ?? 0);
    $tipo_retencion = trim((string)($j["tipo_retencion"] ?? ""));
    $modo_calculo   = strtoupper(trim((string)($j["modo_calculo"] ?? "PORCENTAJE")));
    $porcentaje     = isset($j["porcentaje"]) ? toFloat($j["porcentaje"]) : null;
    $importe_fijo   = isset($j["importe_fijo"]) ? toFloat($j["importe_fijo"]) : null;
    $monto_minimo   = isset($j["monto_minimo"]) ? toFloat($j["monto_minimo"]) : null;
    $detalle        = trim((string)($j["detalle"] ?? ""));
    $activo         = intval($j["activo"] ?? 1);

    if ($id_proveedor <= 0) json_err("Proveedor inválido");
    if ($tipo_retencion === "") json_err("Tipo de retención requerido");

    $modos_validos = ["PORCENTAJE", "FIJO", "MANUAL"];
    if (!in_array($modo_calculo, $modos_validos, true)) {
        json_err("Modo de cálculo inválido");
    }

    $sql = "INSERT INTO proveedor_retenciones
                (id_proveedor, tipo_retencion, porcentaje, importe_fijo, modo_calculo, detalle, monto_minimo, activo)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)";

    $st = $pdo->prepare($sql);
    $st->execute([
        $id_proveedor,
        $tipo_retencion,
        $porcentaje,
        $importe_fijo,
        $modo_calculo,
        ($detalle !== "" ? $detalle : null),
        $monto_minimo,
        $activo ? 1 : 0
    ]);

    registrar_log($usuario, "Alta retención proveedor ID {$id_proveedor}", "proveedores");

    json_ok([
        "id_retencion" => (int)$pdo->lastInsertId()
    ]);
}

if ($action === "proveedor_retencion_update") {
    $j = input_json();

    $id_retencion   = intval($j["id_retencion"] ?? 0);
    $tipo_retencion = trim((string)($j["tipo_retencion"] ?? ""));
    $modo_calculo   = strtoupper(trim((string)($j["modo_calculo"] ?? "PORCENTAJE")));
    $porcentaje     = isset($j["porcentaje"]) ? toFloat($j["porcentaje"]) : null;
    $importe_fijo   = isset($j["importe_fijo"]) ? toFloat($j["importe_fijo"]) : null;
    $monto_minimo   = isset($j["monto_minimo"]) ? toFloat($j["monto_minimo"]) : null;
    $detalle        = trim((string)($j["detalle"] ?? ""));
    $activo         = intval($j["activo"] ?? 1);

    if ($id_retencion <= 0) json_err("ID de retención inválido");
    if ($tipo_retencion === "") json_err("Tipo de retención requerido");

    $modos_validos = ["PORCENTAJE", "FIJO", "MANUAL"];
    if (!in_array($modo_calculo, $modos_validos, true)) {
        json_err("Modo de cálculo inválido");
    }

    $sql = "UPDATE proveedor_retenciones
            SET tipo_retencion = ?,
                porcentaje = ?,
                importe_fijo = ?,
                modo_calculo = ?,
                detalle = ?,
                monto_minimo = ?,
                activo = ?
            WHERE id_retencion = ?";

    $st = $pdo->prepare($sql);
    $st->execute([
        $tipo_retencion,
        $porcentaje,
        $importe_fijo,
        $modo_calculo,
        ($detalle !== "" ? $detalle : null),
        $monto_minimo,
        $activo ? 1 : 0,
        $id_retencion
    ]);

    registrar_log($usuario, "Modificación retención ID {$id_retencion}", "proveedores");

    json_ok(true);
}

if ($action === "proveedor_retencion_delete") {
    $j = input_json();

    $id_retencion = intval($j["id_retencion"] ?? 0);
    if ($id_retencion <= 0) json_err("ID de retención inválido");

    $sql = "UPDATE proveedor_retenciones
            SET activo = 0
            WHERE id_retencion = ?";

    $st = $pdo->prepare($sql);
    $st->execute([$id_retencion]);

    registrar_log($usuario, "Baja lógica retención ID {$id_retencion}", "proveedores");

    json_ok(true);
}

    
      if ($action === "proveedores_create") {
      $j = input_json();

      $razon  = trim((string)($j["razon_social"] ?? ""));
      $cuit   = preg_replace("/\D+/", "", (string)($j["cuit"] ?? ""));
      $activo = (int)($j["activo"] ?? 1);
      $celular   = trim((string)($j["celular"] ?? ""));
      $provincia = trim((string)($j["provincia"] ?? ""));
      $plazo_pago = trim((string)($j["plazo_pago"] ?? ""));
      $notas     = trim((string)($j["notas"] ?? ""));
      $iva       = trim((string)($j["condicion_iva"] ?? ""));
      $domicilio = trim((string)($j["domicilio"] ?? ""));
      $email     = trim((string)($j["email"] ?? ""));
      $telefono  = trim((string)($j["telefono"] ?? ""));

      if ($razon === "") json_err("Razón social requerida");
      if (strlen($cuit) !== 11) json_err("CUIT inválido (11 dígitos)");
      if (!validar_cuit($cuit)) json_err("CUIT inválido");

      if ($iva === "") json_err("Condición IVA requerida");
      if ($email === "") json_err("Email requerido");
      if (!validar_email_basico($email)) json_err("Email inválido");
      if ($telefono === "") json_err("Teléfono requerido");
      
      if ($domicilio === "") json_err("Domicilio requerido");
      if ($provincia === "") json_err("Provincia requerida");
      
      try {
        $st = $pdo->prepare("
  INSERT INTO proveedores
(razon_social, cuit, condicion_iva, domicilio, email, telefono, celular, provincia, notas, plazo_pago, activo)
VALUES (:r, :c, :iva, :dom, :em, :tel, :cel, :prov, :notas, :plazo, :a)
");

      $st->execute([
        ":r"     => $razon,
        ":c"     => $cuit,
        ":iva"   => ($iva !== "" ? $iva : null),
        ":dom"   => ($domicilio !== "" ? $domicilio : null),
        ":em"    => ($email !== "" ? $email : null),
        ":tel"   => ($telefono !== "" ? $telefono : null),
        ":cel"   => ($celular !== "" ? $celular : null),
        ":plazo" => ($plazo_pago !== "" ? $plazo_pago : null),
        ":prov"  => ($provincia !== "" ? $provincia : null),
        ":notas" => ($notas !== "" ? $notas : null),
        ":a"     => ($activo ? 1 : 0),
      ]);

$id = (int)$pdo->lastInsertId();

$usuario = $_SESSION["usuario"] ?? "sistema";
try {
    registrar_log($usuario, "Alta proveedor ID ".$id, "proveedores");
} catch (Throwable $e) {
    // no romper el alta si falla el log
}

json_ok(["id_proveedor" => $id]);
      } catch (PDOException $e) {
        handleDuplicateError($e, "Ya existe un proveedor con este CUIT");
      }
        }
        if ($action === "proveedores_get") {
      $id = (int)($_GET["id_proveedor"] ?? 0);
      if ($id <= 0) json_err("ID inválido");

      $st = $pdo->prepare("
        SELECT id_proveedor, razon_social, cuit, condicion_iva, domicilio, email, telefono, celular, provincia, notas, plazo_pago, activo
        FROM proveedores
        WHERE id_proveedor = ?
      ");
      $st->execute([$id]);
      $r = $st->fetch(PDO::FETCH_ASSOC);
      if (!$r) json_err("Proveedor no existe");

      json_ok($r);
    }
        if ($action === "proveedores_update") {
      $j = input_json();

      $id = (int)($j["id_proveedor"] ?? 0);
      if ($id <= 0) json_err("ID inválido");

      $razon  = trim((string)($j["razon_social"] ?? ""));
      $cuit   = preg_replace("/\D+/", "", (string)($j["cuit"] ?? ""));
      $activo = (int)($j["activo"] ?? 1);
      $celular   = trim((string)($j["celular"] ?? ""));
      $provincia = trim((string)($j["provincia"] ?? ""));
      $notas     = trim((string)($j["notas"] ?? ""));
      $plazo_pago = trim((string)($j["plazo_pago"] ?? ""));
      $iva       = trim((string)($j["condicion_iva"] ?? ""));
      $domicilio = trim((string)($j["domicilio"] ?? ""));
      $email     = trim((string)($j["email"] ?? ""));
      $telefono  = trim((string)($j["telefono"] ?? ""));

      if ($razon === "") json_err("Razón social requerida");
      if (strlen($cuit) !== 11) json_err("CUIT inválido (11 dígitos)");
      if (!validar_cuit($cuit)) json_err("CUIT inválido");

      if ($iva === "") json_err("Condición IVA requerida");
      if ($email === "") json_err("Email requerido");
      if (!validar_email_basico($email)) json_err("Email inválido");
      if ($telefono === "") json_err("Teléfono requerido");
      if ($domicilio === "") json_err("Domicilio requerido");
      if ($provincia === "") json_err("Provincia requerida");
      try {
        // Evitar duplicado de CUIT en otro proveedor
        $st = $pdo->prepare("SELECT id_proveedor FROM proveedores WHERE cuit=? AND id_proveedor<>? LIMIT 1");
        $st->execute([$cuit, $id]);
        if ($st->fetch()) json_err("Ya existe otro proveedor con ese CUIT");

        $st = $pdo->prepare("
        UPDATE proveedores
        SET razon_social=:r,
            cuit=:c,
            condicion_iva=:iva,
            domicilio=:dom,
            email=:em,
            telefono=:tel,
            celular=:cel,
            plazo_pago=:plazo,
            provincia=:prov,
            notas=:notas,
            activo=:a
        WHERE id_proveedor=:id
      ");

      $st->execute([
        ":r"     => $razon,
        ":c"     => $cuit,
        ":iva"   => ($iva !== "" ? $iva : null),
        ":dom"   => ($domicilio !== "" ? $domicilio : null),
        ":em"    => ($email !== "" ? $email : null),
        ":tel"   => ($telefono !== "" ? $telefono : null),
        ":cel"   => ($celular !== "" ? $celular : null),
        ":plazo" => ($plazo_pago !== "" ? $plazo_pago : null),
        ":prov"  => ($provincia !== "" ? $provincia : null),
        ":notas" => ($notas !== "" ? $notas : null),
        ":a"     => ($activo ? 1 : 0),
        ":id"    => $id,
      ]);
        $usuario = $_SESSION["usuario"] ?? "sistema";
registrar_log($usuario,"Modificación proveedor ID ".$id,"proveedores");

        json_ok(["ok" => 1]);
      } catch (PDOException $e) {
    json_err("Error al actualizar proveedor: " . $e->getMessage());
}
    }



    
    if ($action === "proveedor_saldo") {
        $idp = (int)($_GET["id_proveedor"] ?? 0);
        if ($idp <= 0) json_err("Proveedor requerido");
        
        // Saldo de cuenta corriente
        $st = $pdo->prepare("
            SELECT COALESCE(SUM(importe), 0) AS saldo
            FROM proveedor_mov
            WHERE id_proveedor = ?
        ");
        $st->execute([$idp]);
        $saldo = (float)$st->fetchColumn();
        
        // Facturas pendientes
        $st = $pdo->prepare("
            SELECT 
                COUNT(*) as cantidad,
                COALESCE(SUM(importe_total), 0) as total_pendiente
            FROM facturas_proveedor
            WHERE id_proveedor = ? 
              AND estado IN ('CARGADA', 'IMPUTADA')
        ");
        $st->execute([$idp]);
        $facturas = $st->fetch(PDO::FETCH_ASSOC);
        
        json_ok([
            "saldo" => $saldo,
            "facturas_pendientes" => (int)$facturas['cantidad'],
            "total_pendiente" => (float)$facturas['total_pendiente']
        ]);
    }
    
    // ==================== FACTURAS ====================
    
    if ($action === "facturas_list") {
        $q = trim($_GET["q"] ?? "");
        $estado = trim($_GET["estado"] ?? "");
        $desde_vencimiento = trim($_GET["desde_emision"] ?? "");
        $hasta_vencimiento = trim($_GET["hasta_emision"] ?? "");
        
        $sql = "
            SELECT
                f.id_factura,
                f.fecha_carga,
                f.tipo,
                f.tipo_cbte,
                f.numero,
                f.fecha_emision,
                f.fecha_vencimiento,
                f.importe_total,
                f.estado,
                f.id_op,
                f.pdf_path,
                p.id_proveedor,
                p.razon_social,
                p.cuit
            FROM facturas_proveedor f
            JOIN proveedores p ON p.id_proveedor = f.id_proveedor
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($q !== "") {
            $sql .= " AND (p.razon_social LIKE :q OR p.cuit LIKE :q OR f.numero LIKE :q) ";
            $params[":q"] = "%{$q}%";
        }
        
        if ($estado !== "") {
            $sql .= " AND f.estado = :estado ";
            $params[":estado"] = $estado;
        }
        
        if ($desde_vencimiento !== "") {
            $sql .= " AND f.fecha_vencimiento >= :de ";
            $params[":de"] = $desde_vencimiento;
        }

        if ($hasta_vencimiento !== "") {
            $sql .= " AND f.fecha_vencimiento <= :ha ";
            $params[":ha"] = $hasta_vencimiento;
        }
        
        $sql .= " ORDER BY f.id_factura DESC LIMIT 300";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        json_ok($st->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === "facturas_export") {
      registrar_log($usuario,"Exportación facturas","facturas");
      header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="facturas.csv"');
    echo "\xEF\xBB\xBF";
  $q = trim($_GET["q"] ?? "");
  $estado = trim($_GET["estado"] ?? "");
  $desde = trim($_GET["desde_emision"] ?? "");
  $hasta = trim($_GET["hasta_emision"] ?? "");

  $qDigits = preg_replace("/\D+/", "", $q);

  $where = " WHERE 1=1 ";
  $params = [];

  // Estado
  if ($estado !== "") {
    $where .= " AND f.estado = :estado ";
    $params[":estado"] = $estado;
  }

  // Fechas emisión
  if ($desde !== "") {
    $where .= " AND f.fecha_emision >= :desde ";
    $params[":desde"] = $desde;
  }
  if ($hasta !== "") {
    $where .= " AND f.fecha_emision <= :hasta ";
    $params[":hasta"] = $hasta;
  }

  // Búsqueda (proveedor / CUIT / nro)
  if ($q !== "") {
    $where .= " AND (
      p.razon_social LIKE :likeText
      OR p.cuit LIKE :likeDigits
      OR f.numero LIKE :likeText
    ) ";
    $params[":likeText"] = "%$q%";
    $params[":likeDigits"] = ($qDigits !== "" ? "%$qDigits%" : "%$q%");
  }

  $sql = "
    SELECT
      f.id_factura,
      f.fecha_carga,
      f.fecha_emision,
      f.fecha_vencimiento,
      f.tipo,
      f.tipo_cbte,
      f.numero,
      f.estado,
      f.importe_total,
      p.id_proveedor,
      p.razon_social,
      p.cuit
    FROM facturas_proveedor f
    JOIN proveedores p ON p.id_proveedor = f.id_proveedor
    $where
    ORDER BY f.id_factura DESC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $filename = "facturas_" . date("Ymd_His") . ".csv";
  header("Content-Type: text/csv; charset=UTF-8");
  header("Content-Disposition: attachment; filename=\"$filename\"");
  header("Pragma: no-cache");
  header("Expires: 0");

  // BOM para Excel (acentos ok)
  echo "\xEF\xBB\xBF";

  $out = fopen("php://output", "w");

  fputcsv($out, [
    "ID Factura", "Fecha carga", "Fecha emisión", "Vencimiento",
    "Proveedor", "CUIT", "Tipo", "Tipo cbte", "Número",
    "Estado", "Importe total"
  ], ";");

  foreach ($rows as $r) {
    fputcsv($out, [
      $r["id_factura"] ?? "",
      $r["fecha_carga"] ?? "",
      $r["fecha_emision"] ?? "",
      $r["fecha_vencimiento"] ?? "",
      $r["razon_social"] ?? "",
      $r["cuit"] ?? "",
      $r["tipo"] ?? "",
      $r["tipo_cbte"] ?? "",
      $r["numero"] ?? "",
      $r["estado"] ?? "",
      $r["importe_total"] ?? "",
    ], ";");
  }

  fclose($out);
  exit;
}

    
    if ($action === "facturas_get") {
        $id_factura = (int)($_GET["id_factura"] ?? 0);
        if ($id_factura <= 0) json_err("id_factura requerido");
        
        // Cabecera de factura
        $st = $pdo->prepare("
            SELECT
                f.*,
                DATE(f.fecha_carga) AS fecha_carga,
                DATE(f.fecha_emision) AS fecha_emision,
                DATE(f.fecha_vencimiento) AS fecha_vencimiento,
                p.razon_social, 
                p.cuit
            FROM facturas_proveedor f
            JOIN proveedores p ON p.id_proveedor = f.id_proveedor
            WHERE f.id_factura = ?
            LIMIT 1
        ");
        $st->execute([$id_factura]);
        $cab = $st->fetch(PDO::FETCH_ASSOC);
        
        if (!$cab) json_err("Factura no encontrada", 404);
        
        // Items de factura
        $st = $pdo->prepare("
            SELECT
                id_item,
                id_factura,
                codigo,
                descripcion,
                cantidad,
                precio_unit,
                COALESCE(bonifica_porc, 0) AS bonifica_porc,
                COALESCE(bonifica_importe, 0) AS bonifica_importe,
                COALESCE(importe_bonificado, 0) AS importe_bonificado,
                COALESCE(subtotal, 0) AS subtotal
            FROM facturas_proveedor_det
            WHERE id_factura = ?
            ORDER BY id_item
        ");
        $st->execute([$id_factura]);
        $items = $st->fetchAll(PDO::FETCH_ASSOC);
        
        json_ok(["cab" => $cab, "items" => $items]);
    }
    
    if ($action === "facturas_create_full") {
        $j = input_json();
        
        // Validación de datos de entrada
        $id_proveedor  = (int)($j["id_proveedor"] ?? 0);
        $tipo          = strtoupper(trim((string)($j["tipo"] ?? "FC")));
        $tipo_cbte     = strtoupper(trim((string)($j["tipo_cbte"] ?? "A")));
        $numero        = trim((string)($j["numero"] ?? ""));
        $fecha_carga   = (string)($j["fecha_carga"] ?? date("Y-m-d"));
        $fecha_emision = $j["fecha_emision"] ?? null;
        $fecha_venc    = $j["fecha_vencimiento"] ?? null;
        $obs           = trim((string)($j["observacion"] ?? ""));
        $items         = $j["items"] ?? [];
        $importe_total_cab = toFloat($j["importe_total"] ?? 0);
        
        // Aplicar signo según tipo
        $importe_total_cab = signedByTipo($tipo, $importe_total_cab);
        
        // Validaciones
        if ($id_proveedor <= 0) json_err("Proveedor requerido");
        if (!in_array($tipo, ["FC","NC","ND"], true)) json_err("Tipo de comprobante inválido");
        if (!in_array($tipo_cbte, ["A","B","C","NCA","NCB","NCC"], true)) json_err("Tipo de comprobante fiscal inválido");
        if ($numero === "") json_err("Número de factura requerido");
        if (!is_array($items) || count($items) === 0) json_err("La factura debe tener al menos 1 ítem");
        if (!is_finite($importe_total_cab) || $importe_total_cab == 0.0) json_err("Importe total requerido");
        
        $pdo->beginTransaction();
        
        try {
            // Insertar cabecera de factura
            $st = $pdo->prepare("
                INSERT INTO facturas_proveedor
                (id_proveedor, tipo, tipo_cbte, numero, fecha_carga, fecha_emision, 
                 fecha_vencimiento, importe_total, observacion, estado)
                VALUES
                (:idp, :tipo, :tcb, :num, :fc, :fe, :fv, :tot, :obs, 'CARGADA')
            ");
            $st->execute([
                ":idp"  => $id_proveedor,
                ":tipo" => $tipo,
                ":tcb"  => $tipo_cbte,
                ":num"  => $numero,
                ":fc"   => $fecha_carga,
                ":fe"   => $fecha_emision,
                ":fv"   => $fecha_venc,
                ":tot"  => $importe_total_cab,
                ":obs"  => $obs
            ]);
            $id_factura = (int)$pdo->lastInsertId();
            
$usuario = $_SESSION["usuario"] ?? "sistema";
registrar_log($usuario,"Alta factura proveedor ID ".$id_factura,"facturas");
            // Registrar movimiento en cuenta corriente
            $imp_signed = signedByTipo($tipo, abs($importe_total_cab));
            $st = $pdo->prepare("
                INSERT INTO proveedor_mov
                (id_proveedor, fecha, tipo, ref_tabla, ref_id, importe, observacion)
                VALUES
                (:idp, :fec, 'FACTURA', 'facturas_proveedor', :rid, :imp, :obs)
            ");
            $st->execute([
                ":idp" => $id_proveedor,
                ":fec" => ($fecha_emision ?: date("Y-m-d")),
                ":rid" => $id_factura,
                ":imp" => $imp_signed,
                ":obs" => "Factura $tipo $tipo_cbte N° $numero - $obs"
            ]);
            
            // Insertar items de factura
            $insItem = $pdo->prepare("
                INSERT INTO facturas_proveedor_det
                (id_factura, codigo, descripcion, cantidad, precio_unit, 
                 bonifica_porc, bonifica_importe, importe_bonificado, subtotal)
                VALUES
                (:idf, :cod, :des, :cant, :pu, :bonp, :boni, :impbon, :subt)
            ");
            
            $total_items = 0.0;
            
            foreach ($items as $idx => $it) {
                if (!is_array($it)) {
                    $pdo->rollBack();
                    json_err('Ítem inválido en posición ' . ($idx + 1));
                }
                
                $codigo = trim((string)($it["codigo"] ?? ""));
                $desc   = trim((string)($it["descripcion"] ?? ""));
                $cant   = toFloat($it["cantidad"] ?? 0);
                $pu     = toFloat($it["precio_unit"] ?? 0);
                $bonp   = toFloat($it["bonifica"] ?? 0);
                
                if ($desc === "") {
                    $pdo->rollBack();
                    json_err("Descripción requerida en ítem " . ($idx + 1));
                }
                if ($cant <= 0) {
                    $pdo->rollBack();
                    json_err("Cantidad debe ser > 0 en ítem " . ($idx + 1));
                }
                
                $subtotal = round($cant * $pu, 2);
                $bonp = max(0.0, min(100.0, $bonp));
                $bonifica_importe = round($subtotal * ($bonp / 100.0), 2);
                $importe_bonificado = round($subtotal - $bonifica_importe, 2);
                $total_items += $importe_bonificado;
                
                $insItem->execute([
                    ":idf"    => $id_factura,
                    ":cod"    => ($codigo !== "" ? $codigo : null),
                    ":des"    => $desc,
                    ":cant"   => $cant,
                    ":pu"     => $pu,
                    ":bonp"   => $bonp,
                    ":boni"   => $bonifica_importe,
                    ":impbon" => $importe_bonificado,
                    ":subt"   => $subtotal
                ]);
            }
            
            // Aplicar signo al total de items
            $total_items = signedByTipo($tipo, $total_items);
            
            // Validar coincidencia entre total cabecera y total items
            $a = round((float)$importe_total_cab, 2);
            $b = round((float)$total_items, 2);
            if (abs($a - $b) > 0.01) {
                $pdo->rollBack();
                json_err("No coincide el total: cabecera = $a vs ítems = $b");
            }
            
            // Actualizar con total calculado (ajustado)
            $st = $pdo->prepare("UPDATE facturas_proveedor SET importe_total = :t WHERE id_factura = :idf");
            $st->execute([":t" => $b, ":idf" => $id_factura]);
            
            $pdo->commit();
            json_ok([
                "id_factura" => $id_factura,
                "importe_total" => $b,
                "tipo" => $tipo,
                "numero" => $numero
            ]);
            
        } catch (PDOException $e) {
          $pdo->rollBack();

          if (($e->errorInfo[1] ?? 0) == 1062) {
              json_err("Error 1062: " . ($e->errorInfo[2] ?? $e->getMessage()));
          }

          json_err("Error al crear factura: " . $e->getMessage(), 500);
      }
    }
    
    if ($action === "facturas_delete") {
        $j = input_json();
        $id_factura = (int)($j["id_factura"] ?? 0);
        if ($id_factura <= 0) json_err("id_factura requerido");
        
        $pdo->beginTransaction();
        
        try {
            // Verificar que la factura pueda ser eliminada
            $st = $pdo->prepare("
                SELECT id_factura, estado, id_op, tipo, importe_total, id_proveedor
                FROM facturas_proveedor
                WHERE id_factura = ?
                FOR UPDATE
            ");
            $st->execute([$id_factura]);
            $f = $st->fetch(PDO::FETCH_ASSOC);
            
            if (!$f) {
                $pdo->rollBack();
                json_err("Factura no encontrada", 404);
            }
            
            if ($f["estado"] !== "CARGADA") {
                $pdo->rollBack();
                json_err("Solo se pueden borrar facturas en estado CARGADA");
            }
            
            if (!empty($f["id_op"])) {
                $pdo->rollBack();
                json_err("No se puede borrar: factura imputada a una Orden de Pago");
            }
            
            // Revertir movimiento en cuenta corriente
            $st = $pdo->prepare("
                DELETE FROM proveedor_mov 
                WHERE ref_tabla = 'facturas_proveedor' 
                AND ref_id = ?
            ");
            $st->execute([$id_factura]);
            
            // Eliminar items
            // Eliminar cabecera// Eliminar cabecera
        // eliminar items
        $st = $pdo->prepare("
        DELETE FROM facturas_proveedor_det
        WHERE id_factura = ?
        ");
        $st->execute([$id_factura]);

        // eliminar cabecera
        $st = $pdo->prepare("
        DELETE FROM facturas_proveedor
        WHERE id_factura = ?
        ");
        $st->execute([$id_factura]);

        registrar_log($usuario,"Eliminación factura proveedor ID ".$id_factura,"facturas");            
            $pdo->commit();
            json_ok([
                "deleted" => true,
                "id_factura" => $id_factura,
                "mensaje" => "Factura eliminada correctamente"
            ]);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            json_err("Error al eliminar factura: " . $e->getMessage(), 500);
        }
    }
    
    if ($action === "facturas_upload_pdf") {
        $id_factura = (int)($_POST["id_factura"] ?? 0);
        if ($id_factura <= 0) json_err("id_factura requerido");
        
        if (!isset($_FILES["pdf"]) || $_FILES["pdf"]["error"] !== UPLOAD_ERR_OK) {
            json_err("No se recibió archivo PDF o hubo un error en la subida");
        }
        
        $f = $_FILES["pdf"];
        $name = (string)($f["name"] ?? "");
        $tmp  = (string)($f["tmp_name"] ?? "");
        $size = (int)($f["size"] ?? 0);
        
        // Validaciones
        if ($size <= 0) json_err("Archivo vacío");
        if ($size > 15 * 1024 * 1024) json_err("PDF demasiado grande (máximo 15MB)");
        
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== "pdf") json_err("Solo se permiten archivos PDF");
        
        // Validar tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmp);
        finfo_close($finfo);
        
        if (!in_array($mime, ['application/pdf', 'application/x-pdf'])) {
            json_err("El archivo no es un PDF válido");
        }
        
        // Crear directorio si no existe
        $dir = __DIR__ . "/../uploads/facturas";
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            json_err("No se pudo crear el directorio de uploads");
        }
        
        // Generar nombre único
        $filename = "factura_" . $id_factura . "_" . date("Ymd_His") . ".pdf";
        $destAbs = $dir . "/" . $filename;
        
        if (!move_uploaded_file($tmp, $destAbs)) {
            json_err("Error al guardar el archivo en el servidor");
        }
        
        // Guardar ruta en base de datos
        $publicPath = "uploads/facturas/" . $filename;
        
        try {
            $st = $pdo->prepare("UPDATE facturas_proveedor SET pdf_path = :p WHERE id_factura = :idf");
            $st->execute([":p" => $publicPath, ":idf" => $id_factura]);
            registrar_log($usuario,"Carga PDF factura ID ".$id_factura,"facturas");
            json_ok([
                "id_factura" => $id_factura,
                "pdf_path" => $publicPath,
                "filename" => $filename,
                "size" => $size
            ]);
            
        } catch (PDOException $e) {
            // Eliminar archivo si falla la BD
            if (file_exists($destAbs)) {
                unlink($destAbs);
            }
            json_err("Error al guardar información en base de datos: " . $e->getMessage(), 500);
        }
    }
    
    // ==================== ORDENES DE PAGO ====================
    
   if ($action === "op_list") {
    $q = trim($_GET["q"] ?? "");
    $desde = trim($_GET["desde"] ?? "");
    $hasta = trim($_GET["hasta"] ?? "");

    $sql = "
        SELECT 
            o.id_op, 
            o.fecha_op, 
            o.importe_pago,
            o.total, 
            o.estado, 
            o.medio_pago,
            p.razon_social, 
            p.cuit
        FROM op_cabecera o
        JOIN proveedores p ON p.id_proveedor = o.id_proveedor
        WHERE 1=1
    ";

    $params = [];

    if ($q !== "") {
        $sql .= " AND (p.razon_social LIKE :q OR p.cuit LIKE :q OR o.id_op LIKE :qnum) ";
        $params[":q"] = "%{$q}%";
        $params[":qnum"] = "%{$q}%";
    }

    if ($desde !== "") {
        $sql .= " AND o.fecha_op >= :desde ";
        $params[":desde"] = $desde;
    }

    if ($hasta !== "") {
        $sql .= " AND o.fecha_op <= :hasta ";
        $params[":hasta"] = $hasta;
    }

    $sql .= " ORDER BY o.id_op DESC LIMIT 200";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    json_ok($st->fetchAll(PDO::FETCH_ASSOC));
}
    
    if ($action === "op_create_from_facturas") {
      
  $input = json_decode(file_get_contents("php://input"), true);
  if (!$input) json_err("JSON inválido");

  $id_proveedor  = intval($input["id_proveedor"] ?? 0);
  $fecha_op      = trim($input["fecha_op"] ?? "");
  $observacion   = $input["observacion"] ?? null;

  $facturas      = $input["facturas"] ?? [];
  $imputaciones  = $input["imputaciones"] ?? [];
  $pagos         = $input["pagos"] ?? [];
  $retenciones   = $input["retenciones"] ?? [];

  if (!$id_proveedor) json_err("Proveedor inválido");
  if (!$fecha_op) json_err("Fecha OP requerida");
  if (!is_array($facturas)) json_err("Facturas inválidas");
  if (!is_array($imputaciones)) $imputaciones = [];
  if (!is_array($pagos)) $pagos = [];
  if (!is_array($retenciones)) $retenciones = [];


  // Helper: signed por tipo
  $signed = function($tipo, $importe) {
    $t = strtoupper(trim((string)$tipo));
    $imp = abs((float)$importe);
    return ($t === "NC") ? -$imp : $imp;
  };

  $pdo->beginTransaction();

  try {
    // 1) Insert cabecera OP (en borrador, luego actualizamos total/importe_pago/medio_pago)
    $stmt = $pdo->prepare("INSERT INTO op_cabecera (id_proveedor, fecha_op, importe_pago, total, medio_pago, observacion, estado)
                          VALUES (?, ?, 0.00, 0.00, 'TRANSFERENCIA', ?, 'CONFIRMADA')");
    $stmt->execute([$id_proveedor, $fecha_op, $observacion]);
    $id_op = $pdo->lastInsertId();

    // 2) Facturas seleccionadas
    $ids = array_values(array_unique(array_map("intval", $facturas)));
    $total_imputado_neto = 0.0;

    if (count($ids) > 0) {
      // Lock facturas
      $in = implode(",", array_fill(0, count($ids), "?"));
      $sql = "SELECT id_factura, tipo, importe_total, estado
              FROM facturas_proveedor
              WHERE id_factura IN ($in) AND id_proveedor = ?
              FOR UPDATE";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(array_merge($ids, [$id_proveedor]));
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

      if (count($rows) !== count($ids)) {
        throw new Exception("Algunas facturas no existen o no pertenecen al proveedor.");
      }

      // Insert detalle (importe editable)
      $insDet = $pdo->prepare("INSERT INTO op_detalle (id_op, id_factura, importe_imputado, descuento)
                              VALUES (?, ?, ?, 0.00)");

      $updFactura = $pdo->prepare("UPDATE facturas_proveedor
                                  SET estado='IMPUTADA', id_op=?
                                  WHERE id_factura=?");

      foreach ($rows as $r) {
        $id_factura = (int)$r["id_factura"];
        $tipo       = $r["tipo"];
        $imp_total  = (float)$r["importe_total"];

        // importe imputado editable: si no viene, usamos total
        $imp_in = isset($imputaciones[$id_factura]) ? (float)$imputaciones[$id_factura] : $imp_total;
        if ($imp_in < 0) $imp_in = abs($imp_in);
        if ($imp_in > $imp_total && strtoupper($tipo) !== "NC") {
          // opcional: permitir imputar más que el total? normalmente NO.
          // si querés permitir, comentá este if.
          throw new Exception("Imputación mayor al total en factura $id_factura.");
        }

        $insDet->execute([$id_op, $id_factura, $imp_in]);

        // total neto (NC resta)
        $total_imputado_neto += $signed($tipo, $imp_in);

        // mantener compatibilidad con tu campo id_op en factura
        $updFactura->execute([$id_op, $id_factura]);
      }
    }

    // 3) Pagos (uno o varios)
    $total_pagado = 0.0;
    $medios = [];

if (count($pagos) > 0) {

  $insPago = $pdo->prepare("
    INSERT INTO op_pagos
      (id_op, medio_pago, importe, fecha_pago, detalle, banco, nro_cheque, fecha_cheque, fecha_vto)
    VALUES
      (:id_op, :medio, :imp, :fecha, :detalle, :banco, :nro, :fchq, :fvto)
  ");

  foreach ($pagos as $p) {

    $fecha_pago = trim($p["fecha_pago"] ?? $fecha_op);
    $medio_pago = strtoupper(trim($p["medio_pago"] ?? "TRANSFERENCIA"));
    $importe    = (float)($p["importe"] ?? 0);

    if ($importe <= 0) continue;

    $detalle      = trim($p["detalle"] ?? ($p["referencia"] ?? ""));
    $banco        = trim($p["banco"] ?? "");
    $nro_cheque   = trim($p["nro_cheque"] ?? ($p["nro"] ?? ""));
    $fecha_cheque = trim($p["fecha_cheque"] ?? "");
    $fecha_vto    = trim($p["fecha_vto"] ?? "");

    $detalle      = ($detalle !== "") ? $detalle : null;
    $banco        = ($banco !== "") ? $banco : null;
    $nro_cheque   = ($nro_cheque !== "") ? $nro_cheque : null;
    $fecha_cheque = ($fecha_cheque !== "") ? $fecha_cheque : null;
    $fecha_vto    = ($fecha_vto !== "") ? $fecha_vto : null;

    $insPago->execute([
      ":id_op"   => $id_op,
      ":medio"   => $medio_pago,
      ":imp"     => $importe,
      ":fecha"   => $fecha_pago,
      ":detalle" => $detalle,
      ":banco"   => $banco,
      ":nro"     => $nro_cheque,
      ":fchq"    => $fecha_cheque,
      ":fvto"    => $fecha_vto,
    ]);

    $total_pagado += $importe;
    $medios[$medio_pago] = true;
  }
}
        // 3.b) Retenciones
    $total_retenciones = 0.0;

    if (count($retenciones) > 0) {
      $insRet = $pdo->prepare("
        INSERT INTO op_retenciones
          (id_op, id_proveedor_retencion, tipo_retencion, base_calculo, porcentaje, importe, detalle)
        VALUES
          (:id_op, :id_proveedor_retencion, :tipo_retencion, :base_calculo, :porcentaje, :importe, :detalle)
      ");

      foreach ($retenciones as $r) {
        $importe = (float)($r["importe_calculado"] ?? $r["importe"] ?? 0);
        if ($importe <= 0) continue;

        $insRet->execute([
          ":id_op" => $id_op,
          ":id_proveedor_retencion" => isset($r["id_retencion"]) ? (int)$r["id_retencion"] : null,
          ":tipo_retencion" => trim($r["tipo_retencion"] ?? ""),
          ":base_calculo" => (float)$total_imputado_neto,
          ":porcentaje" => isset($r["porcentaje"]) && $r["porcentaje"] !== "" ? (float)$r["porcentaje"] : null,
          ":importe" => $importe,
          ":detalle" => trim($r["detalle"] ?? "") ?: null,
        ]);

        $total_retenciones += $importe;
      }
    }

    $medio_op = (count($medios) <= 1) ? (count($medios) ? array_key_first($medios) : "TRANSFERENCIA") : "MIXTO";

    // 4) Actualizar cabecera con total + importe_pago + medio
    $stmt = $pdo->prepare("UPDATE op_cabecera
                          SET total=?, importe_pago=?, medio_pago=?
                          WHERE id_op=?");
    $stmt->execute([$total_imputado_neto, $total_pagado, $medio_op, $id_op]);

    $pdo->commit();
registrar_log($usuario,"Creación Orden de Pago ID ".$id_op,"ordenes_pago");
    json_ok([
      "id_op" => (int)$id_op,
      "total" => (float)$total_imputado_neto,          // neto imputado
      "importe_pago" => (float)$total_pagado,          // total pagado (suma op_pagos)
      "medio_pago" => $medio_op,
      "total_retenciones" => (float)$total_retenciones,
      "saldo" => (float)($total_imputado_neto - $total_retenciones - $total_pagado)
    ]);

  } catch (Exception $e) {
    $pdo->rollBack();
    json_err($e->getMessage());
  }
}
if ($action === "proveedor_cta_cte") {

  $idp = (int)($_GET["id_proveedor"] ?? 0);
  if ($idp <= 0) json_err("Proveedor inválido");

  $st = $pdo->prepare("SELECT id_proveedor, razon_social, cuit FROM proveedores WHERE id_proveedor=?");
  $st->execute([$idp]);
  $prov = $st->fetch(PDO::FETCH_ASSOC);
  if (!$prov) json_err("Proveedor no existe");

  // Movimientos: Facturas (DEBE) / NC (HABER) / Pagos OP (HABER) / Retenciones OP (HABER)
  $sql = "
    SELECT fecha, tipo, referencia, detalle, debe, haber
    FROM (
      SELECT
        COALESCE(fp.fecha_emision, DATE(fp.fecha_carga)) AS fecha,
        CONCAT(fp.tipo,' ',fp.tipo_cbte) AS tipo,
        fp.numero AS referencia,
        CONCAT('Factura ', fp.numero) AS detalle,
        fp.importe_total AS debe,
        0.00 AS haber
      FROM facturas_proveedor fp
      WHERE fp.id_proveedor = ? AND fp.estado <> 'ANULADA' AND fp.tipo IN ('FC','ND')

      UNION ALL

      SELECT
        COALESCE(fp.fecha_emision, DATE(fp.fecha_carga)) AS fecha,
        CONCAT(fp.tipo,' ',fp.tipo_cbte) AS tipo,
        fp.numero AS referencia,
        CONCAT('Nota crédito ', fp.numero) AS detalle,
        0.00 AS debe,
        fp.importe_total AS haber
      FROM facturas_proveedor fp
      WHERE fp.id_proveedor = ? AND fp.estado <> 'ANULADA' AND fp.tipo = 'NC'

      UNION ALL

      SELECT
        p.fecha_pago AS fecha,
        'PAGO' AS tipo,
        CONCAT('OP#', p.id_op) AS referencia,
        CONCAT(
          p.medio_pago,
          IFNULL(CONCAT(' - ', p.detalle), ''),
          IFNULL(CONCAT(' - Banco: ', p.banco), ''),
          IFNULL(CONCAT(' - Chq: ', p.nro_cheque), '')
        ) AS detalle,
        0.00 AS debe,
        p.importe AS haber
      FROM op_pagos p
      JOIN op_cabecera oc ON oc.id_op = p.id_op
      WHERE oc.id_proveedor = ? AND oc.estado <> 'ANULADA'

      UNION ALL

      SELECT
        oc.fecha_op AS fecha,
        'RETENCION' AS tipo,
        CONCAT('OP#', r.id_op) AS referencia,
        CONCAT(
          r.tipo_retencion,
          IFNULL(CONCAT(' - ', r.detalle), '')
        ) AS detalle,
        0.00 AS debe,
        r.importe AS haber
      FROM op_retenciones r
      JOIN op_cabecera oc ON oc.id_op = r.id_op
      WHERE oc.id_proveedor = ? AND oc.estado <> 'ANULADA'
    ) x
    ORDER BY fecha, referencia
  ";

  $st = $pdo->prepare($sql);
  $st->execute([$idp, $idp, $idp, $idp]);
  $mov = $st->fetchAll(PDO::FETCH_ASSOC);

  // saldo acumulado + resumen
  $saldo = 0.0;
  $totDebe = 0.0;
  $totHaber = 0.0;

  foreach ($mov as &$m) {
    $debe = (float)($m["debe"] ?? 0);
    $haber = (float)($m["haber"] ?? 0);

    $totDebe += $debe;
    $totHaber += $haber;

    $saldo += ($debe - $haber);
    $m["saldo"] = $saldo;
  }
  unset($m);

  registrar_log($usuario, "Consulta cuenta corriente proveedor ".$idp, "proveedores");

  json_ok([
    "proveedor" => $prov,
    "resumen" => [
      "debe" => $totDebe,
      "haber" => $totHaber,
      "saldo" => $saldo
    ],
    "movimientos" => $mov
  ]);
}

if ($action === "op_anular") {
    $j = input_json();
    $id_op = (int)($j["id_op"] ?? 0);

    if ($id_op <= 0) json_err("id_op requerido");

    $pdo->beginTransaction();

    try {
        // Verificar que exista
        $st = $pdo->prepare("
            SELECT id_op, estado
            FROM op_cabecera
            WHERE id_op = ?
            FOR UPDATE
        ");
        $st->execute([$id_op]);
        $op = $st->fetch(PDO::FETCH_ASSOC);

        if (!$op) {
            $pdo->rollBack();
            json_err("Orden de pago no encontrada", 404);
        }

        if (strtoupper((string)$op["estado"]) === "ANULADA") {
            $pdo->rollBack();
            json_err("La OP ya está anulada");
        }

        // Devolver facturas a CARGADA
        $st = $pdo->prepare("
            UPDATE facturas_proveedor
            SET estado = 'CARGADA',
                id_op = NULL
            WHERE id_op = ?
        ");
        $st->execute([$id_op]);

        // Marcar cabecera como anulada
        $st = $pdo->prepare("
            UPDATE op_cabecera
            SET estado = 'ANULADA'
            WHERE id_op = ?
        ");
        $st->execute([$id_op]);

        registrar_log($usuario, "Anulación Orden de Pago ID ".$id_op, "ordenes_pago");

        $pdo->commit();
        json_ok([
            "anulada" => true,
            "id_op" => $id_op
        ]);

    } catch (Throwable $e) {
        $pdo->rollBack();
        json_err("Error al anular OP: " . $e->getMessage(), 500);
    }
}

    // ==================== ACCIÓN NO ENCONTRADA ====================
    
    json_err("Acción no implementada: $action", 404);
    
} catch (PDOException $e) {
    json_err("Error de base de datos: " . $e->getMessage(), 500);
} catch (Throwable $e) {
    json_err("Error interno del servidor: " . $e->getMessage(), 500);
}
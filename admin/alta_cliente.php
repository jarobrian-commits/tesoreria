<?php
declare(strict_types=1);
session_start();

/*
  ALTA DE CLIENTE
  - Crea base amber_<codigo>
  - Copia estructura y datos desde amber_template
  - Inserta usuario admin inicial
*/

$host = '127.0.0.1';
$port = '3306';
$user = 'root';
$pass = '';

$mensaje = '';
$error = '';

function validar_codigo_cliente(string $codigo): bool {
    return (bool)preg_match('/^[a-z0-9_-]+$/', $codigo);
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("No se pudo conectar a MySQL: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo       = strtolower(trim($_POST['codigo'] ?? ''));
    $nombre       = trim($_POST['nombre'] ?? '');
    $usuarioAdmin = trim($_POST['usuario_admin'] ?? 'admin');
    $claveAdmin   = trim($_POST['clave_admin'] ?? '1234');

    if ($codigo === '' || !validar_codigo_cliente($codigo)) {
        $error = 'Código de cliente inválido. Usar solo minúsculas, números, guion y guion bajo.';
    } elseif ($nombre === '') {
        $error = 'Falta el nombre del cliente.';
    } elseif ($usuarioAdmin === '') {
        $error = 'Falta el usuario admin.';
    } elseif ($claveAdmin === '') {
        $error = 'Falta la clave admin.';
    } else {
        $dbNueva = 'amber_' . $codigo;
        $dbPlantilla = 'amber_template';

        try {
            // 1) Verificar que exista la plantilla
            $st = $pdo->prepare("SHOW DATABASES LIKE ?");
            $st->execute([$dbPlantilla]);
            if (!$st->fetch()) {
                throw new Exception("No existe la base plantilla {$dbPlantilla}");
            }

            // 2) Verificar que no exista ya la base destino
            $st = $pdo->prepare("SHOW DATABASES LIKE ?");
            $st->execute([$dbNueva]);
            if ($st->fetch()) {
                throw new Exception("La base {$dbNueva} ya existe.");
            }

            // 3) Crear base nueva
            $pdo->exec("CREATE DATABASE `{$dbNueva}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // 4) Obtener tablas de la plantilla
            $st = $pdo->query("SHOW TABLES FROM `{$dbPlantilla}`");
            $tablas = $st->fetchAll(PDO::FETCH_COLUMN);

            if (!$tablas) {
                throw new Exception("La plantilla {$dbPlantilla} no tiene tablas.");
            }

            // 5) Copiar estructura + datos tabla por tabla
            foreach ($tablas as $tabla) {
                $pdo->exec("CREATE TABLE `{$dbNueva}`.`{$tabla}` LIKE `{$dbPlantilla}`.`{$tabla}`");
                $pdo->exec("INSERT INTO `{$dbNueva}`.`{$tabla}` SELECT * FROM `{$dbPlantilla}`.`{$tabla}`");
            }

            // 6) Limpiar usuarios existentes de la plantilla y crear admin inicial
            $pdo->exec("DELETE FROM `{$dbNueva}`.`usuarios`");

            $st = $pdo->prepare("
                INSERT INTO `{$dbNueva}`.`usuarios`
                (usuario, clave, nombre, acceso_tesoreria, acceso_caja, log_actividad, activo)
                VALUES (?, ?, ?, 1, 1, 1, 1)
            ");
            $st->execute([$usuarioAdmin, $claveAdmin, $nombre]);

            $mensaje = "Cliente creado correctamente. Base: {$dbNueva}";
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Alta de cliente</title>
<style>
body{
    font-family:Arial, sans-serif;
    background:#f4f6f9;
    margin:0;
    padding:30px;
}
.card{
    max-width:700px;
    margin:0 auto;
    background:#fff;
    padding:24px;
    border-radius:12px;
    box-shadow:0 4px 16px rgba(0,0,0,.08);
}
h1{margin-top:0}
label{
    display:block;
    margin-top:14px;
    margin-bottom:6px;
    font-weight:bold;
}
input{
    width:100%;
    padding:10px;
    border:1px solid #ccc;
    border-radius:8px;
    font-size:14px;
}
button{
    margin-top:20px;
    padding:12px 18px;
    border:none;
    border-radius:8px;
    background:#2563eb;
    color:#fff;
    font-size:15px;
    cursor:pointer;
}
.msg-ok{
    background:#ecfdf5;
    border:1px solid #10b981;
    color:#065f46;
    padding:12px;
    border-radius:8px;
    margin-bottom:14px;
}
.msg-err{
    background:#fef2f2;
    border:1px solid #ef4444;
    color:#991b1b;
    padding:12px;
    border-radius:8px;
    margin-bottom:14px;
}
.small{
    margin-top:16px;
    color:#555;
    font-size:13px;
}
code{
    background:#f1f5f9;
    padding:2px 6px;
    border-radius:4px;
}
</style>
</head>
<body>
<div class="card">
    <h1>Alta de cliente</h1>

    <?php if ($mensaje !== ''): ?>
        <div class="msg-ok"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="small">
            Ingreso sugerido:
            <br>
            <code>login.php?cliente=<?= htmlspecialchars($codigo ?? '', ENT_QUOTES, 'UTF-8') ?></code>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="msg-err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post">
        <label for="codigo">Código del cliente</label>
        <input type="text" id="codigo" name="codigo" placeholder="Ej: osetra" required>

        <label for="nombre">Nombre visible / admin inicial</label>
        <input type="text" id="nombre" name="nombre" placeholder="Ej: OSETRA" required>

        <label for="usuario_admin">Usuario admin inicial</label>
        <input type="text" id="usuario_admin" name="usuario_admin" value="admin" required>

        <label for="clave_admin">Clave admin inicial</label>
        <input type="text" id="clave_admin" name="clave_admin" value="1234" required>

        <button type="submit">Crear cliente</button>
    </form>

    <div class="small">
        La base creada tendrá formato <code>amber_codigo</code> y copiará todo desde <code>amber_template</code>.
    </div>
</div>
</body>
</html>
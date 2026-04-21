<?php
session_start();

if (empty($_SESSION['logueado']) || empty($_SESSION['usuario']) || empty($_SESSION['cliente'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/api/permisos.php';

$cliente = $_SESSION['cliente'];
$usuario = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Sistema Tesorería</title>
<style>
body{font-family:Arial;background:#f4f6f9;text-align:center}
.panel{margin-top:40px;display:flex;justify-content:center;gap:20px;flex-wrap:wrap}
.modulo{background:white;padding:30px;width:220px;border-radius:10px;box-shadow:0 4px 10px rgba(0,0,0,.1);text-decoration:none;color:#333;font-size:18px}
.logout{margin-top:40px;display:inline-block;padding:10px 20px;background:#e74c3c;color:white;text-decoration:none;border-radius:6px}
</style>
</head>
<body>

<h1>Sistema Tesorería</h1>
<p>Bienvenido <b><?php echo htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8'); ?></b></p>
<p>Cliente actual: <b><?php echo htmlspecialchars($cliente, ENT_QUOTES, 'UTF-8'); ?></b></p>

<div class="panel">
<?php if (!empty($_SESSION['acceso_tesoreria'])): ?>
    <a class="modulo" href="tesoreria/">💰 Tesorería</a>
<?php endif; ?>

<?php if (!empty($_SESSION['acceso_caja'])): ?>
    <a class="modulo" href="caja/">📦 Caja </a>
<?php endif; ?>

<?php if (tiene_permiso('log_actividad')): ?>
    <a class="modulo" href="report/log_actividad.php">📜 Movimientos</a>
<?php endif; ?>
</div>

<a class="logout" href="logout.php">Cerrar sesión</a>

</body>
</html> 
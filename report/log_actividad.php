<?php
require_once __DIR__ . '/api/auth.php';
require_once __DIR__ . '/api/permisos.php';
require_once __DIR__ . '/api/db.php';

$cliente = $_SESSION['cliente'];
$usuario = $_SESSION['usuario'];

// opcional pero recomendable
requiere_permiso('log_actividad');

$pdo = db();

$stmt = $pdo->query("SELECT * FROM log_actividad ORDER BY fecha DESC LIMIT 200");
$logs = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Actividad del sistema</title>
<link rel="stylesheet" href="../assets/styles.css">
</head>
<body>
<div class="container">

<div style="display:flex; justify-content:flex-end; margin-bottom:10px;">
    <a href="/menu.php" class="btn ghost">← Menú principal</a>
</div>

<h2>Actividad del sistema</h2>
<table class="table">
<thead>
<tr><th>Fecha</th><th>Usuario</th><th>Módulo</th><th>Acción</th><th>IP</th></tr>
</thead>
<tbody>
<?php foreach($logs as $l){ ?>
<tr>
<td><?= htmlspecialchars($l["fecha"]) ?></td>
<td><?= htmlspecialchars($l["usuario"]) ?></td>
<td><?= htmlspecialchars($l["modulo"]) ?></td>
<td><?= htmlspecialchars($l["accion"]) ?></td>
<td><?= htmlspecialchars($l["ip"]) ?></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
</body>
</html>
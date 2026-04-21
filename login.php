<?php
session_start();
require_once __DIR__ . '/api/db.php';

$cliente = $_GET['cliente'] ?? $_POST['cliente'] ?? 'demo';
$cliente = strtolower(trim((string)$cliente));
if ($cliente === '' || !preg_match('/^[a-z0-9_-]+$/', $cliente)) {
    $cliente = 'demo';
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave   = trim($_POST['clave'] ?? '');

    try {
        $pdo = db_login($cliente);

        $st = $pdo->prepare("
            SELECT id, usuario, clave, nombre, acceso_tesoreria, acceso_caja, log_actividad, activo
            FROM usuarios
            WHERE usuario = ?
            LIMIT 1
        ");
        $st->execute([$usuario]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $error = 'Usuario inexistente';
        } elseif ((string)$row['activo'] !== '1') {
            $error = 'Usuario inactivo';
        } elseif ((string)$row['clave'] !== $clave) {
            $error = 'Clave incorrecta';
        } else {
            $_SESSION['logueado'] = true;
            $_SESSION['cliente'] = $cliente;
            $_SESSION['usuario'] = $row['usuario'];
            $_SESSION['nombre'] = $row['nombre'];
            $_SESSION['acceso_tesoreria'] = (int)$row['acceso_tesoreria'];
            $_SESSION['acceso_caja'] = (int)$row['acceso_caja'];
            $_SESSION['log_actividad'] = (int)$row['log_actividad'];
            $_SESSION['activo'] = (int)$row['activo'];

            header('Location: menu.php');
            exit;
        }
    } catch (Throwable $e) {
        $error = 'Error al conectar con la base del cliente';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ingreso - Sistema Tesorería</title>

<style>
    *{
        box-sizing:border-box;
    }

    body{
        margin:0;
        min-height:100vh;
        font-family:"Segoe UI", Arial, sans-serif;
        background:
            linear-gradient(135deg, #dbeafe 0%, #eff6ff 35%, #f8fafc 100%);
        display:flex;
        align-items:center;
        justify-content:center;
        color:#0f172a;
    }

    .login-shell{
        width:100%;
        max-width:420px;
        padding:24px;
    }

    .login-card{
        background:#ffffff;
        border-radius:16px;
        box-shadow:0 18px 45px rgba(15, 23, 42, 0.15);
        padding:34px 30px 28px;
        border:1px solid #e2e8f0;
    }

    .brand-top{
        display:flex;
        align-items:center;
        gap:12px;
        margin-bottom:8px;
    }

    .brand-logo{
        width:42px;
        height:42px;
        border-radius:10px;
        background:linear-gradient(135deg, #0ea5e9, #2563eb);
        display:flex;
        align-items:center;
        justify-content:center;
        color:#fff;
        font-size:22px;
        font-weight:700;
    }

    .brand-title{
        font-size:22px;
        font-weight:600;
        margin:0;
        color:#0f172a;
    }

    .brand-subtitle{
        margin:6px 0 10px;
        color:#64748b;
        font-size:14px;
    }

    .cliente-actual{
        margin:0 0 20px;
        color:#334155;
        font-size:13px;
    }

    .field{
        margin-bottom:16px;
    }

    .field label{
        display:block;
        margin-bottom:7px;
        font-size:13px;
        font-weight:600;
        color:#334155;
    }

    .input-wrap{
        position:relative;
    }

    .input-icon{
        position:absolute;
        left:12px;
        top:50%;
        transform:translateY(-50%);
        font-size:15px;
        color:#64748b;
        pointer-events:none;
    }

    .input{
        width:100%;
        height:46px;
        padding:0 14px 0 40px;
        border:1px solid #cbd5e1;
        border-radius:10px;
        outline:none;
        font-size:14px;
        background:#fff;
        transition:border-color .2s, box-shadow .2s;
    }

    .input:focus{
        border-color:#2563eb;
        box-shadow:0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .error{
        margin:0 0 16px;
        padding:11px 12px;
        border-radius:10px;
        background:#fef2f2;
        border:1px solid #fecaca;
        color:#b91c1c;
        font-size:13px;
    }

    .btn{
        width:100%;
        height:46px;
        border:none;
        border-radius:10px;
        background:linear-gradient(135deg, #2563eb, #1d4ed8);
        color:#fff;
        font-size:15px;
        font-weight:600;
        cursor:pointer;
        transition:transform .15s, box-shadow .2s, opacity .2s;
        box-shadow:0 8px 18px rgba(37, 99, 235, 0.22);
    }

    .btn:hover{
        transform:translateY(-1px);
    }

    .btn:active{
        transform:translateY(0);
    }

    .footer-note{
        margin-top:18px;
        text-align:center;
        font-size:12px;
        color:#64748b;
    }

    @media (max-width: 480px){
        .login-card{
            padding:26px 20px 22px;
            border-radius:14px;
        }

        .brand-title{
            font-size:20px;
        }
    }
</style>
</head>
<body>

<div class="login-shell">
    <div class="login-card">
        <div class="brand-top">
            <div class="brand-logo">T</div>
            <h1 class="brand-title">Sistema Tesorería</h1>
        </div>

        <div class="brand-subtitle">
            Ingresá con tu usuario para acceder al sistema.
        </div>

        <div class="cliente-actual">
            Cliente actual: <b><?php echo htmlspecialchars($cliente, ENT_QUOTES, 'UTF-8'); ?></b>
        </div>

        <?php if ($error !== ""): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php?cliente=<?php echo urlencode($cliente); ?>" autocomplete="off">
            <input type="hidden" name="cliente" value="<?php echo htmlspecialchars($cliente, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="field">
                <label for="usuario">Usuario</label>
                <div class="input-wrap">
                    <span class="input-icon">👤</span>
                    <input
                        class="input"
                        type="text"
                        id="usuario"
                        name="usuario"
                        placeholder="Ingresá tu usuario"
                        required
                        autofocus
                    >
                </div>
            </div>

            <div class="field">
                <label for="clave">Contraseña</label>
                <div class="input-wrap">
                    <span class="input-icon">🔒</span>
                    <input
                        class="input"
                        type="password"
                        id="clave"
                        name="clave"
                        placeholder="Ingresá tu contraseña"
                        required
                    >
                </div>
            </div>

            <button class="btn" type="submit">Ingresar</button>
        </form>

        <div class="footer-note">
            Acceso seguro al módulo de tesorería
        </div>
    </div>
</div>

</body>
</html>
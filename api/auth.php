
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logueado']) || empty($_SESSION['usuario']) || empty($_SESSION['cliente'])) {
    header('Location: /login.php');
    exit;
}

if (isset($_GET['cliente']) && $_GET['cliente'] !== $_SESSION['cliente']) {
    session_unset();
    session_destroy();
    header('Location: /login.php');
    exit;
}
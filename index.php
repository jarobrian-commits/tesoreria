<?php
session_start();

if (!empty($_SESSION['logueado']) && !empty($_SESSION['usuario']) && !empty($_SESSION['cliente'])) {
    header('Location: /menu.php');
    exit;
}

$cliente = $_GET['cliente'] ?? 'demo';
$cliente = strtolower(trim((string)$cliente));

if ($cliente === '' || !preg_match('/^[a-z0-9_-]+$/', $cliente)) {
    $cliente = 'demo';
}

header('Location: /login.php?cliente=' . urlencode($cliente));
exit;
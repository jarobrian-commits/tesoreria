<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function verificar_login() {
    if (empty($_SESSION['logueado']) || empty($_SESSION['usuario']) || empty($_SESSION['cliente'])) {
        header("Location: /tesoreria/login.php");
        exit;
    }
}

function verificar_tesoreria() {
    verificar_login();
    if (empty($_SESSION["acceso_tesoreria"])) {
        die("No tiene permiso para Tesorería");
    }
}

function verificar_caja() {
    verificar_login();
    if (empty($_SESSION["acceso_caja"])) {
        die("No tiene permiso para Caja Chica");
    }
}
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function tiene_permiso(string $permiso): bool {
    return !empty($_SESSION[$permiso]);
}

function requiere_permiso(string $permiso): void {
    if (empty($_SESSION[$permiso])) {
        die("Acceso denegado");
    }
}
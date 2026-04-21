<?php

require_once __DIR__ . "/db.php";

function registrar_log($usuario,$accion,$modulo){

    $pdo = db();

    $ip = $_SERVER["REMOTE_ADDR"] ?? "";

    $stmt = $pdo->prepare("
        INSERT INTO log_actividad
        (usuario,accion,modulo,fecha,ip)
        VALUES (?,?,?,NOW(),?)
    ");

    $stmt->execute([
        $usuario,
        $accion,
        $modulo,
        $ip
    ]);

}
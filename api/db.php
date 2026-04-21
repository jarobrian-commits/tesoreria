<?php
function db() {
    static $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = $_ENV['MYSQLHOST'] ?? 'mysql.railway.internal';
    $port = $_ENV['MYSQLPORT'] ?? '3306';
    $db   = $_ENV['MYSQLDATABASE'] ?? 'railway';
    $user = $_ENV['MYSQLUSER'] ?? 'root';
    $pass = $_ENV['MYSQLPASSWORD'] ?? '';

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        die('Error conexión DB: ' . $e->getMessage());
    }

    return $pdo;
}
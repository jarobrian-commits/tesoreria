<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Valida el código de cliente
 */
function amber_validar_codigo_cliente(string $codigo): string
{
    $codigo = strtolower(trim($codigo));

    if ($codigo === '' || !preg_match('/^[a-z0-9_-]+$/', $codigo)) {
        die('Cliente inválido');
    }

    return $codigo;
}

/**
 * Genera nombre de base por cliente
 */
function amber_dbname_por_cliente(string $codigo): string
{
    return $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?? 'railway';
}

/**
 * Configuración de conexión (Railway compatible)
 */
function amber_db_config(): array
{
    return [
        'host' => $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST') ?: 'mysql.railway.internal',
        'port' => $_ENV['MYSQLPORT'] ?? getenv('MYSQLPORT') ?: '3306',
        'user' => $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER') ?: 'root',
        'pass' => $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?: '',
    ];
}

/**
 * Conexión para login (sin sesión previa)
 */
function db_login(string $cliente): PDO
{
    $cfg = amber_db_config();
    $dbname = amber_dbname_por_cliente($cliente);

    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$dbname};charset=utf8mb4";

    return new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/**
 * Conexión principal (usa sesión)
 */
function db(): PDO
{
    static $pdo = null;
    static $pdo_dbname = null;

    if (empty($_SESSION['logueado']) || empty($_SESSION['usuario']) || empty($_SESSION['cliente'])) {
        http_response_code(401);
        die('Sesión no válida');
    }

    $cfg = amber_db_config();

    $cliente = amber_validar_codigo_cliente((string)$_SESSION['cliente']);
    $dbname = amber_dbname_por_cliente($cliente);

    if ($pdo instanceof PDO && $pdo_dbname === $dbname) {
        return $pdo;
    }

    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$dbname};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo_dbname = $dbname;

        return $pdo;

    } catch (PDOException $e) {
        http_response_code(500);
        die('Error de conexión a base de datos: ' . $e->getMessage());
    }
}
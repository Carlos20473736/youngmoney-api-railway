<?php
/**
 * Configuração Global do Banco de Dados
 * 
 * Este arquivo mapeia as variáveis de ambiente do Railway (MYSQLHOST, MYSQLUSER, etc.)
 * para as variáveis antigas (DB_HOST, DB_USER, etc.) usadas pelos endpoints antigos.
 * 
 * Inclua este arquivo no início de qualquer endpoint que precise de conexão ao banco:
 * require_once __DIR__ . '/../../db_config.php';
 */

// Mapear variáveis do Railway para variáveis antigas
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
}

if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('MYSQLPORT') ?: '3306');
}

if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('MYSQLDATABASE') ?: 'railway');
}

if (!defined('DB_USER')) {
    define('DB_USER', getenv('MYSQLUSER') ?: 'root');
}

if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', getenv('MYSQLPASSWORD') ?: '');
}

// Também definir as variáveis de ambiente antigas para compatibilidade
if (!getenv('DB_HOST')) {
    putenv('DB_HOST=' . DB_HOST);
}

if (!getenv('DB_PORT')) {
    putenv('DB_PORT=' . DB_PORT);
}

if (!getenv('DB_NAME')) {
    putenv('DB_NAME=' . DB_NAME);
}

if (!getenv('DB_USER')) {
    putenv('DB_USER=' . DB_USER);
}

if (!getenv('DB_PASSWORD')) {
    putenv('DB_PASSWORD=' . DB_PASSWORD);
}

/**
 * Função helper para obter conexão PDO
 * Usa as variáveis mapeadas acima
 */
function getPDOConnection() {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erro de conexão PDO: " . $e->getMessage());
        throw new Exception("Erro ao conectar ao banco de dados");
    }
}

/**
 * Função helper para obter conexão MySQLi
 * Usa as variáveis mapeadas acima
 */
function getMySQLiConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, (int)DB_PORT);
        
        if ($conn->connect_error) {
            throw new Exception("Erro de conexão: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        
        return $conn;
    } catch (Exception $e) {
        error_log("Erro de conexão MySQLi: " . $e->getMessage());
        throw new Exception("Erro ao conectar ao banco de dados");
    }
}
?>

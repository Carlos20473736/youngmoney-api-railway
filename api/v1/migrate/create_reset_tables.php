<?php
/**
 * Migration: Criar tabelas de logs de reset
 * 
 * Este arquivo cria as tabelas necessárias para armazenar logs dos resets
 * de check-in, roleta e ranking.
 * 
 * Acesso: POST /api/v1/migrate/create_reset_tables.php?token=migration_token
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar token de segurança
$token = $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$expectedToken = getenv('MIGRATION_TOKEN') ?: 'migration_token_2024';

// Remover "Bearer " se presente
if (strpos($token, 'Bearer ') === 0) {
    $token = substr($token, 7);
}

if (empty($token) || $token !== $expectedToken) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Token inválido ou não fornecido',
        'required_param' => '?token=seu_token_aqui'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Conectar ao banco de dados usando MySQLi
    $db_host = $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST') ?: 'localhost';
    $db_user = $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER') ?: 'root';
    $db_pass = $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?: '';
    $db_name = $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?: 'railway';
    $db_port = $_ENV['MYSQLPORT'] ?? getenv('MYSQLPORT') ?: 3306;
    
    $conn = mysqli_init();
    if (!$conn) {
        throw new Exception("mysqli_init failed");
    }
    
    $success = $conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
    if (!$success) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    $migrations = [];
    $errors = [];
    
    // 1. Criar tabela checkin_reset_logs
    $sql1 = "CREATE TABLE IF NOT EXISTS checkin_reset_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reset_type VARCHAR(50) NOT NULL DEFAULT 'manual',
        triggered_by VARCHAR(100) NOT NULL,
        reset_datetime DATETIME,
        status VARCHAR(50) NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_reset_type (reset_type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql1)) {
        $migrations[] = [
            'table' => 'checkin_reset_logs',
            'status' => 'created',
            'message' => 'Tabela criada com sucesso'
        ];
    } else {
        $errors[] = [
            'table' => 'checkin_reset_logs',
            'error' => $conn->error
        ];
    }
    
    // 2. Criar tabela spin_reset_logs
    $sql2 = "CREATE TABLE IF NOT EXISTS spin_reset_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reset_type VARCHAR(50) NOT NULL DEFAULT 'manual',
        triggered_by VARCHAR(100) NOT NULL,
        spins_deleted INT DEFAULT 0,
        reset_datetime DATETIME,
        status VARCHAR(50) NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_reset_type (reset_type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql2)) {
        $migrations[] = [
            'table' => 'spin_reset_logs',
            'status' => 'created',
            'message' => 'Tabela criada com sucesso'
        ];
    } else {
        $errors[] = [
            'table' => 'spin_reset_logs',
            'error' => $conn->error
        ];
    }
    
    // 3. Criar tabela ranking_reset_logs
    $sql3 = "CREATE TABLE IF NOT EXISTS ranking_reset_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reset_type VARCHAR(50) NOT NULL DEFAULT 'manual',
        triggered_by VARCHAR(100) NOT NULL,
        users_affected INT DEFAULT 0,
        reset_datetime DATETIME,
        status VARCHAR(50) NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_reset_type (reset_type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql3)) {
        $migrations[] = [
            'table' => 'ranking_reset_logs',
            'status' => 'created',
            'message' => 'Tabela criada com sucesso'
        ];
    } else {
        $errors[] = [
            'table' => 'ranking_reset_logs',
            'error' => $conn->error
        ];
    }
    
    $conn->close();
    
    // Retornar resultado
    http_response_code(200);
    echo json_encode([
        'success' => count($errors) === 0,
        'message' => 'Migration executada',
        'migrations' => $migrations,
        'errors' => $errors,
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao executar migration',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

<?php
/**
 * API de Reset do Check-in Diário
 * 
 * Endpoint: POST /api/v1/reset/checkin.php
 * 
 * Função: Reseta o check-in do dia para permitir novo check-in
 * 
 * Lógica:
 * - Atualiza last_reset_datetime para o horário atual
 * - Permite que usuários façam novo check-in
 * - Preserva histórico de check-ins anteriores
 * 
 * Segurança:
 * - Token obrigatório via query parameter ou header
 * - Validação de conexão com banco de dados
 * - Transação para garantir consistência
 */

error_reporting(0);
ini_set('display_errors', '0');

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
$expectedToken = getenv('RESET_TOKEN') ?: 'ym_reset_checkin_2024_secure';

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

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

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
    
    // Obter horário atual
    $current_datetime = date('Y-m-d H:i:s');
    $current_date = date('Y-m-d');
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // Atualizar last_reset_datetime para permitir novo check-in
        $stmt = $conn->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_at)
            VALUES ('last_reset_datetime', ?, NOW())
            ON DUPLICATE KEY UPDATE 
                setting_value = ?,
                updated_at = NOW()
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ss", $current_datetime, $current_datetime);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Registrar log do reset (opcional)
        $stmt = $conn->prepare("
            INSERT INTO checkin_reset_logs 
            (reset_type, triggered_by, reset_datetime, status) 
            VALUES ('manual', 'api-reset', NOW(), 'success')
        ");
        
        if ($stmt) {
            $stmt->execute();
            $stmt->close();
        }
        
        // Commit da transação
        $conn->commit();
        
        // Retornar sucesso
        echo json_encode([
            'success' => true,
            'message' => 'Check-in resetado com sucesso!',
            'data' => [
                'reset_type' => 'checkin',
                'description' => 'Usuários podem fazer novo check-in',
                'last_reset_datetime' => $current_datetime,
                'reset_date' => $current_date,
                'timezone' => 'America/Sao_Paulo (GMT-3)',
                'timestamp' => time()
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao executar reset do check-in',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

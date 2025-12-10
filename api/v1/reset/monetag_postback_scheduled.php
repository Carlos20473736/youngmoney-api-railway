<?php
/**
 * API de Reset do Monetag Postback
 * 
 * Endpoint: POST /api/v1/reset/monetag_postback_scheduled.php
 * 
 * Função: Reseta impressões e clicks do Monetag deletando eventos
 * 
 * Lógica:
 * - Deleta TODOS os eventos de postback do Monetag (tabela monetag_events)
 * - Permite que usuários façam novos postbacks
 * - Reseta contadores de impressões e clicks
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
$expectedToken = getenv('RESET_TOKEN') ?: 'ym_reset_monetag_scheduled_2024_secure';

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
    
    // Obter data e hora atual
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');
    $current_datetime = date('Y-m-d H:i:s');
    
    // Iniciar transacao
    $conn->begin_transaction();
    
    try {
        // 1. Contar quantos eventos de Monetag serão deletados
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM monetag_events
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $eventsDeleted = $row['total'] ?? 0;
        $stmt->close();
        
        // 2. Contar quantos usuários únicos têm eventos
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT user_id) as total 
            FROM monetag_events
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $usersAffected = $row['total'] ?? 0;
        $stmt->close();
        
        // 3. Deletar TODOS os eventos de Monetag
        $stmt = $conn->prepare("
            DELETE FROM monetag_events
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // 4. Registrar log do reset
        $stmt = $conn->prepare("
            INSERT INTO monetag_reset_logs 
            (reset_type, triggered_by, events_deleted, users_affected, reset_datetime, status) 
            VALUES ('manual', 'api-call', ?, ?, NOW(), 'success')
        ");
        
        if ($stmt) {
            $stmt->bind_param("ii", $eventsDeleted, $usersAffected);
            $stmt->execute();
            $stmt->close();
        }
        
        // Commit da transação
        $conn->commit();
        
        // Retornar sucesso
        echo json_encode([
            'success' => true,
            'message' => 'Reset do Monetag postback executado com sucesso!',
            'data' => [
                'reset_type' => 'monetag_postback_manual',
                'description' => 'Todos os eventos de postback foram deletados',
                'current_time' => $current_time,
                'events_deleted' => $eventsDeleted,
                'users_affected' => $usersAffected,
                'impressions_reset_to' => 0,
                'clicks_reset_to' => 0,
                'reset_date' => $current_date,
                'reset_datetime' => $current_datetime,
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
        'error' => 'Erro ao executar reset do Monetag postback',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

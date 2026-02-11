<?php
/**
 * API de Reset da Rouleta v2
 * 
 * Endpoint: GET/POST /api/v1/reset/roulette_scheduled_v2.php
 * 
 * Função: Reseta os giros da rouleta MANTENDO giros não usados
 * 
 * Lógica:
 * - Deleta APENAS giros usados (is_used = 1) de user_spins
 * - Mantém giros não usados (is_used = 0)
 * - Permite que usuários façam novos giros
 */

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar token de segurança
$token = $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$expectedToken = getenv('RESET_TOKEN') ?: 'ym_reset_roulette_scheduled_2024_secure';

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

date_default_timezone_set('America/Sao_Paulo');

try {
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
    
    $current_date = date('Y-m-d');
    $current_datetime = date('Y-m-d H:i:s');
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // 1. Contar giros usados que serão deletados
        $result = $conn->query("
            SELECT COUNT(*) as total FROM user_spins 
            WHERE is_used = 1
        ");
        $row = $result->fetch_assoc();
        $usedSpinsDeleted = $row['total'] ?? 0;
        
        // 2. Contar giros não usados que serão mantidos
        $result = $conn->query("
            SELECT COUNT(*) as total FROM user_spins 
            WHERE is_used = 0
        ");
        $row = $result->fetch_assoc();
        $unusedSpinsKept = $row['total'] ?? 0;
        
        // 3. Deletar APENAS giros usados (is_used = 1)
        $deleteResult = $conn->query("
            DELETE FROM user_spins 
            WHERE is_used = 1
        ");
        
        if (!$deleteResult) {
            throw new Exception("Delete failed: " . $conn->error);
        }
        
        // 4. Registrar log do reset
        $stmt = $conn->prepare("
            INSERT INTO spin_reset_logs 
            (spins_deleted, reset_datetime, triggered_by) 
            VALUES (?, NOW(), 'api_roulette_scheduled_v2')
        ");
        
        if ($stmt) {
            $stmt->bind_param("i", $usedSpinsDeleted);
            $stmt->execute();
            $stmt->close();
        }
        
        // Commit da transação
        $conn->commit();
        
        // Retornar sucesso
        echo json_encode([
            'success' => true,
            'message' => 'Reset da rouleta executado com sucesso!',
            'data' => [
                'reset_type' => 'roulette_v2',
                'description' => 'Giros usados deletados, giros não usados mantidos',
                'used_spins_deleted' => $usedSpinsDeleted,
                'unused_spins_kept' => $unusedSpinsKept,
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
        'error' => 'Erro ao executar reset da rouleta',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

<?php
/**
 * API de Reset da Rouleta
 * 
 * Endpoint: GET/POST /api/v1/reset/roulette_scheduled.php
 * 
 * Função: Reseta os giros da rouleta IMEDIATAMENTE ao ser chamado
 * 
 * Lógica:
 * - Deleta TODOS os registros de spin_history
 * - Permite que usuários façam novos giros
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
$expectedToken = getenv('RESET_TOKEN') ?: 'ym_reset_roulette_scheduled_2024_secure';

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
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // Contar quantos registros de spin serão deletados
        $result = $conn->query("SELECT COUNT(*) as total FROM spin_history");
        $row = $result->fetch_assoc();
        $spinsDeleted = $row['total'] ?? 0;
        
        // Deletar TODOS os registros de spin (reset completo)
        $deleteResult = $conn->query("DELETE FROM spin_history");
        
        if (!$deleteResult) {
            throw new Exception("Delete failed: " . $conn->error);
        }
        
        // Registrar log do reset
        $stmt = $conn->prepare("
            INSERT INTO spin_reset_logs 
            (spins_deleted, reset_datetime, triggered_by) 
            VALUES (?, NOW(), 'api_roulette_scheduled')
        ");
        
        if ($stmt) {
            $stmt->bind_param("i", $spinsDeleted);
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
                'reset_type' => 'roulette_immediate',
                'description' => 'Todos os giros foram deletados',
                'spins_deleted' => $spinsDeleted,
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

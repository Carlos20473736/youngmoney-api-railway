<?php
/**
 * API de Reset da Roleta de Giros
 * 
 * Endpoint: POST /api/v1/reset/spin.php
 * 
 * Função: Reseta os giros consumidos no dia anterior
 * 
 * Lógica:
 * - Deleta registros de spin_history de hoje
 * - Permite que usuários façam novos giros
 * - Respeita o limite diário de giros configurado
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
$expectedToken = getenv('RESET_TOKEN') ?: 'ym_reset_spin_2024_secure';

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
    
    // CORREÇÃO: Definir timezone na conexão MySQL
    $conn->query("SET time_zone = '-03:00'");
    
    // Obter data atual
    $current_date = date('Y-m-d');
    $current_datetime = date('Y-m-d H:i:s');
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // Contar quantos registros de spin serão deletados
        // CORREÇÃO: Usar DATE(CONVERT_TZ()) para converter UTC para Brasília
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM spin_history 
            WHERE DATE(CONVERT_TZ(created_at, '+00:00', '-03:00')) = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $current_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $spinsDeleted = $row['total'] ?? 0;
        $stmt->close();
        
        // Deletar registros de spin de hoje
        // CORREÇÃO: Usar DATE(CONVERT_TZ()) para converter UTC para Brasília
        $stmt = $conn->prepare("
            DELETE FROM spin_history 
            WHERE DATE(CONVERT_TZ(created_at, '+00:00', '-03:00')) = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $current_date);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Registrar log do reset (opcional)
        $stmt = $conn->prepare("
            INSERT INTO spin_reset_logs 
            (reset_type, triggered_by, spins_deleted, reset_datetime, status) 
            VALUES ('manual', 'api-reset', ?, NOW(), 'success')
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
            'message' => 'Giros da roleta resetados com sucesso!',
            'data' => [
                'reset_type' => 'spin',
                'description' => 'Giros consumidos foram deletados',
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
        'error' => 'Erro ao executar reset da roleta',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

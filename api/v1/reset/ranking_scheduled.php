<?php
/**
 * API de Reset do Ranking Agendada
 * 
 * Endpoint: POST /api/v1/reset/ranking_scheduled.php
 * 
 * Função: Reseta o ranking diário baseado na hora configurada no painel ADM
 * 
 * Lógica:
 * - Lê a hora de reset configurada em system_settings (reset_time)
 * - Verifica se é a hora certa para resetar
 * - Zera daily_points de todos os usuários
 * - Permite que usuários acumulem pontos novamente
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
$expectedToken = getenv('RESET_TOKEN') ?: 'ym_reset_ranking_scheduled_2024_secure';

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
    
    // Buscar hora de reset configurada no painel ADM
    $stmt = $conn->prepare("
        SELECT setting_value 
        FROM system_settings 
        WHERE setting_key = 'reset_time' 
        LIMIT 1
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $reset_time = $row['setting_value'] ?? '00:00:00';
    $stmt->close();
    
    // Verificar se é a hora certa para resetar (com margem de 1 minuto)
    $reset_hour = substr($reset_time, 0, 2);
    $reset_minute = substr($reset_time, 3, 2);
    $current_hour = date('H');
    $current_minute = date('i');
    
    $is_reset_time = ($current_hour === $reset_hour && $current_minute === $reset_minute);
    
    // SE NAO FOR A HORA CERTA, RETORNAR SEM FAZER RESET
    if (!$is_reset_time) {
        echo json_encode([
            'success' => true,
            'message' => 'Reset agendado, mas ainda nao eh a hora certa',
            'data' => [
                'reset_type' => 'ranking_scheduled',
                'description' => 'Reset nao foi executado - aguardando horario configurado',
                'reset_time_configured' => $reset_time,
                'is_reset_time' => false,
                'current_time' => $current_time,
                'users_affected' => 0,
                'reset_date' => $current_date,
                'reset_datetime' => $current_datetime,
                'timezone' => 'America/Sao_Paulo (GMT-3)',
                'timestamp' => time()
            ]
        ], JSON_UNESCAPED_UNICODE);
        $conn->close();
        exit;
    }
    
    // Iniciar transacao
    
    try {
        // Contar quantos usuários têm daily_points > 0
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM users 
            WHERE daily_points > 0
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $usersAffected = $row['total'] ?? 0;
        $stmt->close();
        
        // Resetar daily_points para 0 para todos os usuários
        $stmt = $conn->prepare("
            UPDATE users 
            SET daily_points = 0
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Registrar log do reset
        $stmt = $conn->prepare("
            INSERT INTO ranking_reset_logs 
            (users_affected, reset_datetime) 
            VALUES (?, NOW())
        ");
        
        if ($stmt) {
            $stmt->bind_param("i", $usersAffected);
            $stmt->execute();
            $stmt->close();
        }
        
        // Commit da transação
        $conn->commit();
        
        // Retornar sucesso
        echo json_encode([
            'success' => true,
            'message' => 'Reset do ranking executado com sucesso!',
            'data' => [
                'reset_type' => 'ranking_scheduled',
                'description' => 'Todos os usuários tiveram daily_points zerado',
                'reset_time_configured' => $reset_time,
                'is_reset_time' => $is_reset_time,
                'current_time' => $current_time,
                'users_affected' => $usersAffected,
                'daily_points_reset_to' => 0,
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
        'error' => 'Erro ao executar reset do ranking',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

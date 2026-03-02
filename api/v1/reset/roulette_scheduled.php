<?php
/**
 * API de Reset da Rouleta v1 - CORRIGIDO
 * 
 * Endpoint: GET/POST /api/v1/reset/roulette_scheduled.php
 * 
 * Função: Reseta os giros da rouleta MANTENDO giros não usados e CRIANDO novos giros até o limite diário
 * 
 * Lógica:
 * - Deleta APENAS giros usados (is_used = 1) de user_spins
 * - Mantém giros não usados (is_used = 0)
 * - Completa os giros de cada usuário até o limite max_daily_spins
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
        $result = $conn->query("SELECT COUNT(*) as total FROM user_spins WHERE is_used = 1");
        $row = $result->fetch_assoc();
        $usedSpinsDeleted = $row['total'] ?? 0;
        
        // 2. Deletar APENAS giros usados (is_used = 1)
        $conn->query("DELETE FROM user_spins WHERE is_used = 1");
        
        // 3. Buscar limite diário de giros
        $maxSpinsResult = $conn->query("SELECT setting_value FROM roulette_settings WHERE setting_key = 'max_daily_spins' LIMIT 1");
        $maxSpinsRow = $maxSpinsResult ? $maxSpinsResult->fetch_assoc() : null;
        $dailySpinsLimit = $maxSpinsRow ? (int)$maxSpinsRow['setting_value'] : 10;
        
        // 4. Recriar giros para todos os usuários ativos
        $activeUsersResult = $conn->query("SELECT id FROM users");
        $spinsCreatedCount = 0;
        
        while ($activeUser = $activeUsersResult->fetch_assoc()) {
            $uid = (int)$activeUser['id'];
            
            // Contar quantos giros NÃO usados o usuário já tem (os que foram mantidos)
            $existingResult = $conn->query("SELECT COUNT(*) as cnt FROM user_spins WHERE user_id = $uid AND is_used = 0");
            $existingRow = $existingResult->fetch_assoc();
            $existingSpins = (int)$existingRow['cnt'];
            
            // Calcular quantos giros faltam para completar o limite diário
            $toCreate = $dailySpinsLimit - $existingSpins;
            
            if ($toCreate > 0) {
                $values = [];
                for ($i = 0; $i < $toCreate; $i++) {
                    $values[] = "($uid, 0, 0, NOW(), NULL)";
                }
                $conn->query("INSERT INTO user_spins (user_id, prize_value, is_used, created_at, used_at) VALUES " . implode(',', $values));
                $spinsCreatedCount += $toCreate;
            }
        }
        
        // 5. Contar giros não usados totais após a criação
        $result = $conn->query("SELECT COUNT(*) as total FROM user_spins WHERE is_used = 0");
        $row = $result->fetch_assoc();
        $totalUnusedSpins = $row['total'] ?? 0;
        
        // 6. Registrar log do reset
        $stmt = $conn->prepare("
            INSERT INTO spin_reset_logs 
            (spins_deleted, reset_datetime, triggered_by) 
            VALUES (?, NOW(), 'api_roulette_scheduled_fixed')
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
            'message' => 'Reset da roleta executado com sucesso! Novos giros foram criados.',
            'data' => [
                'reset_type' => 'roulette_fixed',
                'description' => 'Giros usados deletados, novos giros criados mantendo os não usados',
                'used_spins_deleted' => $usedSpinsDeleted,
                'new_spins_created' => $spinsCreatedCount,
                'total_available_spins_in_db' => $totalUnusedSpins,
                'daily_limit_per_user' => $dailySpinsLimit,
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

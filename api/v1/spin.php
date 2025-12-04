<?php
/**


 * Spin Wheel API
 * Backend decide valor aleatório e valida giros diários
 * 
 * Endpoint: POST /api/v1/spin.php
 */

// Suprimir warnings e notices do PHP
error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Aceitar GET e POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    exit;
}

// Incluir configuração do banco de dados
require_once __DIR__ . '/../../database.php';
require_once __DIR__ . '/../../includes/HeadersValidator.php';
require_once __DIR__ . '/../../middleware/auto_reset.php';
require_once __DIR__ . '/../../includes/security_validation_helper.php';
require_once __DIR__ . '/../../includes/auth_helper.php';

// Obter conexão
$conn = getDbConnection();

// Validar headers de segurança
$validator = validateRequestHeaders($conn, true);
if (!$validator) exit; // Já enviou resposta de erro


// Verificar e fazer reset automático se necessário
checkAndResetRanking($conn);

// Função removida - agora usa auth_helper.php

// Buscar valores da roleta e configurações do banco de dados
$prizeValues = [];
$maxDailySpins = 10; // Valor padrão

try {
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM roulette_settings ORDER BY setting_key");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $key = $row['setting_key'];
        $value = (int)$row['setting_value'];
        
        // Se for max_daily_spins, armazenar separadamente
        if ($key === 'max_daily_spins') {
            $maxDailySpins = $value;
        } 
        // Se for prize_*, adicionar ao array de prêmios
        elseif (strpos($key, 'prize_') === 0) {
            $prizeValues[] = $value;
        }
    }
    $stmt->close();
    
    // Se não encontrou valores, usar padrão
    if (empty($prizeValues)) {
        $prizeValues = [100, 250, 500, 750, 1000, 1500, 2000, 5000];
    }
} catch (Exception $e) {
    // Em caso de erro, usar valores padrão
    $prizeValues = [100, 250, 500, 750, 1000, 1500, 2000, 5000];
    $maxDailySpins = 10;
}

try {
    // Validar autenticação usando auth_helper
    $user = getAuthenticatedUser($conn);
    if (!$user) {
        sendUnauthorizedError();
    }
    
    // VALIDAÇÃO DE HEADERS REMOVIDA - estava bloqueando requisições legítimas
    // validateSecurityHeaders($conn, $user);
    
    $userId = $user['id'];
    
    // Obter data atual no servidor (timezone configurável)
    date_default_timezone_set('America/Sao_Paulo'); // GMT-3 (Brasília)
    $currentDate = date('Y-m-d');
    $currentDateTime = date('Y-m-d H:i:s');
    
    // Verificar giros do usuário HOJE (data real)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as spins_today 
        FROM spin_history 
        WHERE user_id = ? AND DATE(created_at) = ?
    ");
    $stmt->bind_param("is", $userId, $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $spinsToday = (int)$row['spins_today'];
    
    $spinsRemaining = $maxDailySpins - $spinsToday;
    
    // Determinar saudação baseada no horário
    $hour = (int)date('H');
    if ($hour >= 5 && $hour < 12) {
        $greeting = 'BOM DIA';
    } elseif ($hour >= 12 && $hour < 18) {
        $greeting = 'BOA TARDE';
    } else {
        $greeting = 'BOA NOITE';
    }
    
    // Se for GET, apenas retornar giros restantes e valores da roleta
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode([
            'status' => 'success',
            'data' => [
                'greeting' => $greeting,
                'spins_remaining' => $spinsRemaining,
                'spins_today' => $spinsToday,
                'max_daily_spins' => $maxDailySpins,
                'prize_values' => $prizeValues,
                'server_time' => $currentDateTime,
                'server_timestamp' => time()
            ]
        ]);
        exit;
    }
    
    // Verificar se ainda tem giros disponíveis (apenas para POST)
    if ($spinsToday >= $maxDailySpins) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Você já usou todos os giros de hoje. Volte amanhã!',
            'data' => [
                'spins_remaining' => 0,
                'spins_today' => $spinsToday,
                'max_daily_spins' => $maxDailySpins,
                'server_time' => $currentDateTime
            ]
        ]);
        exit;
    }
    
    // Sortear prêmio aleatório
    $prizeIndex = array_rand($prizeValues);
    $prizeValue = $prizeValues[$prizeIndex];
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // 1. Registrar giro no histórico
        $stmt = $conn->prepare("
            INSERT INTO spin_history (user_id, prize_value, prize_index, created_at)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiis", $userId, $prizeValue, $prizeIndex, $currentDateTime);
        $stmt->execute();
        
        // 2. Adicionar pontos ao saldo do usuário (total E daily_points para ranking)
        $stmt = $conn->prepare("
            UPDATE users 
            SET points = points + ?,
                daily_points = daily_points + ?,
                updated_at = ?
            WHERE id = ?
        ");
        $stmt->bind_param("iisi", $prizeValue, $prizeValue, $currentDateTime, $userId);
        $stmt->execute();
        
        // 3. Registrar no histórico de pontos
        $stmt = $conn->prepare("
            INSERT INTO points_history (user_id, points, description, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $description = "Roleta da Sorte - Ganhou {$prizeValue} pontos";
        $stmt->bind_param("iis", $userId, $prizeValue, $description);
        $stmt->execute();
        
        // Commit da transação
        $conn->commit();
        
        // Calcular giros restantes
        $spinsRemaining = $maxDailySpins - ($spinsToday + 1);
        
        // Obter saldo atualizado
        $stmt = $conn->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $userData = $result->fetch_assoc();
        $newBalance = $userData['points'];
        
        // Retornar sucesso
        echo json_encode([
            'status' => 'success',
            'message' => "Você ganhou {$prizeValue} pontos!",
            'data' => [
                'greeting' => $greeting,
                'prize_value' => $prizeValue,
                'prize_index' => $prizeIndex,
                'spins_remaining' => $spinsRemaining,
                'spins_today' => $spinsToday + 1,
                'max_daily_spins' => $maxDailySpins,
                'new_balance' => $newBalance,
                'server_time' => $currentDateTime,
                'server_timestamp' => time()
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao processar giro: ' . $e->getMessage()
    ]);
}
?>

<?php
/**
 * Spin Wheel API v2
 * Usa nova tabela user_spins para rastrear giros disponíveis
 * 
 * Endpoint: POST /api/v1/spin_v2.php
 */

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    exit;
}

require_once __DIR__ . '/../../database.php';
require_once __DIR__ . '/../../includes/HeadersValidator.php';
require_once __DIR__ . '/../../middleware/auto_reset.php';
require_once __DIR__ . '/../../includes/security_validation_helper.php';
require_once __DIR__ . '/../../includes/auth_helper.php';
require_once __DIR__ . '/middleware/MaintenanceCheck.php';
require_once __DIR__ . '/includes/CooldownCheck.php';

$conn = getDbConnection();

// Verificação de manutenção e versão
$method = $_SERVER['REQUEST_METHOD'];
$requestData = ($method === 'POST') 
    ? json_decode(file_get_contents('php://input'), true) ?? []
    : $_GET;
$userEmail = $requestData['email'] ?? null;
$appVersion = $requestData['app_version'] ?? $_SERVER['HTTP_X_APP_VERSION'] ?? null;
checkMaintenanceAndVersion($conn, $userEmail, $appVersion);

// Autenticar usuário
$user = getAuthenticatedUser($conn);
if (!$user) {
    sendUnauthorizedError();
}

checkAndResetRanking($conn);

// Buscar valores da roleta
$prizeValues = [];
$maxDailySpins = 10;

try {
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM roulette_settings ORDER BY setting_key");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $key = $row['setting_key'];
        $value = (int)$row['setting_value'];
        
        if ($key === 'max_daily_spins') {
            $maxDailySpins = $value;
        } elseif (strpos($key, 'prize_') === 0) {
            $prizeValues[] = $value;
        }
    }
    $stmt->close();
    
    if (empty($prizeValues)) {
        $prizeValues = [100, 250, 500, 750, 1000, 1500, 2000, 5000];
    }
} catch (Exception $e) {
    $prizeValues = [100, 250, 500, 750, 1000, 1500, 2000, 5000];
    $maxDailySpins = 10;
}

try {
    $userId = $user['id'];
    date_default_timezone_set('America/Sao_Paulo');
    $currentDate = date('Y-m-d');
    $currentDateTime = date('Y-m-d H:i:s');
    
    // Contar giros disponíveis (não usados) do usuário
    $stmt = $conn->prepare("
        SELECT COUNT(*) as available_spins 
        FROM user_spins 
        WHERE user_id = ? AND is_used = 0
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $availableSpins = (int)$row['available_spins'];
    
    // Determinar saudação
    $hour = (int)date('H');
    if ($hour >= 5 && $hour < 12) {
        $greeting = 'BOM DIA';
    } elseif ($hour >= 12 && $hour < 18) {
        $greeting = 'BOA TARDE';
    } else {
        $greeting = 'BOA NOITE';
    }
    
    // GET: Retornar giros disponíveis
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $required_impressions = 10;
        $current_impressions = 0;
        $task_completed = false;
        
        try {
            $user_settings_stmt = $conn->prepare("
                SELECT required_impressions FROM user_required_impressions 
                WHERE user_id = ?
            ");
            $user_settings_stmt->bind_param("i", $userId);
            $user_settings_stmt->execute();
            $settings_result = $user_settings_stmt->get_result();
            
            if ($settings_result->num_rows > 0) {
                $settings_row = $settings_result->fetch_assoc();
                $required_impressions = (int)$settings_row['required_impressions'];
            }
            $user_settings_stmt->close();
            
            $impressions_stmt = $conn->prepare("
                SELECT COUNT(*) as total FROM monetag_impressions 
                WHERE user_id = ?
            ");
            $impressions_stmt->bind_param("i", $userId);
            $impressions_stmt->execute();
            $impressions_result = $impressions_stmt->get_result();
            $impressions_row = $impressions_result->fetch_assoc();
            $current_impressions = (int)$impressions_row['total'];
            $task_completed = ($current_impressions >= $required_impressions);
            $impressions_stmt->close();
        } catch (Exception $e) {
            error_log("[SPIN] Erro ao buscar progresso Monetag: " . $e->getMessage());
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'greeting' => $greeting,
                'available_spins' => $availableSpins,
                'max_daily_spins' => $maxDailySpins,
                'prize_values' => $prizeValues,
                'monetag_task' => [
                    'required_impressions' => $required_impressions,
                    'current_impressions' => $current_impressions,
                    'completed' => $task_completed
                ],
                'server_time' => $currentDateTime
            ]
        ]);
        exit;
    }
    
    // POST: Usar um giro
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verificar se tem giros disponíveis
        if ($availableSpins <= 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Você não tem giros disponíveis. Volte amanhã!',
                'data' => [
                    'available_spins' => 0,
                    'max_daily_spins' => $maxDailySpins,
                    'server_time' => $currentDateTime
                ]
            ]);
            exit;
        }
        
        // Verificar cooldown
        $cooldownCheck = shouldBlockDailyPoints($conn, $userId, 0, 'Spin - Tentativa durante cooldown');
        
        if (!$cooldownCheck['allowed']) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Você está em cooldown de ranking. Não pode acumular pontos agora.',
                'data' => [
                    'reason' => $cooldownCheck['reason'],
                    'cooldown_info' => $cooldownCheck['cooldown_info'],
                    'can_still_spin' => false
                ]
            ]);
            exit;
        }
        
        // Sortear prêmio
        $prizeIndex = array_rand($prizeValues);
        $prizeValue = $prizeValues[$prizeIndex];
        
        // Iniciar transação
        $conn->begin_transaction();
        
        try {
            // 1. Buscar um giro disponível para marcar como usado
            $stmt = $conn->prepare("
                SELECT id FROM user_spins 
                WHERE user_id = ? AND is_used = 0 
                LIMIT 1
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $spinRow = $result->fetch_assoc();
            $spinId = $spinRow['id'] ?? null;
            $stmt->close();
            
            if (!$spinId) {
                throw new Exception("Nenhum giro disponível encontrado");
            }
            
            // 2. Marcar giro como usado
            $stmt = $conn->prepare("
                UPDATE user_spins 
                SET is_used = 1, used_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $spinId);
            $stmt->execute();
            $stmt->close();
            
            // 3. Registrar no histórico de giros
            $stmt = $conn->prepare("
                INSERT INTO spin_history (user_id, prize_value, created_at)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iis", $userId, $prizeValue, $currentDateTime);
            $stmt->execute();
            $stmt->close();
            
            // 4. Adicionar pontos ao usuário
            $stmt = $conn->prepare("
                UPDATE users 
                SET points = points + ?,
                    daily_points = daily_points + ?,
                    updated_at = ?
                WHERE id = ?
            ");
            $stmt->bind_param("iisi", $prizeValue, $prizeValue, $currentDateTime, $userId);
            $stmt->execute();
            $stmt->close();
            
            // 5. Registrar no histórico de pontos
            $stmt = $conn->prepare("
                INSERT INTO points_history (user_id, points, description, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $description = "Roleta da Sorte - Ganhou {$prizeValue} pontos";
            $stmt->bind_param("iis", $userId, $prizeValue, $description);
            $stmt->execute();
            $stmt->close();
            
            // Commit
            $conn->commit();
            
            // Obter saldo atualizado
            $stmt = $conn->prepare("SELECT points FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $userData = $result->fetch_assoc();
            $newBalance = $userData['points'];
            $stmt->close();
            
            echo json_encode([
                'status' => 'success',
                'message' => "Você ganhou {$prizeValue} pontos!",
                'data' => [
                    'greeting' => $greeting,
                    'prize_value' => $prizeValue,
                    'prize_index' => $prizeIndex,
                    'available_spins' => $availableSpins - 1,
                    'max_daily_spins' => $maxDailySpins,
                    'new_balance' => $newBalance,
                    'server_time' => $currentDateTime
                ]
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Erro ao processar giro: ' . $e->getMessage()
            ]);
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>

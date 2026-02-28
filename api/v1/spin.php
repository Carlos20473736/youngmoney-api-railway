<?php
/**
 * Spin Wheel API v2 - CORRIGIDO
 * Usa nova tabela user_spins para rastrear giros disponíveis
 * 
 * CORREÇÕES APLICADAS:
 * 1. Campos renomeados: available_spins -> spins_remaining, server_time -> server_timestamp
 * 2. Campo adicionado: spins_today (contagem de giros usados hoje)
 * 3. Tabela corrigida: monetag_impressions -> monetag_events
 * 4. Leitura do body compatível com secure.php (usa _SECURE_REQUEST_BODY se disponível)
 * 5. server_timestamp agora retorna milissegundos (timestamp Unix * 1000)
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
// CORREÇÃO: Ler body de _SECURE_REQUEST_BODY se disponível (chamada via secure.php)
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    if (isset($GLOBALS['_SECURE_REQUEST_BODY']) && !empty($GLOBALS['_SECURE_REQUEST_BODY'])) {
        $requestData = json_decode($GLOBALS['_SECURE_REQUEST_BODY'], true) ?? [];
    } else {
        $requestData = json_decode(file_get_contents('php://input'), true) ?? [];
    }
} else {
    $requestData = $_GET;
}
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
    $stmt->close();
    
    // CORREÇÃO: Contar giros usados HOJE para spins_today
    $stmt = $conn->prepare("
        SELECT COUNT(*) as spins_today 
        FROM user_spins 
        WHERE user_id = ? AND is_used = 1 AND DATE(used_at) = ?
    ");
    $stmt->bind_param("is", $userId, $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $spinsToday = (int)$row['spins_today'];
    $stmt->close();
    
    // Determinar saudação
    $hour = (int)date('H');
    if ($hour >= 5 && $hour < 12) {
        $greeting = 'BOM DIA';
    } elseif ($hour >= 12 && $hour < 18) {
        $greeting = 'BOA TARDE';
    } else {
        $greeting = 'BOA NOITE';
    }
    
    // Gerar server_timestamp em milissegundos (compatível com o frontend)
    $serverTimestamp = round(microtime(true) * 1000);
    
    // GET: Retornar giros disponíveis
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $required_impressions = 10;
        $current_impressions = 0;
        $task_completed = false;
        
        try {
            // Buscar required_impressions do roulette_settings (definido pelo cron diário)
            $settings_stmt = $conn->prepare("
                SELECT setting_value FROM roulette_settings 
                WHERE setting_key = 'monetag_required_impressions'
            ");
            $settings_stmt->execute();
            $settings_result = $settings_stmt->get_result();
            
            if ($settings_result->num_rows > 0) {
                $settings_row = $settings_result->fetch_assoc();
                $required_impressions = (int)$settings_row['setting_value'];
            }
            $settings_stmt->close();
            
            // Tentar buscar de user_required_impressions (override por usuário)
            try {
                $user_settings_stmt = $conn->prepare("
                    SELECT required_impressions FROM user_required_impressions 
                    WHERE user_id = ?
                ");
                $user_settings_stmt->bind_param("i", $userId);
                $user_settings_stmt->execute();
                $user_settings_result = $user_settings_stmt->get_result();
                
                if ($user_settings_result->num_rows > 0) {
                    $user_settings_row = $user_settings_result->fetch_assoc();
                    $required_impressions = (int)$user_settings_row['required_impressions'];
                }
                $user_settings_stmt->close();
            } catch (Exception $e) {
                // Tabela pode não existir, usar valor do roulette_settings
                error_log("[SPIN] user_required_impressions não disponível: " . $e->getMessage());
            }
            
            // CORREÇÃO: Usar tabela monetag_events em vez de monetag_impressions (que não existe)
            try {
                $impressions_stmt = $conn->prepare("
                    SELECT COUNT(*) as total FROM monetag_events 
                    WHERE user_id = ?
                ");
                $impressions_stmt->bind_param("i", $userId);
                $impressions_stmt->execute();
                $impressions_result = $impressions_stmt->get_result();
                $impressions_row = $impressions_result->fetch_assoc();
                $current_impressions = (int)$impressions_row['total'];
                $impressions_stmt->close();
            } catch (Exception $e) {
                // Se monetag_events também não existir, tentar coluna na tabela users
                error_log("[SPIN] monetag_events não disponível: " . $e->getMessage());
                try {
                    $fallback_stmt = $conn->prepare("
                        SELECT monetag_impressions FROM users WHERE id = ?
                    ");
                    $fallback_stmt->bind_param("i", $userId);
                    $fallback_stmt->execute();
                    $fallback_result = $fallback_stmt->get_result();
                    if ($fallback_result->num_rows > 0) {
                        $fallback_row = $fallback_result->fetch_assoc();
                        $current_impressions = (int)($fallback_row['monetag_impressions'] ?? 0);
                    }
                    $fallback_stmt->close();
                } catch (Exception $e2) {
                    error_log("[SPIN] Fallback monetag_impressions também falhou: " . $e2->getMessage());
                    $current_impressions = 0;
                }
            }
            
            $task_completed = ($current_impressions >= $required_impressions);
        } catch (Exception $e) {
            error_log("[SPIN] Erro ao buscar progresso Monetag: " . $e->getMessage());
        }
        
        // CORREÇÃO: Retornar campos com nomes compatíveis com o frontend Android
        echo json_encode([
            'status' => 'success',
            'data' => [
                'greeting' => $greeting,
                'spins_remaining' => $availableSpins,       // CORRIGIDO: era 'available_spins'
                'available_spins' => $availableSpins,        // Manter para retrocompatibilidade
                'spins_today' => $spinsToday,                // ADICIONADO: campo que o frontend espera
                'max_daily_spins' => $maxDailySpins,
                'prize_values' => $prizeValues,
                'server_timestamp' => $serverTimestamp,      // CORRIGIDO: era 'server_time', agora em ms
                'server_time' => $currentDateTime,           // Manter para retrocompatibilidade
                'monetag_task' => [
                    'required_impressions' => $required_impressions,
                    'current_impressions' => $current_impressions,
                    'completed' => $task_completed
                ]
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
                    'spins_remaining' => 0,                  // CORRIGIDO
                    'available_spins' => 0,                   // Retrocompatibilidade
                    'max_daily_spins' => $maxDailySpins,
                    'server_timestamp' => $serverTimestamp,    // CORRIGIDO
                    'server_time' => $currentDateTime          // Retrocompatibilidade
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
            
            // Incrementar spinsToday para a resposta
            $spinsToday++;
            
            // CORREÇÃO: Retornar campos com nomes compatíveis com o frontend Android
            echo json_encode([
                'status' => 'success',
                'message' => "Você ganhou {$prizeValue} pontos!",
                'data' => [
                    'greeting' => $greeting,
                    'prize_value' => $prizeValue,
                    'prize_index' => $prizeIndex,
                    'spins_remaining' => $availableSpins - 1,    // CORRIGIDO: era 'available_spins'
                    'available_spins' => $availableSpins - 1,     // Retrocompatibilidade
                    'spins_today' => $spinsToday,                 // ADICIONADO
                    'max_daily_spins' => $maxDailySpins,
                    'new_balance' => $newBalance,
                    'server_timestamp' => $serverTimestamp,       // CORRIGIDO: era 'server_time', agora em ms
                    'server_time' => $currentDateTime              // Retrocompatibilidade
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

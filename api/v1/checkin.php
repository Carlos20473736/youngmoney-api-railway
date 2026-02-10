<?php
/**
 * API de Check-in Diário
 * 
 * CORRIGIDO: Problema de timezone que bloqueava check-in no dia seguinte
 * 
 * Lógica:
 * - Usa timezone America/Sao_Paulo para todas as comparações de data
 * - Verifica se o último check-in foi feito no dia atual (baseado no timezone correto)
 * - Permite check-in se o último foi em um dia diferente
 */

error_reporting(0);

require_once __DIR__ . "/../../database.php";
require_once __DIR__ . "/../../includes/auth_helper.php";
require_once __DIR__ . '/middleware/MaintenanceCheck.php';
require_once __DIR__ . '/includes/CooldownCheck.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// IMPORTANTE: Configurar timezone para Brasil
date_default_timezone_set('America/Sao_Paulo');

try {
    $conn = getDbConnection();
    
    // ========================================
    // VERIFICAÇÃO DE MANUTENÇÃO E VERSÃO
    // ========================================
    $method = $_SERVER['REQUEST_METHOD'];
    $requestData = ($method === 'POST') 
        ? json_decode(file_get_contents('php://input'), true) ?? []
        : $_GET;
    $userEmail = $requestData['email'] ?? null;
    $appVersion = $requestData['app_version'] ?? $_SERVER['HTTP_X_APP_VERSION'] ?? null;
    checkMaintenanceAndVersion($conn, $userEmail, $appVersion);
    // ========================================
    
    // Configurar timezone no MySQL também
    $conn->query("SET time_zone = '-03:00'");
    
    // Autenticar usuário via token Bearer
    $user = getAuthenticatedUser($conn);
    
    // Se não conseguiu autenticar, tentar pegar user_id da URL (fallback para compatibilidade)
    $userId = null;
    if ($user) {
        $userId = (int)$user['id'];
    } else {
        // Fallback: pegar user_id da URL ou POST body
        if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
            $userId = (int)$_GET['user_id'];
        } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = $requestData;
            if (!$input && !empty($GLOBALS['_SECURE_REQUEST_BODY'])) {
                $input = json_decode($GLOBALS['_SECURE_REQUEST_BODY'], true);
            }
            if (isset($input['user_id']) && !empty($input['user_id'])) {
                $userId = (int)$input['user_id'];
            }
        }
    }
    
    // Se não conseguiu pegar user_id
    if (!$userId) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Não autenticado. Token inválido ou expirado.'
        ]);
        exit;
    }
    
    // Verificar se usuário existe
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userExists = $result->fetch_assoc();
    $stmt->close();
    
    if (!$userExists) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Usuário não encontrado'
        ]);
        exit;
    }
    
    // Buscar último reset datetime
    $result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'last_reset_datetime'");
    $lastResetRow = $result ? $result->fetch_assoc() : null;
    $lastResetDatetime = $lastResetRow ? $lastResetRow['setting_value'] : '1970-01-01 00:00:00';
    
    // Data de HOJE no timezone correto (Brasil)
    $todayDate = date('Y-m-d');
    
    // GET - Verificar status do check-in
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Buscar último check-in do usuário
        $stmt = $conn->prepare("
            SELECT created_at, checkin_date 
            FROM daily_checkin 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $lastCheckin = $result->fetch_assoc();
        $stmt->close();
        
        // Contar total de check-ins (histórico completo)
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM daily_checkin WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $totalRow = $result->fetch_assoc();
        $totalCheckins = (int)$totalRow['total'];
        $stmt->close();
        
        // Verificar se pode fazer check-in
        // CORRIGIDO: Usar checkin_date diretamente (é uma coluna DATE)
        $canCheckin = true;
        $lastCheckinDate = null;
        
        if ($lastCheckin) {
            // Usar a coluna checkin_date que é do tipo DATE
            $lastCheckinDate = $lastCheckin['checkin_date'];
            
            // Se não tiver checkin_date, usar created_at como fallback
            if (!$lastCheckinDate) {
                $lastCheckinDate = date('Y-m-d', strtotime($lastCheckin['created_at']));
            }
            
            // COMPARAÇÃO CORRIGIDA: Verificar se o último check-in foi HOJE
            if ($lastCheckinDate === $todayDate) {
                $canCheckin = false;
            }
        }
        
        // Calcular sequência de dias consecutivos
        $stmt = $conn->prepare("
            SELECT checkin_date 
            FROM daily_checkin 
            WHERE user_id = ? 
            ORDER BY checkin_date DESC 
            LIMIT 30
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $streak = 0;
        $previousDate = null;
        
        while ($row = $result->fetch_assoc()) {
            $checkinDate = $row['checkin_date'];
            
            if ($previousDate === null) {
                // Primeiro registro - verificar se é hoje ou ontem
                $daysDiff = (strtotime($todayDate) - strtotime($checkinDate)) / 86400;
                if ($daysDiff <= 1) {
                    $streak = 1;
                    $previousDate = $checkinDate;
                } else {
                    break; // Sequência quebrada
                }
            } else {
                // Verificar se é o dia anterior
                $expectedPrevious = date('Y-m-d', strtotime($previousDate . ' -1 day'));
                if ($checkinDate === $expectedPrevious) {
                    $streak++;
                    $previousDate = $checkinDate;
                } else {
                    break; // Sequência quebrada
                }
            }
        }
        $stmt->close();
        
        echo json_encode([
            'status' => 'success',
            'can_checkin' => $canCheckin,
            'last_checkin' => $lastCheckinDate,
            'total_checkins' => $totalCheckins,
            'streak' => $streak,
            'last_reset_datetime' => $lastResetDatetime,
            'server_date' => $todayDate,
            'server_time' => date('H:i:s'),
            'timezone' => 'America/Sao_Paulo',
            'message' => $canCheckin ? 'Você pode fazer check-in!' : 'Você já fez check-in hoje!'
        ]);
        exit;
    }
    
    // POST - Fazer check-in
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // VERIFICAÇÃO CORRIGIDA: Usar a coluna checkin_date diretamente
        $stmt = $conn->prepare("
            SELECT id 
            FROM daily_checkin 
            WHERE user_id = ? 
            AND checkin_date = ?
            LIMIT 1
        ");
        $stmt->bind_param("is", $userId, $todayDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $recentCheckin = $result->fetch_assoc();
        $stmt->close();
        
        if ($recentCheckin) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Você já fez check-in hoje! Volte amanhã.',
                'server_date' => $todayDate,
                'server_time' => date('H:i:s')
            ]);
            exit;
        }
        
        // ========================================
        // VERIFICAR COOLDOWN ANTES DE ADICIONAR PONTOS
        // ========================================
        $cooldownCheck = shouldBlockDailyPoints($conn, $userId, 0, 'Check-in - Tentativa durante cooldown');
        
        if (!$cooldownCheck['allowed']) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Voce esta em cooldown de ranking. Nao pode acumular pontos agora.',
                'data' => [
                    'reason' => $cooldownCheck['reason'],
                    'cooldown_info' => $cooldownCheck['cooldown_info'],
                    'can_still_checkin' => false,
                    'note' => 'Voce nao pode fazer check-in durante o cooldown'
                ]
            ]);
            exit;
        }
        
        // Fazer check-in
        $pointsEarned = rand(500, 5000); // Pontos aleatórios entre 500 e 5000
        
        // Inserir registro de check-in
        // CORRIGIDO: Usar a data de hoje explicitamente
        $stmt = $conn->prepare("
            INSERT INTO daily_checkin (user_id, points_reward, checkin_date, created_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                points_reward = VALUES(points_reward),
                created_at = NOW()
        ");
        $stmt->bind_param("iis", $userId, $pointsEarned, $todayDate);
        $stmt->execute();
        $stmt->close();
        
        // Atualizar pontos do usuário (total E daily_points para ranking)
        $stmt = $conn->prepare("UPDATE users SET points = points + ?, daily_points = daily_points + ? WHERE id = ?");
        $stmt->bind_param("iii", $pointsEarned, $pointsEarned, $userId);
        $stmt->execute();
        $stmt->close();
        
        // Registrar no histórico de pontos
        $description = "Check-in Diário - Ganhou {$pointsEarned} pontos";
        $stmt = $conn->prepare("
            INSERT INTO points_history (user_id, points, description, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iss", $userId, $pointsEarned, $description);
        $stmt->execute();
        $stmt->close();
        
        // Contar total de check-ins
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM daily_checkin WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $totalRow = $result->fetch_assoc();
        $totalCheckins = (int)$totalRow['total'];
        $stmt->close();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Check-in realizado com sucesso!',
            'points_earned' => $pointsEarned,
            'total_checkins' => $totalCheckins,
            'checkin_date' => $todayDate,
            'server_time' => date('H:i:s')
        ]);
        exit;
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Método não permitido'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao processar check-in: ' . $e->getMessage()
    ]);
}

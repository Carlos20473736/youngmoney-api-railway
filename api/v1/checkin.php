<?php
error_reporting(0);

require_once __DIR__ . "/../../database.php";
require_once __DIR__ . "/../../includes/auth_helper.php";

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $conn = getDbConnection();
    
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
            $input = json_decode(file_get_contents('php://input'), true);
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
    
    // GET - Verificar status do check-in
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Buscar último check-in do usuário
        $stmt = $conn->prepare("
            SELECT created_at 
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
        // Pode se: não tem check-in HOJE (CURDATE())
        $canCheckin = true;
        $lastCheckinDate = null;
        
        if ($lastCheckin) {
            $lastCheckinDate = date('Y-m-d', strtotime($lastCheckin['created_at']));
            $today = date('Y-m-d');
            
            // Se o último check-in foi HOJE, não pode fazer check-in
            if ($lastCheckinDate === $today) {
                $canCheckin = false;
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'can_checkin' => $canCheckin,
            'last_checkin' => $lastCheckinDate,
            'total_checkins' => $totalCheckins,
            'last_reset_datetime' => $lastResetDatetime,
            'message' => $canCheckin ? 'Você pode fazer check-in!' : 'Você já fez check-in hoje!'
        ]);
        exit;
    }
    
    // POST - Fazer check-in
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verificar se já fez check-in HOJE
        $stmt = $conn->prepare("
            SELECT id 
            FROM daily_checkin 
            WHERE user_id = ? 
            AND DATE(created_at) = CURDATE()
            LIMIT 1
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $recentCheckin = $result->fetch_assoc();
        $stmt->close();
        
        if ($recentCheckin) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Você já fez check-in hoje! Volte amanhã.'
            ]);
            exit;
        }
        
        // Fazer check-in
        $pointsEarned = rand(500, 5000); // Pontos aleatórios entre 500 e 5000
        
        // Inserir registro de check-in
        $stmt = $conn->prepare("
            INSERT INTO daily_checkin (user_id, points_reward, checkin_date, created_at) 
            VALUES (?, ?, CURDATE(), NOW())
            ON DUPLICATE KEY UPDATE 
                points_reward = VALUES(points_reward),
                created_at = NOW()
        ");
        $stmt->bind_param("ii", $userId, $pointsEarned);
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
            'total_checkins' => $totalCheckins
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

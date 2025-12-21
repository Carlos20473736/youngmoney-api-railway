<?php
/**
 * Endpoint da API para Sistema de Convites (v1)
 * Usa auth_helper.php para autenticação consistente com outros endpoints
 */

header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-ID');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Usar os mesmos includes que battery.php
require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../includes/auth_helper.php';

$method = $_SERVER['REQUEST_METHOD'];

error_log("[INVITE] Request started - Method: $method");

// Conectar ao banco de dados
try {
    $conn = getMySQLiConnection();
    error_log("[INVITE] Database connection OK");
} catch (Exception $e) {
    error_log("[INVITE] Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Obter usuário autenticado (mesmo método que battery.php)
$user = getAuthenticatedUser($conn);

if (!$user) {
    error_log("[INVITE] User not authenticated");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized - Invalid or missing token']);
    exit;
}

$userId = $user['id'];
error_log("[INVITE] User ID: $userId");

// Verificar se tabela referrals existe e tem as colunas corretas
$tableCheck = $conn->query("SHOW COLUMNS FROM referrals LIKE 'referred_user_id'");
if (!$tableCheck || $tableCheck->num_rows == 0) {
    // Tabela não existe ou não tem a coluna correta - dropar e recriar
    error_log("[INVITE] Dropping and recreating referrals table");
    $conn->query("DROP TABLE IF EXISTS referrals");
    
    $createTableSQL = "CREATE TABLE referrals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        referrer_user_id INT NOT NULL COMMENT 'ID do usuário que convidou',
        referred_user_id INT NOT NULL COMMENT 'ID do usuário que foi convidado',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data do convite',
        INDEX idx_referrer (referrer_user_id),
        INDEX idx_referred (referred_user_id),
        UNIQUE KEY unique_referred (referred_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($createTableSQL)) {
        error_log("[INVITE] Table creation error: " . $conn->error);
    } else {
        error_log("[INVITE] Referrals table created successfully");
    }
}

// Criar tabela system_settings se não existir
$createSettingsSQL = "CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($createSettingsSQL)) {
    error_log("[INVITE] Settings table creation error: " . $conn->error);
}

// Inserir valores padrão para pontos de convite se não existirem (1000 pontos para cada)
$conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('invite_points_inviter', '1000'), ('invite_points_invited', '1000')");

// Adicionar colunas na tabela users se não existirem
// Verificar se coluna existe antes de adicionar
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'has_used_invite_code'");
if ($result && $result->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN has_used_invite_code TINYINT(1) DEFAULT 0");
}

$result = $conn->query("SHOW COLUMNS FROM users LIKE 'invite_code'");
if ($result && $result->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN invite_code VARCHAR(20) DEFAULT NULL");
}

// Função para buscar pontos de recompensa do banco
function getInvitePoints($conn) {
    $stmt = $conn->prepare("
        SELECT setting_key, setting_value 
        FROM system_settings 
        WHERE setting_key IN ('invite_points_inviter', 'invite_points_invited')
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $points = [
        'inviter' => 1000,  // Padrão: 1000 pontos
        'invited' => 1000   // Padrão: 1000 pontos
    ];
    
    while ($row = $result->fetch_assoc()) {
        if ($row['setting_key'] === 'invite_points_inviter') {
            $points['inviter'] = intval($row['setting_value']);
        } elseif ($row['setting_key'] === 'invite_points_invited') {
            $points['invited'] = intval($row['setting_value']);
        }
    }
    
    $stmt->close();
    return $points;
}

// Função para gerar código de convite único
function generateInviteCode($userId) {
    $timestamp = time();
    $hash = md5($userId . $timestamp);
    $code = '';
    
    for ($i = 0; $i < 6; $i++) {
        $code .= hexdec($hash[$i]) % 10;
    }
    
    return $code;
}

// Buscar pontos de recompensa do banco
$invitePoints = getInvitePoints($conn);
$POINTS_INVITER = $invitePoints['inviter'];
$POINTS_INVITED = $invitePoints['invited'];

switch ($method) {
    case 'GET':
        // GET /api/v1/invite.php - Obter código de convite e estatísticas do usuário autenticado
        
        // Buscar código de convite do usuário e se já usou código
        $stmt = $conn->prepare("SELECT invite_code, has_used_invite_code FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $userData = $result->fetch_assoc();
        $stmt->close();
        
        if (!$userData) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
            exit;
        }
        
        $inviteCode = $userData['invite_code'];
        
        // Se não tem código, gerar um
        if (!$inviteCode) {
            $inviteCode = generateInviteCode($userId);
            $stmt = $conn->prepare("UPDATE users SET invite_code = ? WHERE id = ?");
            $stmt->bind_param("si", $inviteCode, $userId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Contar amigos convidados (da tabela referrals)
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM referrals WHERE referrer_user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        $stmt->close();
        
        // Verificar se já usou código de convite (verificar na tabela referrals também)
        $hasUsedInviteCode = (bool)($userData['has_used_invite_code'] ?? 0);
        if (!$hasUsedInviteCode) {
            // Verificar na tabela referrals se este usuário foi convidado por alguém
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM referrals WHERE referred_user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $referralCheck = $result->fetch_assoc();
            $stmt->close();
            $hasUsedInviteCode = ($referralCheck['count'] > 0);
        }
        
        // Calcular pontos ganhos
        $pointsEarned = $stats['total'] * $POINTS_INVITER;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'invite_code' => $inviteCode,
                'friends_invited' => intval($stats['total']),
                'points_earned' => $pointsEarned,
                'points_per_invite' => $POINTS_INVITER,
                'points_for_friend' => $POINTS_INVITED,
                'has_used_invite_code' => $hasUsedInviteCode
            ]
        ]);
        break;
        
    case 'POST':
        // POST /api/v1/invite.php - Validar e usar código de convite
        
        // Obter body da requisição (mesmo método que battery.php)
        $rawBody = $GLOBALS['_SECURE_REQUEST_BODY'] ?? file_get_contents('php://input');
        $input = json_decode($rawBody, true);
        
        error_log("[INVITE] POST - Raw body: " . substr($rawBody, 0, 200));
        
        if (!isset($input['invite_code']) || empty(trim($input['invite_code']))) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'invite_code é obrigatório']);
            exit;
        }
        
        $inviteCode = trim($input['invite_code']);
        
        error_log("[INVITE] Validating invite code: $inviteCode for user $userId");
        
        // Verificar se já usou um código de convite
        $stmt = $conn->prepare("SELECT id FROM referrals WHERE referred_user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $stmt->close();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Você já usou um código de convite']);
            exit;
        }
        $stmt->close();
        
        // Buscar quem é o dono do código
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE invite_code = ?");
        $stmt->bind_param("s", $inviteCode);
        $stmt->execute();
        $result = $stmt->get_result();
        $inviter = $result->fetch_assoc();
        $stmt->close();
        
        if (!$inviter) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Código de convite inválido']);
            exit;
        }
        
        // Não pode usar o próprio código
        if ($inviter['id'] == $userId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Você não pode usar seu próprio código']);
            exit;
        }
        
        // Iniciar transação
        $conn->begin_transaction();
        
        try {
            // Registrar convite na tabela referrals
            $stmt = $conn->prepare("INSERT INTO referrals (referrer_user_id, referred_user_id, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $inviter['id'], $userId);
            $stmt->execute();
            $stmt->close();
            
            // Dar pontos para quem convidou
            $stmt = $conn->prepare("UPDATE users SET points = points + ?, daily_points = daily_points + ? WHERE id = ?");
            $stmt->bind_param("iii", $POINTS_INVITER, $POINTS_INVITER, $inviter['id']);
            $stmt->execute();
            $stmt->close();
            
            // Dar pontos para quem foi convidado e marcar que já usou código
            $stmt = $conn->prepare("UPDATE users SET points = points + ?, daily_points = daily_points + ?, has_used_invite_code = 1 WHERE id = ?");
            $stmt->bind_param("iii", $POINTS_INVITED, $POINTS_INVITED, $userId);
            $stmt->execute();
            $stmt->close();
            
            // Registrar no histórico de pontos
            $description = "Código de Convite - Ganhou {$POINTS_INVITED} pontos";
            $stmt = $conn->prepare("
                INSERT INTO points_history (user_id, points, description, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->bind_param("iis", $userId, $POINTS_INVITED, $description);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            
            error_log("[INVITE] Success! User $userId used code from user " . $inviter['id']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Código validado com sucesso!',
                'data' => [
                    'points_earned' => $POINTS_INVITED,
                    'inviter_name' => $inviter['name']
                ]
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("[INVITE] Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro ao processar convite: ' . $e->getMessage()]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método não permitido']);
        break;
}

$conn->close();
?>

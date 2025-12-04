<?php
// Endpoint da API para Sistema de Convites (v1)



header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../database.php';
require_once __DIR__ . '/../../includes/HeadersValidator.php';

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDbConnection();

// Validar headers de segurança
$validator = validateRequestHeaders($conn, true);
if (!$validator) exit; // Já enviou resposta de erro


// Função para validar token JWT
function getUserFromToken($conn) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return null;
    }
    
    $token = $matches[1];
    
    // Buscar usuário pelo token
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Tentar obter usuário do token (se houver)
$userFromToken = getUserFromToken($conn);

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
        'inviter' => 500,  // Padrão
        'invited' => 500   // Padrão
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

// Buscar pontos de recompensa do banco
$invitePoints = getInvitePoints($conn);
define('POINTS_INVITER', $invitePoints['inviter']);
define('POINTS_INVITED', $invitePoints['invited']);

switch ($method) {
    case 'GET':
        // GET /api/v1/invite.php?user_id=1 - Obter código de convite e estatísticas
        if (isset($_GET['user_id'])) {
            $userId = intval($_GET['user_id']);
            
            // Buscar código de convite do usuário e se já usou código
            $stmt = $conn->prepare("SELECT invite_code, has_used_invite_code FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if (!$user) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
                exit;
            }
            
            $inviteCode = $user['invite_code'];
            
            // Se não tem código, gerar um
            if (!$inviteCode) {
                $inviteCode = generateInviteCode($userId);
                $stmt = $conn->prepare("UPDATE users SET invite_code = ? WHERE id = ?");
                $stmt->bind_param("si", $inviteCode, $userId);
                $stmt->execute();
                $stmt->close();
            }
            
            // Contar amigos convidados
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE invited_by = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats = $result->fetch_assoc();
            $stmt->close();
            
            // Calcular pontos ganhos
            $pointsEarned = $stats['total'] * POINTS_INVITER;
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'invite_code' => $inviteCode,
                    'friends_invited' => intval($stats['total']),
                    'points_earned' => $pointsEarned,
                    'points_per_invite' => POINTS_INVITER,
                    'points_for_friend' => POINTS_INVITED,
                    'has_used_invite_code' => (bool)($user['has_used_invite_code'] ?? 0)
                ]
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'user_id é obrigatório']);
        }
        break;
        
    case 'POST':
        // POST /api/v1/invite.php - Validar e usar código de convite
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Pegar user_id do body OU do token (verificar ambos)
        $userId = null;
        if (isset($input['user_id']) && !empty($input['user_id'])) {
            $userId = intval($input['user_id']);
        } else if ($userFromToken && isset($userFromToken['id'])) {
            $userId = intval($userFromToken['id']);
        }
        
        if (!$userId || !isset($input['invite_code'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'user_id e invite_code são obrigatórios']);
            exit;
        }
        
        $inviteCode = trim($input['invite_code']);
        
        // Verificar se usuário existe
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
            exit;
        }
        
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
            $stmt->bind_param("iii", $pointsInviter, $pointsInviter, $inviter['id']);
            $pointsInviter = POINTS_INVITER;
            $stmt->execute();
            $stmt->close();
            
            // Dar pontos para quem foi convidado e marcar que já usou código
            $stmt = $conn->prepare("UPDATE users SET points = points + ?, daily_points = daily_points + ?, has_used_invite_code = 1 WHERE id = ?");
            $stmt->bind_param("iii", $pointsInvited, $pointsInvited, $userId);
            $pointsInvited = POINTS_INVITED;
            $stmt->execute();
            $stmt->close();
            
            // Registrar no histórico de pontos
            $description = "Código de Convite - Ganhou {$pointsInvited} pontos";
            $stmt = $conn->prepare("
                INSERT INTO points_history (user_id, points, description, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->bind_param("iis", $userId, $pointsInvited, $description);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Código validado com sucesso!',
                'data' => [
                    'points_earned' => POINTS_INVITED,
                    'inviter_name' => $inviter['name']
                ]
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
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

// Função para gerar código de convite único
function generateInviteCode($userId) {
    // Gerar código de 6 dígitos baseado no user_id + timestamp
    $timestamp = time();
    $hash = md5($userId . $timestamp);
    $code = '';
    
    // Extrair 6 dígitos do hash
    for ($i = 0; $i < 6; $i++) {
        $code .= hexdec($hash[$i]) % 10;
    }
    
    return $code;
}
?>

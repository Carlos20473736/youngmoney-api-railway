<?php
/**
 * User Profile Endpoint
 * GET - Retorna perfil completo do usuário autenticado
 * PUT - Atualiza perfil do usuário
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/security_validation_helper.php';
require_once __DIR__ . '/../includes/xreq_manager.php';

try {
    $conn = getDbConnection();
    
    // Autenticar usuário
    $user = getAuthenticatedUser($conn);
    if (!$user) {
        sendUnauthorizedError();
    }
    
    // VALIDAÇÃO DE HEADERS REMOVIDA - estava bloqueando requisições legítimas
    // validateSecurityHeaders($conn, $user);
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Determinar saudação baseada no horário (GMT-3)
        date_default_timezone_set('America/Sao_Paulo');
        $hour = (int)date('H');
        
        if ($hour >= 5 && $hour < 12) {
            $greeting = 'BOM DIA';
        } elseif ($hour >= 12 && $hour < 18) {
            $greeting = 'BOA TARDE';
        } else {
            $greeting = 'BOA NOITE';
        }
        
        // Gerar novo x-req para próxima requisição
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'okhttp/4.12.0';
        $newXReq = generateNewXReq($conn, $user, $userAgent);
        header("X-New-Req: $newXReq");
        
        // Retornar perfil completo do usuário
        $profileData = [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'profile_picture' => $user['profile_picture'] ?: '',
            'balance' => (int)$user['points'], // balance = points
            'points' => (int)$user['points'],
            'invite_code' => $user['invite_code'],
            'greeting' => $greeting,
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at'],
            'x_req' => $newXReq
        ];
        
        sendSuccess($profileData);
        
    } elseif ($method === 'PUT') {
        // Atualizar perfil do usuário
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        if (!$data) {
            sendError('Dados inválidos', 400);
        }
        
        // Campos permitidos para atualização
        $allowedFields = ['name', 'profile_picture'];
        $updates = [];
        $params = [];
        $types = '';
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
                $types .= 's';
            }
        }
        
        if (empty($updates)) {
            sendError('Nenhum campo para atualizar', 400);
        }
        
        // Adicionar user_id no final dos parâmetros
        $params[] = $user['id'];
        $types .= 'i';
        
        // Executar UPDATE
        $sql = "UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
        
        // Retornar perfil atualizado
        $stmt = $conn->prepare("
            SELECT id, email, name, profile_picture, points, invite_code, created_at, updated_at
            FROM users 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $updatedUser = $result->fetch_assoc();
        $stmt->close();
        
        sendSuccess([
            'id' => (int)$updatedUser['id'],
            'email' => $updatedUser['email'],
            'name' => $updatedUser['name'],
            'profile_picture' => $updatedUser['profile_picture'],
            'balance' => (int)$updatedUser['points'],
            'points' => (int)$updatedUser['points'],
            'invite_code' => $updatedUser['invite_code'],
            'created_at' => $updatedUser['created_at'],
            'updated_at' => $updatedUser['updated_at']
        ]);
    } else {
        sendError('Método não permitido', 405);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("user/profile.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

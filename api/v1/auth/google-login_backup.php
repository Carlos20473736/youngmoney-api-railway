<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['google_token']) || !isset($input['device_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Google token e device_id são obrigatórios']);
        exit;
    }
    
    $googleToken = $input['google_token'];
    $deviceId = $input['device_id'];
    
    // Verificar token do Google (simplificado - em produção, validar com Google API)
    // Por enquanto, vamos extrair o email do token (decodificar JWT)
    $tokenParts = explode('.', $googleToken);
    if (count($tokenParts) !== 3) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token do Google inválido']);
        exit;
    }
    
    $payload = json_decode(base64_decode($tokenParts[1]), true);
    $googleId = isset($payload['sub']) ? $payload['sub'] : null;
    $email = isset($payload['email']) ? $payload['email'] : null;
    $name = isset($payload['name']) ? $payload['name'] : null;
    $profilePicture = isset($payload['picture']) ? $payload['picture'] : null;
    
    if (!$googleId || !$email) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não foi possível extrair dados do token do Google']);
        exit;
    }
    
    $conn = getDbConnection();
    
    // Verificar se usuário já existe (por Google ID ou Device ID)
    $stmt = $conn->prepare("SELECT * FROM users WHERE google_id = ? OR device_id = ?");
    $stmt->bind_param("ss", $googleId, $deviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Usuário existe - atualizar dados
        $user = $result->fetch_assoc();
        $userId = $user['id'];
        
        // Atualizar Google ID, Device ID e foto de perfil se necessário
        $stmt = $conn->prepare("UPDATE users SET google_id = ?, device_id = ?, email = ?, name = ?, profile_picture = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssssi", $googleId, $deviceId, $email, $name, $profilePicture, $userId);
        $stmt->execute();
        
    } else {
        // Criar novo usuário com código de convite aleatório
        $inviteCode = 'YM' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $stmt = $conn->prepare("INSERT INTO users (google_id, device_id, email, name, profile_picture, invite_code, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssss", $googleId, $deviceId, $email, $name, $profilePicture, $inviteCode);
        $stmt->execute();
        $userId = $conn->insert_id;
    }
    
    // Gerar token de autenticação
    $token = bin2hex(random_bytes(32));
    
    // Atualizar token no banco
    $stmt = $conn->prepare("UPDATE users SET token = ? WHERE id = ?");
    $stmt->bind_param("si", $token, $userId);
    $stmt->execute();
    
    // Buscar dados atualizados do usuário
    $stmt = $conn->prepare("SELECT id, email, name, device_id, google_id, profile_picture, points FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'token' => $token,
            'user' => [
                'id' => (int)$user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'device_id' => $user['device_id'],
                'google_id' => $user['google_id'],
                'profile_picture' => $user['profile_picture'],
                'points' => (int)$user['points']
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>

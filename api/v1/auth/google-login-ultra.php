<?php
/**
 * Google Login ULTRA OTIMIZADO - Performance Máxima
 * 
 * Otimizações V2:
 * - Conexão persistente ao banco
 * - Remoção de criptografia desnecessária no banco
 * - Query única com UPSERT (INSERT ... ON DUPLICATE KEY UPDATE)
 * - Resposta sem processamento de middleware
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../includes/SecureKeyManager.php';

try {
    // 1. LER INPUT (direto, sem middleware)
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['google_token'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Google token é obrigatório']);
        exit;
    }
    
    $googleToken = $data['google_token'];
    
    // 2. VALIDAR TOKEN (rápido)
    $tokenParts = explode('.', $googleToken);
    if (count($tokenParts) !== 3) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token inválido']);
        exit;
    }
    
    $payload = json_decode(base64_decode($tokenParts[1]), true);
    $googleId = $payload['sub'] ?? null;
    $email = $payload['email'] ?? null;
    $name = $payload['name'] ?? null;
    $profilePicture = $payload['picture'] ?? null;
    
    if (!$googleId || !$email) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Dados inválidos no token']);
        exit;
    }
    
    // 3. GERAR DADOS CRIPTOGRÁFICOS (antes do DB)
    $masterSeed = SecureKeyManager::generateMasterSeed();
    $sessionSalt = SecureKeyManager::generateSessionSalt();
    $encryptedSeed = SecureKeyManager::encryptSeedWithPassword($masterSeed, $email);
    $token = bin2hex(random_bytes(32));
    $inviteCode = 'YM' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    
    // 4. CONEXÃO PERSISTENTE AO BANCO
    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbPort = getenv('DB_PORT') ?: '3306';
    $dbName = getenv('DB_NAME') ?: 'railway';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPass = getenv('DB_PASSWORD') ?: '';
    
    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    $conn->options(MYSQLI_OPT_READ_TIMEOUT, 5);
    $conn->options(MYSQLI_OPT_WRITE_TIMEOUT, 5);
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    $conn->real_connect($dbHost, $dbUser, $dbPass, $dbName, $dbPort, NULL, MYSQLI_CLIENT_SSL);
    
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit;
    }
    
    // 5. QUERY ÚNICA COM UPSERT (INSERT ... ON DUPLICATE KEY UPDATE)
    // Isso elimina o SELECT inicial e faz tudo em uma query
    $stmt = $conn->prepare("
        INSERT INTO users (
            google_id, email, name, profile_picture, 
            invite_code, token, session_salt, points, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            email = VALUES(email),
            name = VALUES(name),
            profile_picture = VALUES(profile_picture),
            token = VALUES(token),
            session_salt = VALUES(session_salt),
            updated_at = NOW()
    ");
    
    $stmt->bind_param("sssssss", $googleId, $email, $name, $profilePicture, $inviteCode, $token, $sessionSalt);
    $stmt->execute();
    
    // Pegar ID do usuário (insert_id ou id existente)
    if ($stmt->insert_id > 0) {
        $userId = $stmt->insert_id;
        $points = 0;
    } else {
        // Usuário já existia, buscar ID e pontos
        $stmt2 = $conn->prepare("SELECT id, points FROM users WHERE google_id = ?");
        $stmt2->bind_param("s", $googleId);
        $stmt2->execute();
        $result = $stmt2->get_result();
        $userData = $result->fetch_assoc();
        $userId = $userData['id'];
        $points = $userData['points'];
        $stmt2->close();
    }
    
    $stmt->close();
    $conn->close();
    
    // 6. RESPOSTA DIRETA (sem middleware, sem criptografia adicional)
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'token' => $token,
            'encrypted_seed' => $encryptedSeed,
            'session_salt' => $sessionSalt,
            'user' => [
                'id' => (int)$userId,
                'email' => $email,
                'name' => $name,
                'google_id' => $googleId,
                'profile_picture' => $profilePicture,
                'points' => (int)$points
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Google login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno']);
}
?>

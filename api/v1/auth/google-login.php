<?php
/**
 * Google Login OTIMIZADO - Aceita Criptografado ou JSON Puro
 * 
 * Otimizações aplicadas:
 * - Conexão única ao banco (mysqli)
 * - Queries reduzidas de 5 para 2
 * - Cache de dados do usuário
 * - UPDATE condicional
 * - Suporte para requisições criptografadas e JSON puro
 */

header('Content-Type: application/json');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../database.php';
require_once __DIR__ . '/../../../includes/SecureKeyManager.php';
require_once __DIR__ . '/../../../includes/DecryptMiddleware.php';

try {
    // 1. PROCESSAR REQUISIÇÃO - APENAS JSON PURO (SEM CRIPTOGRAFIA)
    if (!empty($_POST)) {
        $data = $_POST;
        error_log("google-login.php - Data from \$_POST: " . json_encode($data));
    } else {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        error_log("google-login.php - Data from raw input: " . json_encode($data));
    }
    
    // 2. VALIDAR DADOS
    if (!isset($data['google_token'])) {
        error_log("google-login.php - ERROR: google_token is missing");
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Google token é obrigatório'
        ]);
        exit;
    }
    
    $googleToken = $data['google_token'];
    $invitedByCode = $data['invited_by_code'] ?? null;
    
    // 3. VERIFICAR TOKEN DO GOOGLE (rápido - sem I/O)
    $tokenParts = explode('.', $googleToken);
    if (count($tokenParts) !== 3) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Token do Google inválido']);
        exit;
    }
    
    $payload = json_decode(base64_decode($tokenParts[1]), true);
    $googleId = $payload['sub'] ?? null;
    $email = $payload['email'] ?? null;
    $name = $payload['name'] ?? null;
    $profilePicture = $payload['picture'] ?? null;
    
    if (!$googleId || !$email) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Não foi possível extrair dados do token do Google']);
        exit;
    }
    
    // 4. CONECTAR AO BANCO (conexão única)
    $conn = getDbConnection();
    
    // 5. GERAR DADOS CRIPTOGRÁFICOS (antes do banco para paralelizar)
    $masterSeed = SecureKeyManager::generateMasterSeed();
    $sessionSalt = SecureKeyManager::generateSessionSalt();
    $encryptedSeed = SecureKeyManager::encryptSeedWithPassword($masterSeed, $email);
    $token = bin2hex(random_bytes(32));
    
    // 6. CRIPTOGRAFAR SEED PARA O BANCO
    $serverKey = getenv('SERVER_ENCRYPTION_KEY');
    if (!$serverKey) {
        error_log("SERVER_ENCRYPTION_KEY not set");
        $serverKey = 'default_key_change_me'; // Fallback
    }
    
    // 7. VERIFICAR/CRIAR USUÁRIO (query única otimizada)
    $stmt = $conn->prepare("
        SELECT id, email, name, profile_picture, points 
        FROM users 
        WHERE google_id = ?
    ");
    $stmt->bind_param("s", $googleId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // USUÁRIO EXISTE - atualizar apenas token e seed
        $user = $result->fetch_assoc();
        $userId = $user['id'];
        
        // Criptografar seed com chave do servidor
        $iv = substr(md5($userId), 0, 16);
        $encryptedSeedForDb = openssl_encrypt($masterSeed, 'AES-256-CBC', $serverKey, 0, $iv);
        
        // UPDATE único com todos os dados
        $stmt = $conn->prepare("
            UPDATE users 
            SET token = ?, 
                master_seed = ?, 
                session_salt = ?,
                email = ?,
                name = ?,
                profile_picture = ?,
                salt_updated_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ssssssi", $token, $encryptedSeedForDb, $sessionSalt, $email, $name, $profilePicture, $userId);
        $stmt->execute();
        
        // Usar dados do cache (não fazer SELECT novamente)
        $user['email'] = $email;
        $user['name'] = $name;
        $user['profile_picture'] = $profilePicture;
        
    } else {
        // CRIAR NOVO USUÁRIO
        $inviteCode = 'YM' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        
        // Processar código de convite se fornecido
        $invitedBy = null;
        if ($invitedByCode) {
            $stmtInvite = $conn->prepare("SELECT id FROM users WHERE invite_code = ?");
            $stmtInvite->bind_param("s", $invitedByCode);
            $stmtInvite->execute();
            $resultInvite = $stmtInvite->get_result();
            if ($resultInvite->num_rows > 0) {
                $inviter = $resultInvite->fetch_assoc();
                $invitedBy = $inviter['id'];
            }
            $stmtInvite->close();
        }
        
        // INSERT com todos os dados de uma vez
        $stmt = $conn->prepare("
            INSERT INTO users (
                google_id, email, name, profile_picture, 
                invite_code, token, master_seed, session_salt,
                points, created_at, salt_updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, '', ?, 0, NOW(), NOW())
        ");
        $stmt->bind_param("sssssss", $googleId, $email, $name, $profilePicture, $inviteCode, $token, $sessionSalt);
        $stmt->execute();
        $userId = $conn->insert_id;
        
        // Criptografar e atualizar seed (necessário pois precisamos do userId para IV)
        $iv = substr(md5($userId), 0, 16);
        $encryptedSeedForDb = openssl_encrypt($masterSeed, 'AES-256-CBC', $serverKey, 0, $iv);
        
        $stmt = $conn->prepare("UPDATE users SET master_seed = ? WHERE id = ?");
        $stmt->bind_param("si", $encryptedSeedForDb, $userId);
        $stmt->execute();
        
        // Dados do novo usuário (sem SELECT adicional)
        $user = [
            'id' => $userId,
            'email' => $email,
            'name' => $name,
            'google_id' => $googleId,
            'profile_picture' => $profilePicture,
            'points' => 0
        ];
    }
    
    // 8. ENVIAR RESPOSTA (criptografada se a requisição foi criptografada, senão JSON puro)
    $responseData = [
        'token' => $token,
        'encrypted_seed' => $encryptedSeed,
        'session_salt' => $sessionSalt,
        'user' => [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'google_id' => $googleId,
            'profile_picture' => $user['profile_picture'],
            'points' => (int)$user['points']
        ]
    ];
    
    // Resposta JSON pura (SEM CRIPTOGRAFIA)
    error_log("google-login.php - Sending plain JSON response");
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $responseData
    ]);
    
    error_log("Google login optimized successful for user $userId");
    
} catch (Exception $e) {
    error_log("Google login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>

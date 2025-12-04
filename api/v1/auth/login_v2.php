<?php
/**
 * Endpoint de Login V2 - Com Segurança Máxima
 * 
 * Retorna encrypted_seed e session_salt para ativar chaves rotativas no app
 */

require_once 'SecureKeyManager.php';
require_once '../includes/DecryptMiddleware.php'; // V1 para compatibilidade

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    DecryptMiddleware::sendError('Método não permitido', 405);
    exit;
}

try {
    // 1. PROCESSAR REQUISIÇÃO (pode vir criptografada com V1)
    $data = DecryptMiddleware::processRequest();
    
    // 2. VALIDAR DADOS
    $googleToken = $data['google_token'] ?? null;
    $deviceId = $data['device_id'] ?? null;
    
    if (!$googleToken && !$deviceId) {
        DecryptMiddleware::sendError('Token do Google ou Device ID é obrigatório');
        exit;
    }
    
    // 3. CONECTAR AO BANCO
    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbName = getenv('DB_NAME') ?: 'youngmoney';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPass = getenv('DB_PASSWORD') ?: '';
    
    try {
        $pdo = new PDO(
            "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
            $dbUser,
            $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        DecryptMiddleware::sendError('Erro de conexão', 500);
        exit;
    }
    
    // 4. AUTENTICAR USUÁRIO
    $userId = null;
    $userEmail = null;
    $userName = null;
    $userPassword = null; // Será o device_id ou derivado do Google token
    
    if ($googleToken) {
        // Validar Google Token (você deve implementar isso)
        // $googleUserInfo = validateGoogleToken($googleToken);
        
        // Simulação - REMOVA ISSO
        $googleUserInfo = [
            'email' => 'user@example.com',
            'name' => 'User Name',
            'picture' => 'https://example.com/photo.jpg'
        ];
        
        $userEmail = $googleUserInfo['email'];
        $userName = $googleUserInfo['name'];
        
        // Buscar ou criar usuário
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$userEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $userId = $user['id'];
        } else {
            // Criar novo usuário
            $stmt = $pdo->prepare("
                INSERT INTO users (email, name, device_id, profile_picture, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userEmail, $userName, $deviceId, $googleUserInfo['picture']]);
            $userId = $pdo->lastInsertId();
        }
        
        // Usar device_id como "senha" para criptografar seed
        $userPassword = $deviceId ?: $userEmail;
        
    } else if ($deviceId) {
        // Login com device_id
        $stmt = $pdo->prepare("SELECT id, email, name FROM users WHERE device_id = ?");
        $stmt->execute([$deviceId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            DecryptMiddleware::sendError('Dispositivo não encontrado', 404);
            exit;
        }
        
        $userId = $user['id'];
        $userEmail = $user['email'];
        $userName = $user['name'];
        $userPassword = $deviceId;
    }
    
    // 5. GERAR MASTER SEED E SESSION SALT
    $masterSeed = SecureKeyManager::generateMasterSeed();
    $sessionSalt = SecureKeyManager::generateSessionSalt();
    
    // 6. CRIPTOGRAFAR SEED COM "SENHA" DO USUÁRIO
    $encryptedSeed = SecureKeyManager::encryptSeedWithPassword($masterSeed, $userPassword);
    
    // 7. ARMAZENAR SEED E SALT NO BANCO (criptografados com chave do servidor)
    $stored = SecureKeyManager::storeUserSecrets($pdo, $userId, $masterSeed, $sessionSalt);
    
    if (!$stored) {
        error_log("Failed to store user secrets for user $userId");
        DecryptMiddleware::sendError('Erro ao armazenar dados de segurança', 500);
        exit;
    }
    
    // 8. GERAR JWT
    $jwt = generateJWT($userId, $userEmail); // Você deve implementar isso
    
    // 9. BUSCAR DADOS COMPLETOS DO USUÁRIO
    $stmt = $pdo->prepare("
        SELECT id, email, name, device_id, points, profile_picture, created_at 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 10. ENVIAR RESPOSTA COM SEED CRIPTOGRAFADO
    DecryptMiddleware::sendSuccess([
        'jwt' => $jwt,
        'encrypted_seed' => $encryptedSeed,  // ⭐ SEED CRIPTOGRAFADO
        'session_salt' => $sessionSalt,      // ⭐ SALT DA SESSÃO
        'user' => [
            'id' => $userData['id'],
            'email' => $userData['email'],
            'name' => $userData['name'],
            'device_id' => $userData['device_id'],
            'points' => intval($userData['points']),
            'profile_picture' => $userData['profile_picture'],
            'created_at' => $userData['created_at']
        ],
        'name' => $userData['name'],
        'photo_url' => $userData['profile_picture'],
        'balance' => intval($userData['points'])
    ], true); // Resposta criptografada com V1
    
    error_log("Login V2 successful for user $userId - seed and salt generated");
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    DecryptMiddleware::sendError('Erro interno do servidor', 500);
}

/**
 * Gera JWT (exemplo simplificado - use uma biblioteca real)
 */
function generateJWT($userId, $userEmail) {
    $secret = getenv('JWT_SECRET') ?: 'your-secret-key';
    
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    
    $payload = base64_encode(json_encode([
        'user_id' => $userId,
        'email' => $userEmail,
        'exp' => time() + (7 * 24 * 60 * 60) // 7 dias
    ]));
    
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
    
    return "$header.$payload.$signature";
}

?>

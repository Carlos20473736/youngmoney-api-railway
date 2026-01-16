<?php
/**
 * Link Login - Sistema de Login por Deep Link
 * 
 * Este endpoint gerencia o fluxo de login por link:
 * 1. Recebe Google Token da página web
 * 2. Valida o token e cria/atualiza usuário
 * 3. Gera um token temporário de sessão
 * 4. Retorna o token para a página web redirecionar via deep link
 * 
 * Endpoints:
 * - POST /api/v1/auth/link-login.php?action=generate - Gera token temporário
 * - POST /api/v1/auth/link-login.php?action=validate - Valida token e retorna dados do usuário
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

// Tempo de expiração do token temporário (5 minutos)
define('LINK_TOKEN_EXPIRY', 300);

try {
    $action = $_GET['action'] ?? 'generate';
    
    // Ler dados da requisição
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'JSON inválido']);
        exit;
    }
    
    $conn = getDbConnection();
    
    // Criar tabela de tokens temporários se não existir
    $conn->query("
        CREATE TABLE IF NOT EXISTS link_login_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(64) NOT NULL UNIQUE,
            user_id INT NOT NULL,
            auth_token VARCHAR(255) NOT NULL,
            session_salt VARCHAR(64) NOT NULL,
            encrypted_seed TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            used_at TIMESTAMP NULL,
            expires_at TIMESTAMP NOT NULL,
            INDEX idx_token (token),
            INDEX idx_expires (expires_at)
        )
    ");
    
    if ($action === 'generate') {
        // ========================================
        // GERAR TOKEN TEMPORÁRIO
        // ========================================
        
        if (!isset($data['google_token'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Google token é obrigatório']);
            exit;
        }
        
        $googleToken = $data['google_token'];
        
        // Verificar token do Google
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
            echo json_encode(['status' => 'error', 'message' => 'Não foi possível extrair dados do token']);
            exit;
        }
        
        // Verificar modo de manutenção
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $ADMIN_EMAILS = ['soltacartatigri@gmail.com', 'muriel25herrera@gmail.com', 'gustavopramos97@gmail.com'];
        $isAdmin = in_array(strtolower($email), array_map('strtolower', $ADMIN_EMAILS));
        
        if ($row && $row['setting_value'] === '1' && !$isAdmin) {
            http_response_code(503);
            echo json_encode([
                'status' => 'error',
                'maintenance' => true,
                'message' => 'Sistema em manutenção'
            ]);
            exit;
        }
        
        // Gerar dados criptográficos
        $masterSeed = SecureKeyManager::generateMasterSeed();
        $sessionSalt = SecureKeyManager::generateSessionSalt();
        $encryptedSeed = SecureKeyManager::encryptSeedWithPassword($masterSeed, $email);
        $authToken = bin2hex(random_bytes(32));
        
        // Verificar/criar usuário
        $stmt = $conn->prepare("SELECT id, email, name, profile_picture, points FROM users WHERE google_id = ?");
        $stmt->bind_param("s", $googleId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $serverKey = getenv('SERVER_ENCRYPTION_KEY') ?: 'default_key_change_me';
        
        if ($result->num_rows > 0) {
            // Usuário existe - atualizar
            $user = $result->fetch_assoc();
            $userId = $user['id'];
            
            $iv = substr(md5($userId), 0, 16);
            $encryptedSeedForDb = openssl_encrypt($masterSeed, 'AES-256-CBC', $serverKey, 0, $iv);
            
            $stmt = $conn->prepare("
                UPDATE users 
                SET token = ?, master_seed = ?, session_salt = ?,
                    email = ?, name = ?, profile_picture = ?,
                    salt_updated_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("ssssssi", $authToken, $encryptedSeedForDb, $sessionSalt, $email, $name, $profilePicture, $userId);
            $stmt->execute();
            
            $user['email'] = $email;
            $user['name'] = $name;
            $user['profile_picture'] = $profilePicture;
        } else {
            // Criar novo usuário
            $inviteCode = 'YM' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
            
            $stmt = $conn->prepare("
                INSERT INTO users (
                    google_id, email, name, profile_picture, 
                    invite_code, token, master_seed, session_salt,
                    points, created_at, salt_updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, '', ?, 0, NOW(), NOW())
            ");
            $stmt->bind_param("sssssss", $googleId, $email, $name, $profilePicture, $inviteCode, $authToken, $sessionSalt);
            $stmt->execute();
            $userId = $conn->insert_id;
            
            $iv = substr(md5($userId), 0, 16);
            $encryptedSeedForDb = openssl_encrypt($masterSeed, 'AES-256-CBC', $serverKey, 0, $iv);
            
            $stmt = $conn->prepare("UPDATE users SET master_seed = ? WHERE id = ?");
            $stmt->bind_param("si", $encryptedSeedForDb, $userId);
            $stmt->execute();
            
            $user = [
                'id' => $userId,
                'email' => $email,
                'name' => $name,
                'profile_picture' => $profilePicture,
                'points' => 0
            ];
        }
        
        // Gerar token temporário para o deep link
        $linkToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + LINK_TOKEN_EXPIRY);
        
        // Salvar token temporário
        $stmt = $conn->prepare("
            INSERT INTO link_login_tokens (token, user_id, auth_token, session_salt, encrypted_seed, expires_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sissss", $linkToken, $userId, $authToken, $sessionSalt, $encryptedSeed, $expiresAt);
        $stmt->execute();
        
        // Limpar tokens expirados
        $conn->query("DELETE FROM link_login_tokens WHERE expires_at < NOW()");
        
        error_log("[LINK-LOGIN] Token gerado para user $userId ($email) - Token: " . substr($linkToken, 0, 16) . "...");
        
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'data' => [
                'link_token' => $linkToken,
                'expires_in' => LINK_TOKEN_EXPIRY,
                'deep_link' => "youngmoney://login?token=" . $linkToken,
                'user' => [
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'profile_picture' => $user['profile_picture']
                ]
            ]
        ]);
        
    } elseif ($action === 'validate') {
        // ========================================
        // VALIDAR TOKEN E RETORNAR DADOS DO USUÁRIO
        // ========================================
        
        if (!isset($data['link_token'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Link token é obrigatório']);
            exit;
        }
        
        $linkToken = $data['link_token'];
        
        // Buscar token
        $stmt = $conn->prepare("
            SELECT lt.*, u.id as user_id, u.email, u.name, u.profile_picture, u.points, u.google_id
            FROM link_login_tokens lt
            JOIN users u ON lt.user_id = u.id
            WHERE lt.token = ? AND lt.expires_at > NOW() AND lt.used_at IS NULL
        ");
        $stmt->bind_param("s", $linkToken);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            error_log("[LINK-LOGIN] Token inválido ou expirado: " . substr($linkToken, 0, 16) . "...");
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'Token inválido ou expirado',
                'code' => 'INVALID_TOKEN'
            ]);
            exit;
        }
        
        $tokenData = $result->fetch_assoc();
        
        // Marcar token como usado
        $stmt = $conn->prepare("UPDATE link_login_tokens SET used_at = NOW() WHERE token = ?");
        $stmt->bind_param("s", $linkToken);
        $stmt->execute();
        
        error_log("[LINK-LOGIN] Token validado para user " . $tokenData['user_id'] . " (" . $tokenData['email'] . ")");
        
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'data' => [
                'token' => $tokenData['auth_token'],
                'session_salt' => $tokenData['session_salt'],
                'encrypted_seed' => $tokenData['encrypted_seed'],
                'user' => [
                    'id' => (int)$tokenData['user_id'],
                    'email' => $tokenData['email'],
                    'name' => $tokenData['name'],
                    'google_id' => $tokenData['google_id'],
                    'profile_picture' => $tokenData['profile_picture'],
                    'points' => (int)$tokenData['points']
                ]
            ]
        ]);
        
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Ação inválida']);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("[LINK-LOGIN] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>

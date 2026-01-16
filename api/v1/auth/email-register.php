<?php
/**
 * Email Register - Registro de Usuário por Email e Senha
 * 
 * Endpoint para criar conta usando email e senha
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
    // 1. PROCESSAR REQUISIÇÃO
    $data = null;
    
    if (!empty($GLOBALS['_SECURE_REQUEST_BODY'])) {
        $data = json_decode($GLOBALS['_SECURE_REQUEST_BODY'], true);
    } elseif (!empty($_POST)) {
        $data = $_POST;
    } else {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
    }
    
    error_log("email-register.php - Data received: " . json_encode($data));
    
    // 2. VALIDAR DADOS
    if (!isset($data['email']) || !isset($data['password']) || !isset($data['name'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Email, senha e nome são obrigatórios'
        ]);
        exit;
    }
    
    $email = trim(strtolower($data['email']));
    $password = $data['password'];
    $name = trim($data['name']);
    $invitedByCode = $data['invited_by_code'] ?? null;
    
    // Validar formato do email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Formato de email inválido'
        ]);
        exit;
    }
    
    // Validar tamanho da senha
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'A senha deve ter pelo menos 6 caracteres'
        ]);
        exit;
    }
    
    // Validar nome
    if (strlen($name) < 2) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'O nome deve ter pelo menos 2 caracteres'
        ]);
        exit;
    }
    
    // 3. CONECTAR AO BANCO
    $conn = getDbConnection();
    
    // 4. VERIFICAR SE EMAIL JÁ EXISTE
    $stmt = $conn->prepare("SELECT id, google_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $existingUser = $result->fetch_assoc();
        $stmt->close();
        
        if (!empty($existingUser['google_id'])) {
            // Usuário existe com Google, permitir adicionar senha
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'message' => 'Este email já está cadastrado com login Google. Use o login com Google.',
                'code' => 'EMAIL_EXISTS_GOOGLE'
            ]);
        } else {
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'message' => 'Este email já está cadastrado',
                'code' => 'EMAIL_EXISTS'
            ]);
        }
        $conn->close();
        exit;
    }
    $stmt->close();
    
    // 5. GERAR DADOS CRIPTOGRÁFICOS
    $masterSeed = SecureKeyManager::generateMasterSeed();
    $sessionSalt = SecureKeyManager::generateSessionSalt();
    $encryptedSeed = SecureKeyManager::encryptSeedWithPassword($masterSeed, $email);
    $token = bin2hex(random_bytes(32));
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $inviteCode = 'YM' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    
    // 6. PROCESSAR CÓDIGO DE CONVITE
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
    
    // 7. CRIAR USUÁRIO
    $stmt = $conn->prepare("
        INSERT INTO users (
            email, password_hash, name, 
            invite_code, token, session_salt,
            points, created_at, salt_updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
    ");
    $stmt->bind_param("ssssss", $email, $passwordHash, $name, $inviteCode, $token, $sessionSalt);
    $stmt->execute();
    $userId = $conn->insert_id;
    $stmt->close();
    
    // 8. CRIPTOGRAFAR E SALVAR SEED
    $serverKey = getenv('SERVER_ENCRYPTION_KEY');
    if (!$serverKey) {
        $serverKey = 'default_key_change_me';
    }
    
    $iv = substr(md5($userId), 0, 16);
    $encryptedSeedForDb = openssl_encrypt($masterSeed, 'AES-256-CBC', $serverKey, 0, $iv);
    
    $stmt = $conn->prepare("UPDATE users SET master_seed = ? WHERE id = ?");
    $stmt->bind_param("si", $encryptedSeedForDb, $userId);
    $stmt->execute();
    $stmt->close();
    
    // 9. REGISTRAR CONVITE (se houver)
    if ($invitedBy) {
        $stmt = $conn->prepare("UPDATE users SET invited_by = ? WHERE id = ?");
        $stmt->bind_param("ii", $invitedBy, $userId);
        $stmt->execute();
        $stmt->close();
    }
    
    // 10. ENVIAR RESPOSTA
    $responseData = [
        'token' => $token,
        'encrypted_seed' => $encryptedSeed,
        'session_salt' => $sessionSalt,
        'user' => [
            'id' => (int)$userId,
            'email' => $email,
            'name' => $name,
            'google_id' => null,
            'profile_picture' => null,
            'points' => 0
        ]
    ];
    
    http_response_code(201);
    echo json_encode([
        'status' => 'success',
        'message' => 'Conta criada com sucesso',
        'data' => $responseData
    ]);
    
    error_log("Email register successful for user $userId ($email)");
    $conn->close();
    
} catch (Exception $e) {
    error_log("Email register error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>

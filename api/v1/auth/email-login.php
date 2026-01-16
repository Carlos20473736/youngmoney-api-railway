<?php
/**
 * Email Login - Autenticação por Email e Senha
 * 
 * Endpoint para login usando email e senha
 * Suporta requisições criptografadas e JSON puro
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

// Lista de emails de administradores que podem logar durante manutenção
$ADMIN_EMAILS = [
    'soltacartatigri@gmail.com',
    'muriel25herrera@gmail.com',
    'gustavopramos97@gmail.com'
];

try {
    // 1. PROCESSAR REQUISIÇÃO - ACEITA TÚNEL SEGURO OU JSON PURO
    $data = null;
    
    // Primeiro, verificar se veio do túnel seguro
    if (!empty($GLOBALS['_SECURE_REQUEST_BODY'])) {
        $data = json_decode($GLOBALS['_SECURE_REQUEST_BODY'], true);
        error_log("email-login.php - Data from SECURE TUNNEL: " . json_encode($data));
    } elseif (!empty($_POST)) {
        $data = $_POST;
        error_log("email-login.php - Data from \$_POST: " . json_encode($data));
    } else {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        error_log("email-login.php - Data from raw input: " . json_encode($data));
    }
    
    // 2. VALIDAR DADOS
    if (!isset($data['email']) || !isset($data['password'])) {
        error_log("email-login.php - ERROR: email or password is missing");
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Email e senha são obrigatórios'
        ]);
        exit;
    }
    
    $email = trim(strtolower($data['email']));
    $password = $data['password'];
    
    // Validar formato do email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Formato de email inválido'
        ]);
        exit;
    }
    
    // 3. CONECTAR AO BANCO
    $conn = getDbConnection();

    // ========================================
    // VERIFICAR MODO DE MANUTENÇÃO
    // ========================================
    $isAdmin = in_array(strtolower($email), array_map('strtolower', $ADMIN_EMAILS));
    
    // Verificar status do modo de manutenção
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    $isMaintenanceActive = ($row && $row['setting_value'] === '1');
    
    if ($isMaintenanceActive && !$isAdmin) {
        // Buscar mensagem de manutenção
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_message'");
        $stmt->execute();
        $result = $stmt->get_result();
        $msgRow = $result->fetch_assoc();
        $stmt->close();
        
        $message = $msgRow ? $msgRow['setting_value'] : 'Servidor em manutenção. Tente novamente mais tarde.';
        
        error_log("[EMAIL-LOGIN] Requisição BLOQUEADA - Modo de manutenção ativo para: $email");
        
        http_response_code(503);
        echo json_encode([
            'status' => 'error',
            'maintenance' => true,
            'maintenance_mode' => true,
            'message' => $message,
            'code' => 'MAINTENANCE_MODE'
        ]);
        $conn->close();
        exit;
    }
    
    if ($isMaintenanceActive && $isAdmin) {
        error_log("[EMAIL-LOGIN] Admin autorizado durante manutenção: $email");
    }
    // ========================================
    
    // 4. BUSCAR USUÁRIO PELO EMAIL
    $stmt = $conn->prepare("
        SELECT id, email, name, profile_picture, points, password_hash, google_id
        FROM users 
        WHERE email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("[EMAIL-LOGIN] Usuário não encontrado: $email");
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Email ou senha incorretos'
        ]);
        $conn->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // 5. VERIFICAR SE O USUÁRIO TEM SENHA DEFINIDA
    if (empty($user['password_hash'])) {
        // Usuário só tem login Google, não tem senha definida
        error_log("[EMAIL-LOGIN] Usuário sem senha definida (apenas Google): $email");
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Esta conta usa login com Google. Por favor, faça login com o Google.',
            'code' => 'GOOGLE_ONLY_ACCOUNT'
        ]);
        $conn->close();
        exit;
    }
    
    // 6. VERIFICAR SENHA
    if (!password_verify($password, $user['password_hash'])) {
        error_log("[EMAIL-LOGIN] Senha incorreta para: $email");
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Email ou senha incorretos'
        ]);
        $conn->close();
        exit;
    }
    
    // 7. GERAR DADOS CRIPTOGRÁFICOS
    $masterSeed = SecureKeyManager::generateMasterSeed();
    $sessionSalt = SecureKeyManager::generateSessionSalt();
    $encryptedSeed = SecureKeyManager::encryptSeedWithPassword($masterSeed, $email);
    $token = bin2hex(random_bytes(32));
    
    // 8. CRIPTOGRAFAR SEED PARA O BANCO
    $serverKey = getenv('SERVER_ENCRYPTION_KEY');
    if (!$serverKey) {
        error_log("SERVER_ENCRYPTION_KEY not set");
        $serverKey = 'default_key_change_me'; // Fallback
    }
    
    $userId = $user['id'];
    $iv = substr(md5($userId), 0, 16);
    $encryptedSeedForDb = openssl_encrypt($masterSeed, 'AES-256-CBC', $serverKey, 0, $iv);
    
    // 9. ATUALIZAR TOKEN E SEED DO USUÁRIO
    $stmt = $conn->prepare("
        UPDATE users 
        SET token = ?, 
            master_seed = ?, 
            session_salt = ?,
            salt_updated_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("sssi", $token, $encryptedSeedForDb, $sessionSalt, $userId);
    $stmt->execute();
    $stmt->close();
    
    // 10. ENVIAR RESPOSTA
    $responseData = [
        'token' => $token,
        'encrypted_seed' => $encryptedSeed,
        'session_salt' => $sessionSalt,
        'user' => [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'google_id' => $user['google_id'] ?? null,
            'profile_picture' => $user['profile_picture'],
            'points' => (int)$user['points']
        ]
    ];
    
    error_log("email-login.php - Sending plain JSON response");
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $responseData
    ]);
    
    error_log("Email login successful for user $userId ($email)");
    $conn->close();
    
} catch (Exception $e) {
    error_log("Email login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>

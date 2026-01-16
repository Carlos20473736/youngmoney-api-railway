<?php
/**
 * Email Login - Autenticação por Email e Senha
 * 
 * Endpoint para login usando email e senha
 * Se o usuário não existir, cria automaticamente uma nova conta
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
    $name = isset($data['name']) ? trim($data['name']) : null;
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
    
    // 5. GERAR DADOS CRIPTOGRÁFICOS (antes do banco para otimizar)
    $masterSeed = SecureKeyManager::generateMasterSeed();
    $sessionSalt = SecureKeyManager::generateSessionSalt();
    $encryptedSeed = SecureKeyManager::encryptSeedWithPassword($masterSeed, $email);
    $token = bin2hex(random_bytes(32));
    
    // 6. OBTER CHAVE DO SERVIDOR
    $serverKey = getenv('SERVER_ENCRYPTION_KEY');
    if (!$serverKey) {
        error_log("SERVER_ENCRYPTION_KEY not set");
        $serverKey = 'default_key_change_me'; // Fallback
    }
    
    if ($result->num_rows === 0) {
        // ========================================
        // USUÁRIO NÃO EXISTE - CRIAR NOVA CONTA
        // ========================================
        error_log("[EMAIL-LOGIN] Usuário não encontrado, criando nova conta: $email");
        
        // Validar tamanho da senha para novo usuário
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'A senha deve ter pelo menos 6 caracteres'
            ]);
            $conn->close();
            exit;
        }
        
        // Se não foi fornecido nome, usar parte do email antes do @
        if (empty($name)) {
            $name = ucfirst(explode('@', $email)[0]);
        }
        
        // Validar nome
        if (strlen($name) < 2) {
            $name = ucfirst(explode('@', $email)[0]);
        }
        
        // Gerar hash da senha e código de convite
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
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
        
        // Criar usuário
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
        
        // Criptografar e salvar seed
        $iv = substr(md5($userId), 0, 16);
        $encryptedSeedForDb = openssl_encrypt($masterSeed, 'AES-256-CBC', $serverKey, 0, $iv);
        
        $stmt = $conn->prepare("UPDATE users SET master_seed = ? WHERE id = ?");
        $stmt->bind_param("si", $encryptedSeedForDb, $userId);
        $stmt->execute();
        $stmt->close();
        
        // Registrar convite (se houver)
        if ($invitedBy) {
            $stmt = $conn->prepare("UPDATE users SET invited_by = ? WHERE id = ?");
            $stmt->bind_param("ii", $invitedBy, $userId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Dados do novo usuário
        $user = [
            'id' => $userId,
            'email' => $email,
            'name' => $name,
            'google_id' => null,
            'profile_picture' => null,
            'points' => 0
        ];
        
        error_log("[EMAIL-LOGIN] Nova conta criada com sucesso: $email (ID: $userId)");
        
    } else {
        // ========================================
        // USUÁRIO EXISTE - FAZER LOGIN
        // ========================================
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Verificar se o usuário tem senha definida
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
        
        // Verificar senha
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
        
        $userId = $user['id'];
        
        // Criptografar seed para o banco
        $iv = substr(md5($userId), 0, 16);
        $encryptedSeedForDb = openssl_encrypt($masterSeed, 'AES-256-CBC', $serverKey, 0, $iv);
        
        // Atualizar token e seed do usuário
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
        
        error_log("[EMAIL-LOGIN] Login bem-sucedido para: $email (ID: $userId)");
    }
    
    // 7. ENVIAR RESPOSTA
    $responseData = [
        'token' => $token,
        'encrypted_seed' => $encryptedSeed,
        'session_salt' => $sessionSalt,
        'user' => [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'google_id' => $user['google_id'] ?? null,
            'profile_picture' => $user['profile_picture'] ?? null,
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

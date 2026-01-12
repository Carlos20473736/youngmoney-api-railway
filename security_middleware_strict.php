<?php
/**
 * Security Middleware STRICT - Validação OBRIGATÓRIA de headers anti-script
 * 
 * Este middleware bloqueia TODAS as requisições que não tenham os headers de segurança.
 * Requisições sem os headers serão rejeitadas com erro 403.
 * 
 * INCLUI: Verificação de Modo de Manutenção (PRIMEIRA COISA!)
 * 
 * Headers obrigatórios:
 * - X-Device-Fingerprint
 * - X-Timestamp
 * - X-Nonce
 * - X-Request-Signature
 * - X-App-Hash
 * 
 * @version 2.1.0
 */

// =====================================================
// VERIFICAÇÃO DE MODO DE MANUTENÇÃO - PRIMEIRA COISA!
// =====================================================

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Se for OPTIONS (preflight), permitir SEMPRE
if ($requestMethod === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Lista de emails de administradores (GLOBAL para uso em todo o middleware)
$ADMIN_EMAILS = [
    'soltacartatigri@gmail.com',
    'muriel25herrera@gmail.com',
    'gustavopramos97@gmail.com'
];

// Endpoints isentos de verificação de manutenção (admins podem acessar mesmo sem token)
$maintenanceExemptEndpoints = [
    '/admin/maintenance.php',
    '/api/v1/device/check.php',
    '/api/v1/device/bind.php',
    '/api/v1/device/register.php',
    '/api/v1/auth/google-login.php',
    '/api/v1/auth/device-login.php',
    '/api/v1/tunnel.php',
    '/api/v1/secure.php',
    '/api/v1/app/check-update.php',  // Verificação de atualização do app
    '/api/v1/app/version.php'         // Versão do app
];

// Verificar se é endpoint isento de manutenção
$isMaintenanceExempt = false;
foreach ($maintenanceExemptEndpoints as $exemptEndpoint) {
    if (strpos($requestUri, $exemptEndpoint) !== false) {
        $isMaintenanceExempt = true;
        break;
    }
}

// Se NÃO for endpoint isento, verificar modo de manutenção
if (!$isMaintenanceExempt) {
    
    try {
        // Carregar configuração do banco
        require_once __DIR__ . '/database.php';
        $conn = getDbConnection();
        
        // Verificar status do modo de manutenção
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $isMaintenanceActive = ($row && $row['setting_value'] === '1');
        
        if ($isMaintenanceActive) {
            // Buscar mensagem de manutenção
            $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_message'");
            $stmt->execute();
            $result = $stmt->get_result();
            $msgRow = $result->fetch_assoc();
            $stmt->close();
            
            $message = $msgRow ? $msgRow['setting_value'] : 'Servidor em manutenção. Tente novamente mais tarde.';
            
            // Verificar se é um admin tentando acessar
            $isAdmin = false;
            
            // MÉTODO 1: Verificar pelo token de autenticação (Bearer)
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
                
                // Buscar usuário pelo token
                $stmt = $conn->prepare("SELECT email FROM users WHERE token = ?");
                $stmt->bind_param("s", $token);
                $stmt->execute();
                $result = $stmt->get_result();
                $userRow = $result->fetch_assoc();
                $stmt->close();
                
                if ($userRow) {
                    $userEmail = strtolower($userRow['email']);
                    $isAdmin = in_array($userEmail, array_map('strtolower', $ADMIN_EMAILS));
                    if ($isAdmin) {
                        error_log("[MAINTENANCE] Admin identificado por token: $userEmail");
                    }
                }
            }
            
            // MÉTODO 2: Verificar pelo email no body da requisição (para endpoints de login/device)
            if (!$isAdmin) {
                $rawBody = file_get_contents('php://input');
                $bodyData = json_decode($rawBody, true);
                
                if ($bodyData && isset($bodyData['email'])) {
                    $bodyEmail = strtolower(trim($bodyData['email']));
                    $isAdmin = in_array($bodyEmail, array_map('strtolower', $ADMIN_EMAILS));
                    if ($isAdmin) {
                        error_log("[MAINTENANCE] Admin identificado por email no body: $bodyEmail");
                    }
                }
            }
            
            // MÉTODO 3: Verificar pelo google_token (decodificar JWT para extrair email)
            if (!$isAdmin) {
                $rawBody = $rawBody ?? file_get_contents('php://input');
                $bodyData = $bodyData ?? json_decode($rawBody, true);
                
                if ($bodyData && isset($bodyData['google_token'])) {
                    $googleToken = $bodyData['google_token'];
                    $tokenParts = explode('.', $googleToken);
                    if (count($tokenParts) === 3) {
                        $payload = json_decode(base64_decode($tokenParts[1]), true);
                        if ($payload && isset($payload['email'])) {
                            $googleEmail = strtolower($payload['email']);
                            $isAdmin = in_array($googleEmail, array_map('strtolower', $ADMIN_EMAILS));
                            if ($isAdmin) {
                                error_log("[MAINTENANCE] Admin identificado por google_token: $googleEmail");
                            }
                        }
                    }
                }
            }
            
            // MÉTODO 4: Verificar pelo device_id (buscar usuário vinculado ao dispositivo)
            if (!$isAdmin) {
                $rawBody = $rawBody ?? file_get_contents('php://input');
                $bodyData = $bodyData ?? json_decode($rawBody, true);
                
                if ($bodyData && isset($bodyData['device_id'])) {
                    $deviceId = $bodyData['device_id'];
                    
                    // Buscar usuário vinculado ao dispositivo
                    $stmt = $conn->prepare("
                        SELECT u.email 
                        FROM device_bindings db 
                        JOIN users u ON db.user_id = u.id 
                        WHERE db.device_id = ? AND db.is_active = 1
                    ");
                    $stmt->bind_param("s", $deviceId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $deviceUser = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($deviceUser) {
                        $deviceEmail = strtolower($deviceUser['email']);
                        $isAdmin = in_array($deviceEmail, array_map('strtolower', $ADMIN_EMAILS));
                        if ($isAdmin) {
                            error_log("[MAINTENANCE] Admin identificado por device_id: $deviceEmail");
                        }
                    }
                }
            }
            
            $conn->close();
            
            // Se NÃO for admin, BLOQUEAR
            if (!$isAdmin) {
                error_log("[MAINTENANCE] Requisição BLOQUEADA - Endpoint: $requestUri - Method: $requestMethod");
                
                header('Content-Type: application/json');
                header('Access-Control-Allow-Origin: *');
                http_response_code(503); // Service Unavailable
                
                echo json_encode([
                    'status' => 'error',
                    'maintenance' => true,
                    'maintenance_mode' => true,
                    'message' => $message,
                    'code' => 'MAINTENANCE_MODE'
                ]);
                exit;
            } else {
                error_log("[MAINTENANCE] Admin autorizado durante manutenção - Endpoint: $requestUri");
            }
        } else {
            $conn->close();
        }
        
    } catch (Exception $e) {
        error_log("[MAINTENANCE] Erro ao verificar modo de manutenção: " . $e->getMessage());
        // Em caso de erro, NÃO bloquear (fail-open para não derrubar o serviço)
    }
}

// =====================================================
// VALIDAÇÃO DE HEADERS DE SEGURANÇA (código original)
// =====================================================

// Endpoints públicos que NÃO precisam de validação de headers
$publicEndpoints = [
    '/',
    '/index.php',
    '/api/v1/auth/google-login.php',
    '/api/v1/auth/device-login.php',
    '/api/v1/auth/login_v2.php',
    '/api/v1/debug/headers.php',
    '/api/v1/debug/check-token.php',
    '/api/v1/device/check.php',
    '/api/v1/device/bind.php',
    '/api/v1/device/register.php',
    '/api/v1/tunnel.php',
    '/api/v1/secure.php',
    '/admin/',
    '/health',
    '/health.php'
];

// Verificar se é endpoint público
$isPublic = false;
foreach ($publicEndpoints as $endpoint) {
    if ($requestUri === $endpoint || strpos($requestUri, $endpoint) === 0) {
        $isPublic = true;
        break;
    }
}

// Se não for público, validar headers
if (!$isPublic) {
    
    // Carregar headers
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    } else {
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $header = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$header] = $value;
            }
        }
    }
    
    // Headers obrigatórios
    $requiredHeaders = [
        'x-device-fingerprint',
        'x-timestamp',
        'x-nonce',
        'x-request-signature',
        'x-app-hash'
    ];
    
    $missingHeaders = [];
    foreach ($requiredHeaders as $header) {
        if (empty($headers[$header])) {
            $missingHeaders[] = $header;
        }
    }
    
    // Se faltam headers, bloquear
    if (!empty($missingHeaders)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Security headers required',
            'code' => 'SECURITY_HEADERS_MISSING',
            'missing_headers' => $missingHeaders
        ]);
        exit;
    }
    
    // Validar timestamp (janela de 2 minutos = 120 segundos)
    $timestamp = (int) $headers['x-timestamp'];
    $currentTime = round(microtime(true) * 1000); // milissegundos
    $diff = abs($currentTime - $timestamp);
    
    if ($diff > 120000) { // 2 minutos em milissegundos
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Request timestamp expired',
            'code' => 'TIMESTAMP_EXPIRED',
            'diff_ms' => $diff
        ]);
        exit;
    }
    
    // Validar fingerprint (mínimo 32 caracteres)
    $fingerprint = $headers['x-device-fingerprint'];
    if (strlen($fingerprint) < 32) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid device fingerprint',
            'code' => 'INVALID_FINGERPRINT'
        ]);
        exit;
    }
    
    // Validar signature (deve ter 64 caracteres - SHA256)
    $signature = $headers['x-request-signature'];
    if (strlen($signature) !== 64) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid request signature',
            'code' => 'INVALID_SIGNATURE'
        ]);
        exit;
    }
    
    // Validar nonce (não vazio)
    $nonce = $headers['x-nonce'];
    if (strlen($nonce) < 16) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid nonce',
            'code' => 'INVALID_NONCE'
        ]);
        exit;
    }
    
    // Validar app hash (mínimo 32 caracteres)
    $appHash = $headers['x-app-hash'];
    if (strlen($appHash) < 32) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid app hash',
            'code' => 'INVALID_APP_HASH'
        ]);
        exit;
    }
    
    // Log de sucesso (opcional)
    error_log("[SecurityMiddleware] Request validated - Fingerprint: " . substr($fingerprint, 0, 16) . "...");
}
?>

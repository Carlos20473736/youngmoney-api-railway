<?php
/**
 * Auth Helper - Funções auxiliares para autenticação
 * COM VALIDAÇÃO OBRIGATÓRIA DE HEADERS ANTI-SCRIPT
 */

require_once __DIR__ . '/AntiScriptValidator.php';
require_once __DIR__ . '/RotatingKeyValidator.php';

// ========================================
// VALIDAÇÃO OBRIGATÓRIA DE HEADERS ANTI-SCRIPT
// ========================================

// Endpoints públicos que NÃO precisam de validação
$_PUBLIC_ENDPOINTS = [
    '/api/v1/auth/google-login.php',
    '/api/v1/auth/device-login.php',
    '/api/v1/auth/login_v2.php',
    '/api/v1/debug/',
    '/health'
];

// Verificar se é endpoint público
$_currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$_isPublicEndpoint = false;

foreach ($_PUBLIC_ENDPOINTS as $_endpoint) {
    if (strpos($_currentUri, $_endpoint) !== false) {
        $_isPublicEndpoint = true;
        break;
    }
}

// Se for OPTIONS (preflight CORS), permitir
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $_isPublicEndpoint = true;
}

// Se NÃO for endpoint público, validar headers OBRIGATORIAMENTE
if (!$_isPublicEndpoint) {
    // Carregar headers
    $_headers = [];
    if (function_exists('getallheaders')) {
        $_headers = array_change_key_case(getallheaders(), CASE_LOWER);
    } else {
        foreach ($_SERVER as $_key => $_value) {
            if (substr($_key, 0, 5) === 'HTTP_') {
                $_header = strtolower(str_replace('_', '-', substr($_key, 5)));
                $_headers[$_header] = $_value;
            }
        }
    }
    
    // Headers obrigatórios
    $_requiredHeaders = [
        'x-device-fingerprint',
        'x-timestamp',
        'x-nonce',
        'x-request-signature',
        'x-app-hash'
    ];
    
    $_missingHeaders = [];
    foreach ($_requiredHeaders as $_header) {
        if (empty($_headers[$_header])) {
            $_missingHeaders[] = $_header;
        }
    }
    
    // Se faltam headers, BLOQUEAR
    if (!empty($_missingHeaders)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Security headers required',
            'code' => 'SECURITY_HEADERS_MISSING',
            'missing_headers' => $_missingHeaders
        ]);
        exit;
    }
    
    // Validar timestamp (janela de 2 minutos = 120 segundos)
    $_timestamp = (int) $_headers['x-timestamp'];
    $_currentTime = round(microtime(true) * 1000);
    $_diff = abs($_currentTime - $_timestamp);
    
    if ($_diff > 120000) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Request timestamp expired',
            'code' => 'TIMESTAMP_EXPIRED'
        ]);
        exit;
    }
    
    // Validar fingerprint (mínimo 32 caracteres)
    if (strlen($_headers['x-device-fingerprint']) < 32) {
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
    if (strlen($_headers['x-request-signature']) !== 64) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid request signature',
            'code' => 'INVALID_SIGNATURE'
        ]);
        exit;
    }
    
    error_log("[AntiScript] Request validated - Fingerprint: " . substr($_headers['x-device-fingerprint'], 0, 16) . "...");
    
    // ======= VALIDAÇÃO DE CHAVE ROTATIVA NATIVA =======
    // Verifica chave que muda a cada 5 segundos (gerada em C++)
    if (RotatingKeyValidator::hasRotatingKeyHeaders($_headers)) {
        $_rotatingValidation = RotatingKeyValidator::validate(
            $_headers['x-rotating-key'] ?? '',
            $_headers['x-native-signature'] ?? '',
            (int)($_headers['x-key-window'] ?? 0),
            $_currentUri,
            file_get_contents('php://input') ?: '',
            (int)($_headers['x-timestamp'] ?? 0),
            $_headers['x-nonce'] ?? ''
        );
        
        if (!$_rotatingValidation['valid']) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Native security validation failed',
                'code' => $_rotatingValidation['error']
            ]);
            exit;
        }
        
        error_log("[RotatingKey] Chave rotativa validada com sucesso");
    } else {
        // Se não tem headers de chave rotativa, permitir por enquanto
        // (para compatibilidade com versões antigas do app)
        error_log("[RotatingKey] Headers de chave rotativa não encontrados - permitindo por compatibilidade");
    }
}

// ========================================
// FIM DA VALIDAÇÃO ANTI-SCRIPT
// ========================================

/**
 * Obtém o usuário autenticado a partir do token Bearer
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @return array|null Dados do usuário ou null se não autenticado
 */
function getAuthenticatedUser($conn) {
    // Obter o header Authorization
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    
    if (!$authHeader) {
        error_log("auth_helper: No Authorization header");
        return null;
    }
    
    // Extrair o token Bearer
    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        error_log("auth_helper: Invalid Authorization format");
        return null;
    }
    
    $token = $matches[1];
    error_log("auth_helper: Token received: " . substr($token, 0, 20) . "...");
    
    // Buscar usuário pelo token
    $stmt = $conn->prepare("
        SELECT id, google_id, email, name, profile_picture, points, 
               invite_code, created_at, updated_at
        FROM users 
        WHERE token = ?
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("auth_helper: Token not found in database");
        $stmt->close();
        return null;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    error_log("auth_helper: User authenticated: " . $user['id'] . " - " . $user['email']);
    return $user;
}

/**
 * Envia erro de não autenticado
 */
function sendUnauthorizedError() {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Não autenticado. Token inválido ou expirado.'
    ]);
    exit;
}

/**
 * Envia resposta de sucesso
 */
function sendSuccess($data) {
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);
    exit;
}

/**
 * Envia resposta de erro
 */
function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
    exit;
}

/**
 * Valida headers de segurança anti-script
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param bool $strict Se true, bloqueia requisições sem headers
 * @return bool True se válido
 */
function validateSecurityHeadersAntiScript($conn, $strict = false) {
    $validator = new AntiScriptValidator($conn);
    
    if (!$validator->validate($strict)) {
        error_log("[AntiScript] Validation failed: " . implode(', ', $validator->getErrors()));
        
        if ($strict) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Security validation failed',
                'code' => 'ANTI_SCRIPT_BLOCKED',
                'debug' => $validator->getDebugInfo()
            ]);
            exit;
        }
    }
    
    return true;
}
?>

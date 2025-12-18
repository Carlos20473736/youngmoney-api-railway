<?php
/**
 * Auth Helper - Funções auxiliares para autenticação
 * VALIDAÇÃO SIMPLIFICADA - Apenas X-Request-ID e Authorization
 */

// ========================================
// VALIDAÇÃO SIMPLIFICADA DE HEADERS
// ========================================

// Endpoints públicos que NÃO precisam de validação
$_PUBLIC_ENDPOINTS = [
    '/api/v1/auth/google-login.php',
    '/api/v1/auth/device-login.php',
    '/api/v1/auth/login_v2.php',
    '/api/v1/debug/',
    '/api/v1/tunnel.php',
    '/health',
    '/'
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

// Se NÃO for endpoint público, apenas logar (sem bloquear)
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
    
    // Apenas logar os headers recebidos (sem bloquear)
    $requestId = $_headers['x-request-id'] ?? 'N/A';
    error_log("[Security] Request ID: " . $requestId . " - URI: " . $_currentUri);
}

// ========================================
// FIM DA VALIDAÇÃO
// ========================================

/**
 * Obtém o usuário autenticado a partir do token Bearer
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @return array|null Dados do usuário ou null se não autenticado
 */
function getAuthenticatedUser($conn) {
    // Obter o header Authorization - verificar múltiplas fontes
    $authHeader = null;
    
    // 1. Primeiro, verificar se veio do túnel seguro via $_SERVER
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        error_log("auth_helper: Authorization from _SERVER: " . substr($authHeader, 0, 30) . "...");
    }
    
    // 2. Se não, tentar getallheaders()
    if (!$authHeader) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        if ($authHeader) {
            error_log("auth_helper: Authorization from getallheaders: " . substr($authHeader, 0, 30) . "...");
        }
    }
    
    // 3. Se ainda não, verificar $_SERVER com outros formatos
    if (!$authHeader) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    }
    
    if (!$authHeader) {
        error_log("auth_helper: No Authorization header found in any source");
        error_log("auth_helper: _SERVER keys: " . implode(', ', array_keys($_SERVER)));
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
?>

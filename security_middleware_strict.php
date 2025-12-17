<?php
/**
 * Security Middleware STRICT - Validação OBRIGATÓRIA de headers anti-script
 * 
 * Este middleware bloqueia TODAS as requisições que não tenham os headers de segurança.
 * Requisições sem os headers serão rejeitadas com erro 403.
 * 
 * Headers obrigatórios:
 * - X-Device-Fingerprint
 * - X-Timestamp
 * - X-Nonce
 * - X-Request-Signature
 * - X-App-Hash
 * 
 * @version 1.0.0
 */

// Endpoints públicos que NÃO precisam de validação
$publicEndpoints = [
    '/',
    '/index.php',
    '/api/v1/auth/google-login.php',
    '/api/v1/auth/device-login.php',
    '/api/v1/auth/login_v2.php',
    '/api/v1/debug/headers.php',
    '/api/v1/debug/check-token.php',
    '/health',
    '/health.php'
];

// Obter URI atual
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Verificar se é endpoint público
$isPublic = false;
foreach ($publicEndpoints as $endpoint) {
    if ($requestUri === $endpoint || strpos($requestUri, $endpoint) === 0) {
        $isPublic = true;
        break;
    }
}

// Se for OPTIONS (preflight CORS), permitir
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $isPublic = true;
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

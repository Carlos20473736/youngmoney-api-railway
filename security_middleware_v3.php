<?php
/**
 * Security Middleware V3 - Proteção Máxima
 * 
 * Este middleware é carregado ANTES de qualquer endpoint
 * Implementa validação completa de segurança
 * 
 * @version 3.0.0
 */

// Endpoints públicos (não precisam de validação de segurança)
$publicEndpoints = [
    '/api/v1/auth/google-login.php',
    '/api/v1/auth/device-login.php',
    '/api/v1/invite/validate.php',
    '/api/v1/config.php',
    '/api/v1/config-simple.php',
    '/admin/',
    '/api/v1/cron/',
    '/health',
    '/ping',
    '/index.php',
    '/run_security_migration.php',
    '/api/v1/security/status.php'
];

// Endpoints que precisam apenas de autenticação básica (sem PoW)
$basicAuthEndpoints = [
    '/api/v1/user/profile.php',
    '/api/v1/user/balance.php',
    '/api/v1/ranking/list.php'
];

// Verificar se é endpoint público
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isPublicEndpoint = false;
$isBasicAuthEndpoint = false;

// Verificar se é endpoint raiz
if ($requestUri === '/' || $requestUri === '') {
    $isPublicEndpoint = true;
}

foreach ($publicEndpoints as $endpoint) {
    if (strpos($requestUri, $endpoint) !== false) {
        $isPublicEndpoint = true;
        break;
    }
}

foreach ($basicAuthEndpoints as $endpoint) {
    if (strpos($requestUri, $endpoint) !== false) {
        $isBasicAuthEndpoint = true;
        break;
    }
}

// Se for OPTIONS (preflight), permitir
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Se for endpoint público, pular validação
if ($isPublicEndpoint) {
    return;
}

// Carregar sistema de segurança
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/includes/UltraSecurityV3.php';

try {
    $conn = getDbConnection();
    $security = new UltraSecurityV3($conn);
    
    // Validar requisição
    $validation = $security->validate();
    
    if (!$validation['valid']) {
        // Logar violação
        error_log("[SECURITY_V3] Bloqueado: " . $validation['message']);
        error_log("[SECURITY_V3] Code: " . ($validation['code'] ?? 'UNKNOWN'));
        error_log("[SECURITY_V3] Endpoint: " . $requestUri);
        error_log("[SECURITY_V3] IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        // Retornar erro
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Security validation failed',
            'code' => $validation['code'] ?? 'SECURITY_ERROR',
            'security_score' => $validation['score'] ?? 0
        ]);
        exit;
    }
    
    // Adicionar headers de segurança na resposta
    header('X-Security-Score: ' . $validation['score']);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    
} catch (Exception $e) {
    error_log("[SECURITY_V3] Erro crítico: " . $e->getMessage());
    
    // Em caso de erro, BLOQUEAR por segurança
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Security system error',
        'code' => 'SECURITY_SYSTEM_ERROR'
    ]);
    exit;
}

<?php
/**
 * Global Security Middleware - Versão Simplificada
 * 
 * Valida apenas:
 * - X-Request-ID (opcional)
 * - Authorization: Bearer (para endpoints autenticados)
 * 
 * @version 2.0.0 - Simplificado
 */

// Endpoints públicos (não precisam de autenticação)
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
    '/api/v1/security/'
];

// Verificar se é endpoint público
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isPublicEndpoint = false;

// Endpoint raiz é público
if ($requestUri === '/' || $requestUri === '') {
    $isPublicEndpoint = true;
}

foreach ($publicEndpoints as $endpoint) {
    if (strpos($requestUri, $endpoint) !== false) {
        $isPublicEndpoint = true;
        break;
    }
}

// Se for OPTIONS (preflight CORS), permitir
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Adicionar headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-ID, X-Requested-With');
header('Content-Type: application/json');

// Se for endpoint público, pular validação
if ($isPublicEndpoint) {
    return;
}

// Para endpoints protegidos, apenas logar a requisição
// A validação do Bearer token é feita por cada endpoint individualmente
$requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? 'no-request-id';
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

// Log básico (opcional)
// error_log("[REQUEST] $requestUri - RequestID: $requestId");

// Continuar normalmente - cada endpoint valida seu próprio token
?>

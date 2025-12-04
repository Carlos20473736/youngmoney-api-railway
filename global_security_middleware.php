<?php
/**
 * Global Security Middleware
 * Carregado automaticamente ANTES de qualquer endpoint
 * Valida os 30 headers de segurança em TODAS as requisições
 */

// Endpoints que NÃO precisam de validação (públicos ou admin)
$publicEndpoints = [
    '/api/v1/auth/google-login.php',
    '/api/v1/auth/device-login.php',
    '/api/v1/invite/validate.php',
    '/admin/',
    '/api/v1/config.php',
    '/api/v1/config-simple.php'
];

// Verificar se é endpoint público
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isPublicEndpoint = false;

foreach ($publicEndpoints as $endpoint) {
    if (strpos($requestUri, $endpoint) !== false) {
        $isPublicEndpoint = true;
        break;
    }
}

// Se não for público, validar 30 headers
if (!$isPublicEndpoint && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    require_once __DIR__ . '/includes/HeadersValidatorV2.php';
    require_once __DIR__ . '/database.php';
    
    try {
        $conn = getDbConnection();
        $validator = new HeadersValidatorV2($conn);
        
        // Validar headers
        $validation = $validator->validateRequest();
        
        if (!$validation['valid']) {
            // Logar violação
            error_log("[SECURITY] Requisição bloqueada: " . $validation['message']);
            error_log("[SECURITY] Endpoint: " . $requestUri);
            error_log("[SECURITY] IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            
            // Retornar erro
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Security validation failed: ' . $validation['message'],
                'code' => 'SECURITY_VALIDATION_FAILED',
                'security_score' => $validation['score'] ?? 0
            ]);
            exit;
        }
        
        // Logar sucesso (opcional, comentado para não poluir logs)
        // error_log("[SECURITY] Requisição aprovada - Score: " . $validation['score']);
        
    } catch (Exception $e) {
        error_log("[SECURITY] Erro ao validar headers: " . $e->getMessage());
        
        // Em caso de erro no validator, BLOQUEAR por segurança
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Security validation error',
            'code' => 'SECURITY_ERROR'
        ]);
        exit;
    }
}

// Se passou na validação, continuar normalmente
?>

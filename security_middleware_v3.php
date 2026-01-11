<?php
/**
 * Security Middleware V3 - Proteção Máxima
 * 
 * Este middleware é carregado ANTES de qualquer endpoint
 * Implementa validação completa de segurança
 * 
 * INCLUI: Verificação de Modo de Manutenção
 * Quando ativado, TODAS as APIs POST e GET são bloqueadas
 * 
 * @version 3.4.0
 */

// =====================================================
// VERIFICAÇÃO DE MODO DE MANUTENÇÃO - PRIMEIRA COISA!
// =====================================================

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
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
// CONFIGURAÇÃO DE ENDPOINTS (para validação de segurança)
// =====================================================

// Endpoints públicos (não precisam de validação de segurança)
$publicEndpoints = [
    '/api/v1/auth/google-login.php',
    '/api/v1/auth/device-login.php',
    '/api/v1/invite/validate.php',
    '/api/v1/config.php',
    '/api/v1/config-simple.php',
    '/api/v1/device/check.php',
    '/api/v1/device/bind.php',
    '/api/v1/device/register.php',
    '/api/v1/tunnel.php',
    '/api/v1/secure.php',
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

$isPublicEndpoint = false;
$isBasicAuthEndpoint = false;

// Verificar se é endpoint raiz
if ($requestUri === '/' || $requestUri === '') {
    $isPublicEndpoint = true;
}

// Verificar endpoints públicos
foreach ($publicEndpoints as $endpoint) {
    if (strpos($requestUri, $endpoint) !== false) {
        $isPublicEndpoint = true;
        break;
    }
}

// Verificar endpoints de autenticação básica
foreach ($basicAuthEndpoints as $endpoint) {
    if (strpos($requestUri, $endpoint) !== false) {
        $isBasicAuthEndpoint = true;
        break;
    }
}

// =====================================================
// VALIDAÇÃO DE SEGURANÇA
// =====================================================

// Se for endpoint público, pular validação de segurança
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

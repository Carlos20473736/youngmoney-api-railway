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
 * @version 3.1.0
 */

// =====================================================
// CONFIGURAÇÃO DE ENDPOINTS
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

// Endpoints ISENTOS do modo de manutenção (sempre funcionam)
$maintenanceExemptEndpoints = [
    '/admin/maintenance.php',  // Endpoint para controlar manutenção
    '/admin/',                  // Todo painel admin
    '/health',
    '/ping',
    '/index.php'
];

// Lista de emails de administradores que podem acessar durante manutenção
$ADMIN_EMAILS = [
    'soltacartatigri@gmail.com',
    'muriel25herrera@gmail.com'
];

// =====================================================
// VERIFICAÇÕES INICIAIS
// =====================================================

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isPublicEndpoint = false;
$isBasicAuthEndpoint = false;
$isMaintenanceExempt = false;

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

// Verificar endpoints isentos de manutenção
foreach ($maintenanceExemptEndpoints as $endpoint) {
    if (strpos($requestUri, $endpoint) !== false) {
        $isMaintenanceExempt = true;
        break;
    }
}

// Se for OPTIONS (preflight), permitir
if ($requestMethod === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// =====================================================
// VERIFICAÇÃO DE MODO DE MANUTENÇÃO
// =====================================================

/**
 * Verifica se o modo de manutenção está ativo
 * Bloqueia TODAS as requisições POST e GET quando ativado
 * Exceto endpoints administrativos e admins autenticados
 */
function checkMaintenanceMode() {
    global $maintenanceExemptEndpoints, $ADMIN_EMAILS, $requestUri;
    
    // Verificar se endpoint é isento
    foreach ($maintenanceExemptEndpoints as $endpoint) {
        if (strpos($requestUri, $endpoint) !== false) {
            return ['active' => false];
        }
    }
    
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
        
        if (!$isMaintenanceActive) {
            $conn->close();
            return ['active' => false];
        }
        
        // Modo de manutenção ATIVO - Buscar mensagem
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_message'");
        $stmt->execute();
        $result = $stmt->get_result();
        $msgRow = $result->fetch_assoc();
        $stmt->close();
        
        $message = $msgRow ? $msgRow['setting_value'] : 'Servidor em manutenção. Tente novamente mais tarde.';
        
        // Verificar se é um admin tentando acessar
        // Tentar extrair email do token de autenticação
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $isAdmin = false;
        
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
            }
        }
        
        $conn->close();
        
        // Se for admin, permitir acesso
        if ($isAdmin) {
            error_log("[MAINTENANCE] Admin autorizado durante manutenção - Endpoint: $requestUri");
            return ['active' => false];
        }
        
        // Modo de manutenção ativo e usuário não é admin
        return [
            'active' => true,
            'message' => $message
        ];
        
    } catch (Exception $e) {
        error_log("[MAINTENANCE] Erro ao verificar modo de manutenção: " . $e->getMessage());
        // Em caso de erro, NÃO bloquear (fail-open para não derrubar o serviço)
        return ['active' => false];
    }
}

// Executar verificação de manutenção ANTES de qualquer outra coisa
$maintenanceStatus = checkMaintenanceMode();

if ($maintenanceStatus['active']) {
    error_log("[MAINTENANCE] Requisição BLOQUEADA - Endpoint: $requestUri - Method: $requestMethod");
    
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    http_response_code(503); // Service Unavailable
    
    echo json_encode([
        'status' => 'error',
        'maintenance' => true,
        'maintenance_mode' => true,
        'message' => $maintenanceStatus['message'],
        'code' => 'MAINTENANCE_MODE'
    ]);
    exit;
}

// =====================================================
// VALIDAÇÃO DE SEGURANÇA (código original)
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

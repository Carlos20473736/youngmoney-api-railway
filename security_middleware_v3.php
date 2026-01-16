<?php
/**
 * Security Middleware V3 - Proteção Máxima
 * 
 * Este middleware é carregado ANTES de qualquer endpoint
 * Implementa validação completa de segurança
 * 
 * INCLUI: Verificação de Modo de Manutenção
 * INCLUI: Verificação de Versão Mínima do APK (GLOBAL)
 * Quando ativado, TODAS as APIs POST e GET são bloqueadas
 * 
 * @version 3.5.0
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

// =====================================================
// VERSÃO MÍNIMA DO APK - CONFIGURAÇÃO GLOBAL
// =====================================================
// APKs com versão inferior a esta serão BLOQUEADOS
$GLOBAL_MIN_APP_VERSION = '44.0';

// Endpoints isentos de verificação de manutenção (admins podem acessar mesmo sem token)
$maintenanceExemptEndpoints = [
    '/admin/maintenance.php',
    '/api/v1/device/check.php',
    '/api/v1/device/bind.php',
    '/api/v1/device/register.php',
    '/api/v1/auth/google-login.php',
    '/api/v1/auth/device-login.php',
    '/api/v1/auth/email-login.php',     // Login por email e senha
    '/api/v1/auth/email-register.php',  // Registro por email e senha
    '/api/v1/tunnel.php',
    '/api/v1/secure.php',
    '/api/v1/app/check-update.php',  // Verificação de atualização do app
    '/api/v1/app/version.php',        // Versão do app
    '/api/v1/security/allowed-installers.php'  // Verifica versão internamente, isento de manutenção
];

// Endpoints isentos de verificação de versão (podem ser acessados por qualquer versão)
// Isso permite que APKs antigos vejam a mensagem de atualização necessária
$versionExemptEndpoints = [
    '/api/v1/app/check-update.php',
    '/api/v1/app/version.php',
    '/api/v1/auth/email-login.php',     // Login por email e senha
    '/api/v1/auth/email-register.php',  // Registro por email e senha
    '/admin/',
    '/health',
    '/ping',
    '/index.php'
];

// Verificar se é endpoint isento de manutenção
$isMaintenanceExempt = false;
foreach ($maintenanceExemptEndpoints as $exemptEndpoint) {
    if (strpos($requestUri, $exemptEndpoint) !== false) {
        $isMaintenanceExempt = true;
        break;
    }
}

// Verificar se é endpoint isento de verificação de versão
$isVersionExempt = false;
foreach ($versionExemptEndpoints as $exemptEndpoint) {
    if (strpos($requestUri, $exemptEndpoint) !== false) {
        $isVersionExempt = true;
        break;
    }
}

// =====================================================
// FUNÇÃO: Comparar versões do app
// =====================================================
function compareAppVersionsGlobal($v1, $v2) {
    // Remover prefixo 'v' ou 'V' se existir
    $v1 = ltrim($v1, 'vV');
    $v2 = ltrim($v2, 'vV');
    
    // Separar em partes
    $parts1 = explode('.', $v1);
    $parts2 = explode('.', $v2);
    
    // Garantir que ambas tenham 3 partes
    while (count($parts1) < 3) $parts1[] = '0';
    while (count($parts2) < 3) $parts2[] = '0';
    
    // Comparar cada parte
    for ($i = 0; $i < 3; $i++) {
        $num1 = intval($parts1[$i]);
        $num2 = intval($parts2[$i]);
        
        if ($num1 < $num2) return -1;
        if ($num1 > $num2) return 1;
    }
    
    return 0;
}

// =====================================================
// FUNÇÃO: Obter versão do app da requisição
// =====================================================
function getAppVersionFromRequestGlobal() {
    // 1. Tentar obter do header X-App-Version
    if (isset($_SERVER['HTTP_X_APP_VERSION']) && !empty($_SERVER['HTTP_X_APP_VERSION'])) {
        return trim($_SERVER['HTTP_X_APP_VERSION']);
    }
    
    // 2. Tentar obter do header X-APP-VERSION (case insensitive)
    foreach ($_SERVER as $key => $value) {
        if (strtolower($key) === 'http_x_app_version' && !empty($value)) {
            return trim($value);
        }
    }
    
    // 3. Tentar obter do body da requisição
    $rawBody = file_get_contents('php://input');
    if ($rawBody) {
        $data = json_decode($rawBody, true);
        if (isset($data['app_version']) && !empty($data['app_version'])) {
            return trim($data['app_version']);
        }
        if (isset($data['appVersion']) && !empty($data['appVersion'])) {
            return trim($data['appVersion']);
        }
        if (isset($data['version']) && !empty($data['version'])) {
            return trim($data['version']);
        }
    }
    
    // 4. Tentar obter do query string
    if (isset($_GET['app_version']) && !empty($_GET['app_version'])) {
        return trim($_GET['app_version']);
    }
    if (isset($_GET['appVersion']) && !empty($_GET['appVersion'])) {
        return trim($_GET['appVersion']);
    }
    
    // 5. Tentar obter do POST
    if (isset($_POST['app_version']) && !empty($_POST['app_version'])) {
        return trim($_POST['app_version']);
    }
    
    return null;
}

// =====================================================
// VERIFICAÇÃO GLOBAL DE VERSÃO DO APK - ANTES DE TUDO!
// =====================================================
if (!$isVersionExempt) {
    $appVersion = getAppVersionFromRequestGlobal();
    
    // Se a versão não foi enviada OU é inferior à mínima, BLOQUEAR
    if ($appVersion === null || $appVersion === '' || empty(trim($appVersion))) {
        error_log("[VERSION_CHECK] Requisição BLOQUEADA - APK não enviou versão. Endpoint: $requestUri");
        
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        http_response_code(426); // Upgrade Required
        
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'update_required' => true,
            'current_version' => null,
            'min_version' => $GLOBAL_MIN_APP_VERSION,
            'message' => 'Versão do app não identificada. Por favor, atualize o aplicativo para continuar.',
            'code' => 'VERSION_NOT_PROVIDED',
            'reason' => 'app_version_missing'
        ]);
        exit;
    }
    
    // Verificar se a versão é inferior à mínima
    if (compareAppVersionsGlobal($appVersion, $GLOBAL_MIN_APP_VERSION) < 0) {
        error_log("[VERSION_CHECK] Requisição BLOQUEADA - Versão desatualizada: $appVersion (mínima: $GLOBAL_MIN_APP_VERSION). Endpoint: $requestUri");
        
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        http_response_code(426); // Upgrade Required
        
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'update_required' => true,
            'current_version' => $appVersion,
            'min_version' => $GLOBAL_MIN_APP_VERSION,
            'message' => 'Sua versão do app está desatualizada. Por favor, atualize para a versão ' . $GLOBAL_MIN_APP_VERSION . ' ou superior para continuar usando o aplicativo.',
            'code' => 'UPDATE_REQUIRED',
            'reason' => 'version_outdated'
        ]);
        exit;
    }
    
    // Log de versão aceita
    error_log("[VERSION_CHECK] Versão aceita: $appVersion (mínima: $GLOBAL_MIN_APP_VERSION). Endpoint: $requestUri");
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

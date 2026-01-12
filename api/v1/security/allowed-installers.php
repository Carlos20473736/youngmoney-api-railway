<?php
/**
 * API de Instaladores Permitidos
 * 
 * Este endpoint retorna a lista de instaladores permitidos.
 * MODIFICADO: Agora sempre retorna ["*"] para permitir instalação de qualquer fonte.
 * 
 * IMPORTANTE: Este endpoint também verifica a versão mínima do APK (44.0)
 * APKs com versão inferior ou sem versão identificada serão BLOQUEADOS.
 * 
 * Endpoint: GET /api/v1/security/allowed-installers.php
 * 
 * Resposta:
 * {
 *   "success": true,
 *   "installers": ["*"],
 *   "allow_any": true,
 *   "timestamp": 1234567890
 * }
 * 
 * @version 3.0.0 - Sempre permite qualquer instalador (ignora configuração do banco)
 */

// Habilitar CORS para permitir requisições do app
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-App-Version');
header('Content-Type: application/json; charset=utf-8');

// Responder OPTIONS para preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Apenas aceitar GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método não permitido'
    ]);
    exit;
}

// =====================================================
// VERSÃO MÍNIMA DO APK - CONFIGURAÇÃO
// =====================================================
$MIN_APP_VERSION = '44.0';

/**
 * Compara duas versões do app
 * Retorna: -1 se v1 < v2, 0 se v1 == v2, 1 se v1 > v2
 */
function compareVersions($v1, $v2) {
    $v1 = ltrim($v1, 'vV');
    $v2 = ltrim($v2, 'vV');
    
    $parts1 = explode('.', $v1);
    $parts2 = explode('.', $v2);
    
    while (count($parts1) < 3) $parts1[] = '0';
    while (count($parts2) < 3) $parts2[] = '0';
    
    for ($i = 0; $i < 3; $i++) {
        $num1 = intval($parts1[$i]);
        $num2 = intval($parts2[$i]);
        
        if ($num1 < $num2) return -1;
        if ($num1 > $num2) return 1;
    }
    
    return 0;
}

/**
 * Obtém a versão do app da requisição
 */
function getAppVersion() {
    // 1. Header X-App-Version
    if (isset($_SERVER['HTTP_X_APP_VERSION']) && !empty($_SERVER['HTTP_X_APP_VERSION'])) {
        return trim($_SERVER['HTTP_X_APP_VERSION']);
    }
    
    // 2. Query string app_version
    if (isset($_GET['app_version']) && !empty($_GET['app_version'])) {
        return trim($_GET['app_version']);
    }
    
    // 3. Query string appVersion
    if (isset($_GET['appVersion']) && !empty($_GET['appVersion'])) {
        return trim($_GET['appVersion']);
    }
    
    // 4. Query string version
    if (isset($_GET['version']) && !empty($_GET['version'])) {
        return trim($_GET['version']);
    }
    
    return null;
}

// =====================================================
// VERIFICAÇÃO DE VERSÃO DO APK - OBRIGATÓRIA
// =====================================================
$appVersion = getAppVersion();

// Se a versão não foi enviada, BLOQUEAR
if ($appVersion === null || $appVersion === '' || empty(trim($appVersion))) {
    error_log("[ALLOWED_INSTALLERS] BLOQUEADO - APK não enviou versão");
    
    http_response_code(426); // Upgrade Required
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'update_required' => true,
        'current_version' => null,
        'min_version' => $MIN_APP_VERSION,
        'message' => 'Versão do app não identificada. Por favor, atualize o aplicativo para continuar.',
        'code' => 'VERSION_NOT_PROVIDED',
        'reason' => 'app_version_missing',
        'timestamp' => time() * 1000
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Se a versão é inferior à mínima, BLOQUEAR
if (compareVersions($appVersion, $MIN_APP_VERSION) < 0) {
    error_log("[ALLOWED_INSTALLERS] BLOQUEADO - Versão desatualizada: $appVersion (mínima: $MIN_APP_VERSION)");
    
    http_response_code(426); // Upgrade Required
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'update_required' => true,
        'current_version' => $appVersion,
        'min_version' => $MIN_APP_VERSION,
        'message' => 'Sua versão do app está desatualizada. Por favor, atualize para a versão ' . $MIN_APP_VERSION . ' ou superior para continuar usando o aplicativo.',
        'code' => 'UPDATE_REQUIRED',
        'reason' => 'version_outdated',
        'timestamp' => time() * 1000
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Log de versão aceita
error_log("[ALLOWED_INSTALLERS] Versão aceita: $appVersion (mínima: $MIN_APP_VERSION)");

// =====================================================
// SEMPRE PERMITIR QUALQUER INSTALADOR
// Isso permite instalação fora da Play Store
// =====================================================
echo json_encode([
    'success' => true,
    'installers' => ['*'],
    'allow_any' => true,
    'app_version' => $appVersion,
    'min_version' => $MIN_APP_VERSION,
    'timestamp' => time() * 1000
], JSON_UNESCAPED_UNICODE);
?>

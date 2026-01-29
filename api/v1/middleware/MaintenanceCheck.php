<?php
/**
 * MaintenanceCheck.php
 * 
 * Middleware centralizado para verificação de:
 * 1. Modo de manutenção do sistema
 * 2. Versão mínima do app (app_update)
 * 
 * IMPORTANTE: Se o APK não enviar a versão, a requisição será BLOQUEADA
 * quando a verificação de versão estiver ativada.
 * 
 * Uso:
 * require_once __DIR__ . '/middleware/MaintenanceCheck.php';
 * checkMaintenanceAndVersion($conn, $userEmail, $appVersion);
 * 
 * @version 2.0.0
 */

// Lista de emails de administradores que podem bypass manutenção e verificação de versão
$ADMIN_EMAILS = [
    'soltacartatigri@gmail.com',
    'muriel25herrera@gmail.com',
    'gustavopramos97@gmail.com'
];

/**
 * Compara duas versões no formato "X.Y" ou "X.Y.Z"
 * Retorna: -1 se v1 < v2, 0 se v1 == v2, 1 se v1 > v2
 */
function compareAppVersions($v1, $v2) {
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

/**
 * Verifica modo de manutenção e versão do app
 * Bloqueia a requisição se necessário
 * 
 * COMPORTAMENTO:
 * - Se app_update_enabled = 1 e app_update_force = 1:
 *   - Se APK não enviar versão → BLOQUEIA (força atualização)
 *   - Se APK enviar versão antiga → BLOQUEIA (força atualização)
 *   - Se APK enviar versão válida → PERMITE
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param string|null $userEmail Email do usuário (para verificar se é admin)
 * @param string|null $appVersion Versão do app do cliente (OBRIGATÓRIO quando verificação está ativa)
 * @param bool $checkVersion Se deve verificar a versão do app (default: true)
 */
function checkMaintenanceAndVersion($conn, $userEmail = null, $appVersion = null, $checkVersion = true) {
    global $ADMIN_EMAILS;
    
    // Verificar se é admin
    $isAdmin = false;
    if ($userEmail) {
        $isAdmin = in_array(strtolower($userEmail), array_map('strtolower', $ADMIN_EMAILS));
    }
    
    // ========================================
    // 1. VERIFICAR MODO DE MANUTENÇÃO
    // ========================================
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $isMaintenanceActive = ($row && $row['setting_value'] === '1');
        
        if ($isMaintenanceActive && !$isAdmin) {
            // Buscar mensagem de manutenção
            $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_message'");
            $stmt->execute();
            $result = $stmt->get_result();
            $msgRow = $result->fetch_assoc();
            $stmt->close();
            
            $message = $msgRow ? $msgRow['setting_value'] : 'Servidor em manutenção. Tente novamente mais tarde.';
            
            error_log("[MAINTENANCE_CHECK] Requisição BLOQUEADA - Modo de manutenção ativo. Email: " . ($userEmail ?? 'N/A'));
            
            http_response_code(503);
            echo json_encode([
                'success' => false,
                'status' => 'error',
                'maintenance' => true,
                'maintenance_mode' => true,
                'message' => $message,
                'code' => 'MAINTENANCE_MODE'
            ]);
            $conn->close();
            exit;
        }
        
        if ($isMaintenanceActive && $isAdmin) {
            error_log("[MAINTENANCE_CHECK] Admin autorizado durante manutenção: $userEmail");
        }
    }
    
    // ========================================
    // 2. VERIFICAR VERSÃO DO APP (APP UPDATE)
    // ========================================
    if ($checkVersion) {
        // Buscar configurações de atualização
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('app_update_enabled', 'app_update_min_version', 'app_update_force', 'app_update_message', 'app_update_require_version')");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            
            $settings = [];
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            $stmt->close();
            
            $updateEnabled = ($settings['app_update_enabled'] ?? '0') === '1';
            $minVersion = $settings['app_update_min_version'] ?? '1.0.0';
            $forceUpdate = ($settings['app_update_force'] ?? '0') === '1';
            $requireVersion = ($settings['app_update_require_version'] ?? '1') === '1'; // NOVO: Exigir que APK envie versão
            $updateMessage = $settings['app_update_message'] ?? 'Uma atualização é necessária para continuar usando o app.';
            
            // Se atualização está habilitada e é forçada
            if ($updateEnabled && $forceUpdate && !$isAdmin) {
                
                // VERIFICAÇÃO 1: Se APK não enviou a versão e é obrigatório enviar
                if (($appVersion === null || $appVersion === '' || empty(trim($appVersion))) && $requireVersion) {
                    error_log("[MAINTENANCE_CHECK] Requisição BLOQUEADA - APK não enviou versão. Versão obrigatória está ativa.");
                    
                    http_response_code(426); // Upgrade Required
                    echo json_encode([
                        'success' => false,
                        'status' => 'error',
                        'update_required' => true,
                        'current_version' => null,
                        'min_version' => $minVersion,
                        'message' => 'Versão do app não identificada. Por favor, atualize o aplicativo.',
                        'code' => 'VERSION_NOT_PROVIDED',
                        'reason' => 'app_version_missing'
                    ]);
                    $conn->close();
                    exit;
                }
                
                // VERIFICAÇÃO 2: Se APK enviou versão, verificar se é antiga
                if ($appVersion !== null && $appVersion !== '') {
                    $needsUpdate = compareAppVersions($appVersion, $minVersion) < 0;
                    
                    if ($needsUpdate) {
                        error_log("[MAINTENANCE_CHECK] Requisição BLOQUEADA - Versão desatualizada. Versão: $appVersion, Mínima: $minVersion");
                        
                        http_response_code(426); // Upgrade Required
                        echo json_encode([
                            'success' => false,
                            'status' => 'error',
                            'update_required' => true,
                            'current_version' => $appVersion,
                            'min_version' => $minVersion,
                            'message' => $updateMessage,
                            'code' => 'UPDATE_REQUIRED',
                            'reason' => 'version_outdated'
                        ]);
                        $conn->close();
                        exit;
                    }
                }
            }
            
            // Log de versão válida
            if ($appVersion !== null && $appVersion !== '') {
                error_log("[MAINTENANCE_CHECK] Versão do APK aceita: $appVersion (mínima: $minVersion)");
            }
        }
    }
}

/**
 * Versão simplificada que só verifica manutenção (não verifica versão)
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param string|null $userEmail Email do usuário
 */
function checkMaintenanceOnly($conn, $userEmail = null) {
    checkMaintenanceAndVersion($conn, $userEmail, null, false);
}

/**
 * Obtém a versão do app de várias fontes possíveis
 * 
 * @return string|null Versão do app ou null se não encontrada
 */
function getAppVersionFromRequest() {
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

/**
 * Obtém o email do usuário de várias fontes possíveis
 * 
 * @return string|null Email do usuário ou null se não encontrado
 */
function getUserEmailFromRequest() {
    // 1. Tentar obter do body da requisição
    $rawBody = file_get_contents('php://input');
    if ($rawBody) {
        $data = json_decode($rawBody, true);
        if (isset($data['email']) && !empty($data['email'])) {
            return trim($data['email']);
        }
        if (isset($data['user_email']) && !empty($data['user_email'])) {
            return trim($data['user_email']);
        }
    }
    
    // 2. Tentar obter do query string
    if (isset($_GET['email']) && !empty($_GET['email'])) {
        return trim($_GET['email']);
    }
    
    // 3. Tentar obter do POST
    if (isset($_POST['email']) && !empty($_POST['email'])) {
        return trim($_POST['email']);
    }
    
    return null;
}
?>

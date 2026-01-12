<?php
/**
 * MaintenanceCheck.php
 * 
 * Middleware centralizado para verificação de:
 * 1. Modo de manutenção do sistema
 * 2. Versão mínima do app (app_update)
 * 
 * Uso:
 * require_once __DIR__ . '/middleware/MaintenanceCheck.php';
 * checkMaintenanceAndVersion($conn, $userEmail);
 * 
 * @version 1.0.0
 */

// Lista de emails de administradores que podem bypass manutenção
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
 * Verifica modo de manutenção e versão do app
 * Bloqueia a requisição se necessário
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param string|null $userEmail Email do usuário (para verificar se é admin)
 * @param string|null $appVersion Versão do app do cliente
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
    if ($checkVersion && $appVersion !== null) {
        // Buscar configurações de atualização
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('app_update_enabled', 'app_update_min_version', 'app_update_force', 'app_update_message')");
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
            $updateMessage = $settings['app_update_message'] ?? 'Uma atualização é necessária para continuar usando o app.';
            
            // Se atualização está habilitada e é forçada, verificar versão
            if ($updateEnabled && $forceUpdate) {
                $needsUpdate = compareAppVersions($appVersion, $minVersion) < 0;
                
                if ($needsUpdate && !$isAdmin) {
                    error_log("[MAINTENANCE_CHECK] Requisição BLOQUEADA - Versão desatualizada. Versão: $appVersion, Mínima: $minVersion");
                    
                    http_response_code(426); // Upgrade Required
                    echo json_encode([
                        'success' => false,
                        'status' => 'error',
                        'update_required' => true,
                        'current_version' => $appVersion,
                        'min_version' => $minVersion,
                        'message' => $updateMessage,
                        'code' => 'UPDATE_REQUIRED'
                    ]);
                    $conn->close();
                    exit;
                }
            }
        }
    }
}

/**
 * Versão simplificada que só verifica manutenção
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param string|null $userEmail Email do usuário
 */
function checkMaintenanceOnly($conn, $userEmail = null) {
    checkMaintenanceAndVersion($conn, $userEmail, null, false);
}
?>

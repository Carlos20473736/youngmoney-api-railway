<?php
/**
 * API Endpoint: Verificação de Atualização do App
 * 
 * Compara a versão do app enviada pelo cliente com a versão mínima configurada no servidor.
 * Se a versão do app for menor que a versão mínima, retorna que há atualização disponível.
 * 
 * GET /api/v1/app/check-update.php?version=43.0
 * 
 * Parâmetros:
 * - version: Versão atual do app (ex: "43.0", "44.1")
 * 
 * Response (atualização necessária):
 * {
 *   "success": true,
 *   "data": {
 *     "update_required": true,
 *     "update_enabled": true,
 *     "current_version": "43.0",
 *     "min_version": "44.0",
 *     "download_url": "https://...",
 *     "force_update": true,
 *     "release_notes": "Nova versão disponível com melhorias"
 *   }
 * }
 * 
 * Response (app atualizado):
 * {
 *   "success": true,
 *   "data": {
 *     "update_required": false,
 *     "update_enabled": false,
 *     "current_version": "44.0",
 *     "min_version": "44.0"
 *   }
 * }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req, X-Request-ID, X-Timestamp, X-Nonce, X-Device-Fingerprint, X-App-Hash, X-Request-Signature, X-Rotating-Key, X-Native-Signature, X-Key-Window');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../database.php';

/**
 * Compara duas versões no formato "X.Y" ou "X.Y.Z"
 * Retorna: -1 se v1 < v2, 0 se v1 == v2, 1 se v1 > v2
 */
function compareVersions($v1, $v2) {
    // Remover prefixo 'v' se existir
    $v1 = ltrim($v1, 'vV');
    $v2 = ltrim($v2, 'vV');
    
    $parts1 = explode('.', $v1);
    $parts2 = explode('.', $v2);
    
    // Garantir pelo menos 3 partes
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

try {
    $conn = getDbConnection();
    
    // Obter versão do app enviada pelo cliente
    $appVersion = isset($_GET['version']) ? trim($_GET['version']) : '';
    
    if (empty($appVersion)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Parâmetro version é obrigatório'
        ]);
        exit;
    }
    
    // Buscar configurações de atualização do banco
    $settings = [];
    $settingsQuery = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'app_update_%'";
    $result = $conn->query($settingsQuery);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    // Configurações de atualização
    $updateEnabled = ($settings['app_update_enabled'] ?? '0') === '1';
    $minVersion = $settings['app_update_min_version'] ?? '1.0.0';
    $downloadUrl = $settings['app_update_download_url'] ?? '';
    $forceUpdate = ($settings['app_update_force'] ?? '0') === '1';
    $releaseNotes = $settings['app_update_release_notes'] ?? '';
    
    // Verificar se atualização é necessária
    $updateRequired = false;
    if ($updateEnabled) {
        // Se a versão do app for menor que a versão mínima, atualização é necessária
        $updateRequired = compareVersions($appVersion, $minVersion) < 0;
    }
    
    $response = [
        'success' => true,
        'data' => [
            'update_required' => $updateRequired,
            'update_enabled' => $updateEnabled,
            'current_version' => $appVersion,
            'min_version' => $minVersion,
            'download_url' => $downloadUrl,
            'force_update' => $forceUpdate && $updateRequired,
            'release_notes' => $releaseNotes
        ]
    ];
    
    // Log para debug
    error_log("check-update: app_version=$appVersion, min_version=$minVersion, update_required=" . ($updateRequired ? 'true' : 'false'));
    
    echo json_encode($response);
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("app/check-update.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor'
    ]);
}
?>

<?php
/**
 * API Endpoint: Verificação de Versão do App
 * 
 * Retorna a versão atual do app e link de download se houver atualização disponível
 * 
 * GET /api/v1/app/version.php
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "current_version": "43.0",
 *     "version_code": 43,
 *     "update_enabled": true,
 *     "download_url": "https://...",
 *     "force_update": false,
 *     "release_notes": "Correções de bugs e melhorias"
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

try {
    $conn = getDbConnection();
    
    // Buscar configurações de versão do app
    $settings = [];
    $settingsQuery = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'app_%'";
    $result = $conn->query($settingsQuery);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    // Valores padrão
    $currentVersion = $settings['app_version'] ?? '43.0';
    $versionCode = intval($settings['app_version_code'] ?? 43);
    $updateEnabled = ($settings['app_update_enabled'] ?? '0') === '1';
    $downloadUrl = $settings['app_download_url'] ?? '';
    $forceUpdate = ($settings['app_force_update'] ?? '0') === '1';
    $releaseNotes = $settings['app_release_notes'] ?? '';
    
    $response = [
        'success' => true,
        'data' => [
            'current_version' => $currentVersion,
            'version_code' => $versionCode,
            'update_enabled' => $updateEnabled,
            'download_url' => $downloadUrl,
            'force_update' => $forceUpdate,
            'release_notes' => $releaseNotes
        ]
    ];
    
    echo json_encode($response);
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("app/version.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor'
    ]);
}
?>

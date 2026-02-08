<?php
/**
 * APK Activate API
 * 
 * Endpoint para ativar um APK específico como o download principal.
 * 
 * POST /api/v1/apk/activate.php
 * - Body JSON: { "id": 1 }
 * 
 * @version 1.0.0
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

require_once __DIR__ . '/../../../db_config.php';

$input = json_decode(file_get_contents('php://input'), true);
$apkId = $input['id'] ?? null;

if (!$apkId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID do APK é obrigatório']);
    exit;
}

try {
    $pdo = getPDOConnection();
    
    // Verificar se o APK existe
    $stmt = $pdo->prepare("SELECT * FROM apk_uploads WHERE id = ?");
    $stmt->execute([$apkId]);
    $apk = $stmt->fetch();
    
    if (!$apk) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'APK não encontrado']);
        exit;
    }
    
    // Desativar todos
    $pdo->exec("UPDATE apk_uploads SET is_active = 0");
    
    // Ativar o selecionado
    $stmt = $pdo->prepare("UPDATE apk_uploads SET is_active = 1 WHERE id = ?");
    $stmt->execute([$apkId]);
    
    // Atualizar a configuração do sistema com o link do APK ativo
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, created_at, updated_at) 
        VALUES ('app_update_secondary_url', ?, NOW(), NOW()) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
    ");
    $stmt->execute([$apk['download_url']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'APK ativado com sucesso',
        'data' => [
            'id' => (int) $apk['id'],
            'file_name' => $apk['file_name'],
            'version' => $apk['version'],
            'download_url' => $apk['download_url']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("APK Activate Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao ativar APK: ' . $e->getMessage()]);
}
?>

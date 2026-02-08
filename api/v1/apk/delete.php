<?php
/**
 * APK Delete API
 * 
 * Endpoint para deletar um APK específico.
 * 
 * POST /api/v1/apk/delete.php
 * - Body JSON: { "id": 1 } ou { "file_name": "arquivo.apk" }
 * 
 * @version 1.0.0
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

require_once __DIR__ . '/../../../db_config.php';

$input = json_decode(file_get_contents('php://input'), true);
$apkId = $input['id'] ?? null;
$fileName = $input['file_name'] ?? null;

if (!$apkId && !$fileName) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID ou nome do arquivo é obrigatório']);
    exit;
}

$uploadDir = __DIR__ . '/../../../uploads/apk/';

try {
    $pdo = getPDOConnection();
    
    // Buscar o APK
    if ($apkId) {
        $stmt = $pdo->prepare("SELECT * FROM apk_uploads WHERE id = ?");
        $stmt->execute([$apkId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM apk_uploads WHERE file_name = ?");
        $stmt->execute([basename($fileName)]);
    }
    
    $apk = $stmt->fetch();
    
    if (!$apk) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'APK não encontrado']);
        exit;
    }
    
    // Deletar arquivo físico
    $filePath = $uploadDir . $apk['file_name'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    // Deletar registro do banco
    $deleteStmt = $pdo->prepare("DELETE FROM apk_uploads WHERE id = ?");
    $deleteStmt->execute([$apk['id']]);
    
    // Se era o APK ativo, ativar o mais recente
    if ($apk['is_active']) {
        $pdo->exec("UPDATE apk_uploads SET is_active = 1 WHERE id = (SELECT id FROM (SELECT id FROM apk_uploads ORDER BY id DESC LIMIT 1) as t)");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'APK deletado com sucesso',
        'deleted' => [
            'id' => (int) $apk['id'],
            'file_name' => $apk['file_name']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("APK Delete Error: " . $e->getMessage());
    
    // Fallback: tentar deletar apenas o arquivo
    if ($fileName) {
        $filePath = $uploadDir . basename($fileName);
        if (file_exists($filePath)) {
            unlink($filePath);
            echo json_encode(['success' => true, 'message' => 'Arquivo deletado (sem registro no banco)']);
            exit;
        }
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao deletar APK: ' . $e->getMessage()]);
}
?>

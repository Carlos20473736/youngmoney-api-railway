<?php
/**
 * APK Download Direto
 * 
 * Endpoint para download direto do APK.
 * Incrementa o contador de downloads e serve o arquivo.
 * 
 * GET /api/v1/apk/download.php
 * - Parâmetro 'file' (opcional): nome do arquivo específico
 * - Sem parâmetro: baixa o APK ativo mais recente
 * 
 * @version 1.0.0
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

require_once __DIR__ . '/../../../db_config.php';

$uploadDir = __DIR__ . '/../../../uploads/apk/';
$requestedFile = $_GET['file'] ?? null;

// Sanitizar nome do arquivo para evitar path traversal
if ($requestedFile) {
    $requestedFile = basename($requestedFile);
    
    // Verificar extensão
    if (strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION)) !== 'apk') {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Arquivo inválido']);
        exit;
    }
}

$filePath = null;
$fileName = null;

try {
    $pdo = getPDOConnection();
    
    if ($requestedFile) {
        // Buscar arquivo específico
        $stmt = $pdo->prepare("SELECT * FROM apk_uploads WHERE file_name = ? LIMIT 1");
        $stmt->execute([$requestedFile]);
        $apk = $stmt->fetch();
    } else {
        // Buscar APK ativo mais recente
        $stmt = $pdo->prepare("SELECT * FROM apk_uploads WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $apk = $stmt->fetch();
    }
    
    if ($apk) {
        $filePath = $uploadDir . $apk['file_name'];
        $fileName = $apk['original_name'] ?: $apk['file_name'];
        
        // Incrementar contador de downloads
        $updateStmt = $pdo->prepare("UPDATE apk_uploads SET download_count = download_count + 1 WHERE id = ?");
        $updateStmt->execute([$apk['id']]);
    }
    
} catch (Exception $e) {
    error_log("APK Download DB Error: " . $e->getMessage());
    
    // Fallback: tentar servir o arquivo diretamente
    if ($requestedFile) {
        $filePath = $uploadDir . $requestedFile;
        $fileName = $requestedFile;
    }
}

// Se não encontrou no banco, tentar buscar o arquivo mais recente no diretório
if (!$filePath || !file_exists($filePath)) {
    if ($requestedFile) {
        $testPath = $uploadDir . $requestedFile;
        if (file_exists($testPath)) {
            $filePath = $testPath;
            $fileName = $requestedFile;
        }
    } else {
        // Buscar o APK mais recente no diretório
        $apkFiles = glob($uploadDir . '*.apk');
        if (!empty($apkFiles)) {
            // Ordenar por data de modificação (mais recente primeiro)
            usort($apkFiles, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            $filePath = $apkFiles[0];
            $fileName = basename($apkFiles[0]);
        }
    }
}

// Verificar se o arquivo existe
if (!$filePath || !file_exists($filePath)) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode([
        'success' => false, 
        'error' => 'APK não encontrado',
        'message' => 'Nenhum APK disponível para download. Faça upload pelo painel admin.'
    ]);
    exit;
}

// Servir o arquivo para download
$fileSize = filesize($filePath);

header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Content-Transfer-Encoding: binary');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Limpar buffer de saída
if (ob_get_level()) {
    ob_end_clean();
}

// Enviar arquivo
readfile($filePath);
exit;
?>

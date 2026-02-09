<?php
/**
 * APK Direct Link
 * 
 * Endpoint para obter link direto de download do APK
 * ou redirecionar para download direto
 * 
 * GET /api/v1/apk/direct-link.php
 * - Retorna JSON com link direto
 * 
 * GET /api/v1/apk/direct-link.php?download=1
 * - Redireciona para download direto
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

$apkDir = __DIR__;
$apkFiles = glob($apkDir . '/*.apk');

if (empty($apkFiles)) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'APK não encontrado'
    ]);
    exit;
}

// Ordenar por data de modificação (mais recente primeiro)
usort($apkFiles, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

$latestApk = $apkFiles[0];
$apkName = basename($latestApk);
$apkSize = filesize($latestApk);

// Construir URL base
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$basePath = str_replace('api/v1/apk/direct-link.php', '', $_SERVER['REQUEST_URI']);
$directUrl = $protocol . '://' . $host . $basePath . 'api/v1/apk/' . $apkName;

// Se solicitado download direto, redirecionar
if (isset($_GET['download']) && $_GET['download'] === '1') {
    header('Location: ' . $directUrl);
    exit;
}

// Retornar JSON com link direto
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => [
        'apk_name' => $apkName,
        'apk_size' => $apkSize,
        'apk_size_mb' => round($apkSize / (1024 * 1024), 2),
        'direct_link' => $directUrl,
        'download_link' => $directUrl . '?download=1',
        'timestamp' => date('Y-m-d H:i:s')
    ]
]);
?>

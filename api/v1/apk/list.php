<?php
/**
 * APK List API
 * 
 * Endpoint para listar APKs enviados e obter informações.
 * 
 * GET /api/v1/apk/list.php
 * - Retorna lista de APKs com informações
 * 
 * @version 1.0.0
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

require_once __DIR__ . '/../../../db_config.php';

try {
    $pdo = getPDOConnection();
    
    // Criar tabela se não existir
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS apk_uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            file_name VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            version VARCHAR(50) DEFAULT 'unknown',
            description TEXT,
            file_size BIGINT NOT NULL,
            download_url TEXT NOT NULL,
            direct_url TEXT NOT NULL,
            download_count INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Buscar todos os APKs
    $stmt = $pdo->prepare("SELECT * FROM apk_uploads ORDER BY id DESC LIMIT 50");
    $stmt->execute();
    $apks = $stmt->fetchAll();
    
    // Formatar dados
    $formattedApks = array_map(function($apk) {
        return [
            'id' => (int) $apk['id'],
            'file_name' => $apk['file_name'],
            'original_name' => $apk['original_name'],
            'version' => $apk['version'],
            'description' => $apk['description'],
            'file_size' => (int) $apk['file_size'],
            'file_size_formatted' => formatFileSize($apk['file_size']),
            'download_url' => $apk['download_url'],
            'direct_url' => $apk['direct_url'],
            'download_count' => (int) $apk['download_count'],
            'is_active' => (bool) $apk['is_active'],
            'uploaded_at' => $apk['uploaded_at'],
        ];
    }, $apks);
    
    // Buscar APK ativo
    $activeApk = null;
    foreach ($formattedApks as $apk) {
        if ($apk['is_active']) {
            $activeApk = $apk;
            break;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'active' => $activeApk,
            'apks' => $formattedApks,
            'total' => count($formattedApks)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("APK List Error: " . $e->getMessage());
    
    // Fallback: listar arquivos do diretório
    $uploadDir = __DIR__ . '/../../../uploads/apk/';
    $apkFiles = glob($uploadDir . '*.apk');
    
    $fileList = array_map(function($file) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $protocol = $_SERVER['HTTP_X_FORWARDED_PROTO'];
        }
        
        $fileName = basename($file);
        return [
            'file_name' => $fileName,
            'file_size' => filesize($file),
            'file_size_formatted' => formatFileSize(filesize($file)),
            'download_url' => "{$protocol}://{$host}/api/v1/apk/download.php?file=" . urlencode($fileName),
            'uploaded_at' => date('Y-m-d H:i:s', filemtime($file)),
        ];
    }, $apkFiles);
    
    // Ordenar por data (mais recente primeiro)
    usort($fileList, function($a, $b) {
        return strcmp($b['uploaded_at'], $a['uploaded_at']);
    });
    
    echo json_encode([
        'success' => true,
        'data' => [
            'active' => !empty($fileList) ? $fileList[0] : null,
            'apks' => $fileList,
            'total' => count($fileList),
            'source' => 'filesystem'
        ]
    ]);
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
?>

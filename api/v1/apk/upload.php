<?php
/**
 * APK Upload API
 * 
 * Endpoint para upload de APK pelo painel admin.
 * Salva o arquivo e registra no banco de dados.
 * 
 * POST /api/v1/apk/upload.php
 * - Multipart form data com campo 'apk'
 * - Campo opcional 'version' (string)
 * - Campo opcional 'description' (string)
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

// Diretório de armazenamento dos APKs
$uploadDir = __DIR__ . '/../../../uploads/apk/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Verificar se o arquivo foi enviado
if (!isset($_FILES['apk']) || $_FILES['apk']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'Arquivo excede o tamanho máximo do servidor',
        UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o tamanho máximo do formulário',
        UPLOAD_ERR_PARTIAL => 'Upload incompleto',
        UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado',
        UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário não encontrado',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo',
        UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão',
    ];
    
    $errorCode = $_FILES['apk']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errorMsg = $errorMessages[$errorCode] ?? 'Erro desconhecido no upload';
    
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

$file = $_FILES['apk'];

// Validar extensão
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'apk') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Apenas arquivos .apk são permitidos']);
    exit;
}

// Validar tamanho (máximo 200MB)
$maxSize = 200 * 1024 * 1024; // 200MB
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Arquivo excede o tamanho máximo de 200MB']);
    exit;
}

// Dados adicionais
$version = $_POST['version'] ?? 'unknown';
$description = $_POST['description'] ?? '';

// Gerar nome único para o arquivo
$timestamp = date('Ymd_His');
$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
$fileName = "youngmoney_v{$version}_{$timestamp}.apk";
$filePath = $uploadDir . $fileName;

// Mover arquivo para o diretório de uploads
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Falha ao salvar o arquivo']);
    exit;
}

// Construir URL de download direto
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

// Usar X-Forwarded headers se disponível (Railway usa proxy)
if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
}
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $protocol = $_SERVER['HTTP_X_FORWARDED_PROTO'];
}

$downloadUrl = "{$protocol}://{$host}/api/v1/apk/download.php?file=" . urlencode($fileName);
$directUrl = "{$protocol}://{$host}/uploads/apk/" . urlencode($fileName);

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
    
    // Desativar APKs anteriores (apenas o mais recente fica ativo)
    $pdo->exec("UPDATE apk_uploads SET is_active = 0");
    
    // Inserir registro do novo APK
    $stmt = $pdo->prepare("
        INSERT INTO apk_uploads (file_name, original_name, version, description, file_size, download_url, direct_url, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $fileName,
        $file['name'],
        $version,
        $description,
        $file['size'],
        $downloadUrl,
        $directUrl
    ]);
    
    $apkId = $pdo->lastInsertId();
    
    // Atualizar a configuração do sistema com o link do APK
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, created_at, updated_at) 
        VALUES ('app_update_secondary_url', ?, NOW(), NOW()) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
    ");
    $stmt->execute([$downloadUrl]);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int) $apkId,
            'file_name' => $fileName,
            'original_name' => $file['name'],
            'version' => $version,
            'description' => $description,
            'file_size' => $file['size'],
            'file_size_formatted' => formatFileSize($file['size']),
            'download_url' => $downloadUrl,
            'direct_url' => $directUrl,
            'is_active' => true,
            'uploaded_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    error_log("APK Upload DB Error: " . $e->getMessage());
    
    // Mesmo se o banco falhar, o arquivo foi salvo
    echo json_encode([
        'success' => true,
        'data' => [
            'file_name' => $fileName,
            'original_name' => $file['name'],
            'version' => $version,
            'file_size' => $file['size'],
            'file_size_formatted' => formatFileSize($file['size']),
            'download_url' => $downloadUrl,
            'direct_url' => $directUrl,
            'is_active' => true,
            'uploaded_at' => date('Y-m-d H:i:s'),
            'warning' => 'Arquivo salvo, mas houve erro ao registrar no banco de dados'
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

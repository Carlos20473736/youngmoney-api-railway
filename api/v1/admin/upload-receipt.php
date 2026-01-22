<?php
/**
 * Upload Receipt Endpoint
 * POST - Faz upload de imagem de comprovante e retorna a URL pública
 * 
 * Recebe: multipart/form-data com campo 'image'
 * Retorna: URL pública da imagem
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../database.php';
require_once __DIR__ . '/../../includes/auth_helper.php';

// Diretório para salvar os comprovantes
$uploadDir = __DIR__ . '/../../uploads/receipts/';

// Criar diretório se não existir
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

try {
    // Verificar se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Método não permitido', 405);
    }
    
    // Verificar se tem arquivo
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errorCode = $_FILES['image']['error'] ?? 'NO_FILE';
        sendError('Nenhuma imagem enviada ou erro no upload. Código: ' . $errorCode, 400);
    }
    
    $file = $_FILES['image'];
    
    // Validar tipo de arquivo
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        sendError('Tipo de arquivo não permitido. Use: JPG, PNG, GIF ou WebP', 400);
    }
    
    // Validar tamanho (máximo 10MB)
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        sendError('Arquivo muito grande. Máximo: 10MB', 400);
    }
    
    // Gerar nome único para o arquivo
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (empty($extension)) {
        // Determinar extensão pelo mime type
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        $extension = $mimeToExt[$mimeType] ?? 'jpg';
    }
    
    $fileName = 'receipt_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $filePath = $uploadDir . $fileName;
    
    // Mover arquivo para o diretório de uploads
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        sendError('Erro ao salvar arquivo', 500);
    }
    
    // Gerar URL pública
    // A URL será baseada no domínio do Railway
    $baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'youngmoney-api-railway-production.up.railway.app');
    $publicUrl = $baseUrl . '/api/v1/uploads/receipts/' . $fileName;
    
    // Log do upload
    error_log("[Upload Receipt] Arquivo salvo: " . $fileName . " - URL: " . $publicUrl);
    
    sendSuccess([
        'url' => $publicUrl,
        'filename' => $fileName,
        'size' => $file['size'],
        'type' => $mimeType
    ]);
    
} catch (Exception $e) {
    error_log("upload-receipt.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

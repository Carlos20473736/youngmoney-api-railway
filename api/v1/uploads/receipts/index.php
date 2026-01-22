<?php
/**
 * Serve Receipt Images
 * GET - Retorna a imagem do comprovante
 */

// Obter o nome do arquivo da URL
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api/v1/uploads/receipts/';
$fileName = str_replace($basePath, '', parse_url($requestUri, PHP_URL_PATH));

// Sanitizar nome do arquivo
$fileName = basename($fileName);

if (empty($fileName) || $fileName === 'index.php') {
    http_response_code(404);
    echo json_encode(['error' => 'Arquivo não especificado']);
    exit;
}

// Caminho do arquivo
$filePath = __DIR__ . '/' . $fileName;

// Verificar se arquivo existe
if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Arquivo não encontrado']);
    exit;
}

// Determinar tipo MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

// Definir headers para imagem
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: public, max-age=31536000'); // Cache por 1 ano
header('Access-Control-Allow-Origin: *');

// Enviar arquivo
readfile($filePath);
exit;
?>

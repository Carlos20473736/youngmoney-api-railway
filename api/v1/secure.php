<?php
/**
 * Secure API Endpoint - Ponto de entrada para requisições criptografadas
 * 
 * Todas as requisições do app passam por aqui:
 * 1. Recebe dados criptografados com chave única do dispositivo
 * 2. Valida chave rotativa (muda a cada 5 segundos)
 * 3. Descriptografa usando chave do dispositivo
 * 4. Executa a requisição real
 * 5. Criptografa a resposta
 * 6. Retorna resposta criptografada
 * 
 * Scripts NÃO conseguem usar porque:
 * - Cada dispositivo tem chave única
 * - Chave rotativa muda a cada 5 segundos
 * - Sem a chave, não consegue criptografar/descriptografar
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Device-ID, X-Timestamp, X-Rotating-Key, X-Nonce, X-Signature');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../database.php';
require_once __DIR__ . '/../../includes/DeviceKeyValidator.php';

// Obter headers
$headers = [];
if (function_exists('getallheaders')) {
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
} else {
    foreach ($_SERVER as $key => $value) {
        if (substr($key, 0, 5) === 'HTTP_') {
            $header = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$header] = $value;
        }
    }
}

// Obter corpo da requisição
$rawBody = file_get_contents('php://input');
$requestData = json_decode($rawBody, true);

// Validar campos obrigatórios
$requiredFields = ['encrypted_data', 'device_id', 'timestamp', 'rotating_key', 'nonce', 'signature'];
foreach ($requiredFields as $field) {
    if (empty($requestData[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing field: $field", 'code' => 'MISSING_FIELD']);
        exit;
    }
}

$encryptedData = $requestData['encrypted_data'];
$deviceId = $requestData['device_id'];
$timestamp = (int) $requestData['timestamp'];
$rotatingKey = $requestData['rotating_key'];
$nonce = $requestData['nonce'];
$signature = $requestData['signature'];

// Criar validador
$validator = new DeviceKeyValidator($pdo);

// Validar requisição
$validation = $validator->validateRequest($deviceId, $rotatingKey, $timestamp, $nonce, $signature);

if (!$validation['valid']) {
    http_response_code(403);
    echo json_encode([
        'error' => $validation['message'],
        'code' => $validation['error']
    ]);
    exit;
}

$deviceKey = $validation['device_key'];

// Descriptografar dados da requisição
$decryptedData = $validator->decryptData($deviceKey, $encryptedData, $timestamp);

if ($decryptedData === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Failed to decrypt request', 'code' => 'DECRYPT_ERROR']);
    exit;
}

// Parse dos dados descriptografados
$requestInfo = json_decode($decryptedData, true);

if (!$requestInfo || !isset($requestInfo['url']) || !isset($requestInfo['method'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid decrypted data format', 'code' => 'INVALID_FORMAT']);
    exit;
}

$targetUrl = $requestInfo['url'];
$method = $requestInfo['method'];
$targetHeaders = $requestInfo['headers'] ?? [];
$targetBody = $requestInfo['body'] ?? '';

// Log da requisição (para debug)
error_log("Secure request: $method $targetUrl from device $deviceId");

// Executar a requisição real internamente
$response = executeInternalRequest($targetUrl, $method, $targetHeaders, $targetBody, $pdo);

// Criptografar resposta
$responseTimestamp = round(microtime(true) * 1000);
$encryptedResponse = $validator->encryptData($deviceKey, json_encode($response), $responseTimestamp);

// Retornar resposta criptografada
echo json_encode([
    'encrypted_response' => $encryptedResponse,
    'timestamp' => $responseTimestamp,
    'status' => 'success'
]);

/**
 * Executa uma requisição interna
 */
function executeInternalRequest($url, $method, $headers, $body, $pdo) {
    // Remover base URL se presente
    $url = preg_replace('#^https?://[^/]+#', '', $url);
    
    // Mapear URLs para arquivos PHP
    $routes = [
        '/user/profile' => '/user/profile.php',
        '/user/profile.php' => '/user/profile.php',
        '/user/pix' => '/user/pix.php',
        '/user/pix.php' => '/user/pix.php',
        '/user/withdraw' => '/user/withdraw.php',
        '/user/withdraw.php' => '/user/withdraw.php',
        '/api/v1/auth/google-login' => '/api/v1/auth/google-login.php',
        '/api/v1/auth/google-login.php' => '/api/v1/auth/google-login.php',
        '/api/v1/auth/device-login' => '/api/v1/auth/device-login.php',
        '/api/v1/auth/device-login.php' => '/api/v1/auth/device-login.php',
        '/api/v1/users' => '/api/v1/users.php',
        '/api/v1/users.php' => '/api/v1/users.php',
        '/api/v1/spin' => '/api/v1/spin.php',
        '/api/v1/spin.php' => '/api/v1/spin.php',
        '/api/v1/daily-bonus' => '/api/v1/daily-bonus.php',
        '/api/v1/daily-bonus.php' => '/api/v1/daily-bonus.php',
        '/api/v1/invite' => '/api/v1/invite.php',
        '/api/v1/invite.php' => '/api/v1/invite.php',
        '/api/v1/leaderboard' => '/api/v1/leaderboard.php',
        '/api/v1/leaderboard.php' => '/api/v1/leaderboard.php',
    ];
    
    // Encontrar arquivo correspondente
    $targetFile = null;
    foreach ($routes as $route => $file) {
        if (strpos($url, $route) === 0) {
            $targetFile = __DIR__ . '/../..' . $file;
            break;
        }
    }
    
    if (!$targetFile || !file_exists($targetFile)) {
        return ['status' => 'error', 'message' => 'Endpoint not found: ' . $url];
    }
    
    // Simular ambiente da requisição
    $_SERVER['REQUEST_METHOD'] = strtoupper($method);
    $_SERVER['REQUEST_URI'] = $url;
    
    // Configurar headers
    foreach ($headers as $key => $value) {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        $_SERVER[$serverKey] = $value;
        
        // Authorization header especial
        if (strtolower($key) === 'authorization') {
            $_SERVER['HTTP_AUTHORIZATION'] = $value;
        }
    }
    
    // Configurar body
    if ($body) {
        // Criar stream temporário com o body
        $GLOBALS['_SECURE_REQUEST_BODY'] = $body;
    }
    
    // Capturar output
    ob_start();
    
    try {
        // Incluir o arquivo PHP
        include $targetFile;
        $output = ob_get_clean();
        
        // Tentar decodificar como JSON
        $jsonResponse = json_decode($output, true);
        if ($jsonResponse !== null) {
            return $jsonResponse;
        }
        
        return ['raw_response' => $output];
        
    } catch (Exception $e) {
        ob_end_clean();
        return ['status' => 'error', 'message' => 'Internal error: ' . $e->getMessage()];
    }
}

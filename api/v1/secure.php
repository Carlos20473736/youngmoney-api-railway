<?php
/**
 * Secure API Endpoint - Ponto de entrada para requisições criptografadas
 * 
 * Todas as requisições do app passam por aqui:
 * 1. Recebe dados criptografados
 * 2. Descriptografa usando chave rotativa
 * 3. Executa a requisição real
 * 4. Criptografa a resposta
 * 5. Retorna resposta criptografada
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-ID, X-Timestamp, X-Nonce, X-Rotating-Key, X-Signature');

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

require_once __DIR__ . '/../../includes/NativeCrypto.php';
require_once __DIR__ . '/../../includes/db.php';

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

if (!$requestData) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

// Validar campos obrigatórios
$requiredFields = ['encrypted_request', 'timestamp', 'nonce', 'rotating_key', 'signature'];
foreach ($requiredFields as $field) {
    if (empty($requestData[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing field: $field"]);
        exit;
    }
}

$encryptedRequest = $requestData['encrypted_request'];
$timestamp = (int) $requestData['timestamp'];
$nonce = $requestData['nonce'];
$rotatingKey = $requestData['rotating_key'];
$signature = $requestData['signature'];

// Validar timestamp (máximo 2 minutos de diferença)
$currentTime = round(microtime(true) * 1000);
$timeDiff = abs($currentTime - $timestamp);
if ($timeDiff > 120000) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Request expired',
        'code' => 'TIMESTAMP_EXPIRED'
    ]);
    exit;
}

// Validar chave rotativa
if (!NativeCrypto::validateRotatingKey($rotatingKey, $timestamp)) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Invalid rotating key',
        'code' => 'INVALID_ROTATING_KEY'
    ]);
    exit;
}

// Descriptografar requisição
$decryptedRequest = NativeCrypto::decrypt($encryptedRequest, $timestamp);
if ($decryptedRequest === null) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Decryption failed',
        'code' => 'DECRYPTION_FAILED'
    ]);
    exit;
}

// Parse da requisição descriptografada
$request = json_decode($decryptedRequest, true);
if (!$request) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid decrypted request format']);
    exit;
}

// Extrair dados da requisição
$endpoint = $request['endpoint'] ?? '';
$method = strtoupper($request['method'] ?? 'GET');
$requestHeaders = $request['headers'] ?? [];
$body = $request['body'] ?? '';

error_log("[SecureAPI] Requisição descriptografada - Endpoint: $endpoint, Method: $method");

// Executar a requisição real internamente
$response = executeInternalRequest($endpoint, $method, $requestHeaders, $body, $conn);

// Criptografar resposta
$responseTimestamp = round(microtime(true) * 1000);
$encryptedResponse = NativeCrypto::encrypt(json_encode($response), $responseTimestamp);

// Retornar resposta criptografada
echo json_encode([
    'encrypted_response' => $encryptedResponse,
    'timestamp' => $responseTimestamp,
    'rotating_key' => NativeCrypto::generateRotatingKey($responseTimestamp)
]);

/**
 * Executa uma requisição interna
 */
function executeInternalRequest(string $endpoint, string $method, array $headers, string $body, $conn): array {
    // Mapear endpoints para arquivos PHP
    $endpointMap = [
        '/user/profile' => '/user/profile.php',
        '/user/profile.php' => '/user/profile.php',
        '/user/update' => '/user/update.php',
        '/user/pix' => '/user/pix.php',
        '/user/pix.php' => '/user/pix.php',
        '/api/v1/auth/google-login' => '/api/v1/auth/google-login.php',
        '/api/v1/auth/google-login.php' => '/api/v1/auth/google-login.php',
        '/api/v1/users' => '/api/v1/users.php',
        '/api/v1/users.php' => '/api/v1/users.php',
        '/api/v1/tasks' => '/api/v1/tasks.php',
        '/api/v1/tasks.php' => '/api/v1/tasks.php',
        '/api/v1/spin' => '/api/v1/spin.php',
        '/api/v1/spin.php' => '/api/v1/spin.php',
        '/api/v1/rewards' => '/api/v1/rewards.php',
        '/api/v1/rewards.php' => '/api/v1/rewards.php',
        '/api/v1/withdraw' => '/api/v1/withdraw.php',
        '/api/v1/withdraw.php' => '/api/v1/withdraw.php',
        '/api/v1/invite' => '/api/v1/invite.php',
        '/api/v1/invite.php' => '/api/v1/invite.php',
    ];
    
    // Encontrar arquivo correspondente
    $filePath = null;
    foreach ($endpointMap as $pattern => $file) {
        if (strpos($endpoint, $pattern) !== false) {
            $filePath = __DIR__ . '/../../' . ltrim($file, '/');
            break;
        }
    }
    
    if (!$filePath || !file_exists($filePath)) {
        return [
            'status' => 'error',
            'message' => 'Endpoint not found',
            'endpoint' => $endpoint
        ];
    }
    
    // Simular ambiente da requisição
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $endpoint;
    
    // Configurar headers (especialmente Authorization)
    foreach ($headers as $key => $value) {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        $_SERVER[$serverKey] = $value;
    }
    
    // Configurar body
    if (!empty($body)) {
        // Criar stream temporário para php://input
        $GLOBALS['_SECURE_API_BODY'] = $body;
    }
    
    // Capturar output
    ob_start();
    
    try {
        // Incluir o arquivo do endpoint
        include $filePath;
    } catch (Exception $e) {
        ob_end_clean();
        return [
            'status' => 'error',
            'message' => 'Internal error: ' . $e->getMessage()
        ];
    }
    
    $output = ob_get_clean();
    
    // Tentar decodificar como JSON
    $decoded = json_decode($output, true);
    if ($decoded !== null) {
        return $decoded;
    }
    
    // Retornar como string se não for JSON
    return [
        'status' => 'success',
        'raw_response' => $output
    ];
}
?>

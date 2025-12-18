<?php
/**
 * Secure API Endpoint - Ponto de entrada para requisições criptografadas
 * Dados de segurança são enviados nos HEADERS, dados criptografados no BODY
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Device-ID, X-Timestamp, X-Rotating-Key, X-Nonce, X-Signature');

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

try {
    $conn = getDbConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Função para obter header de múltiplas fontes
function getHeader($name) {
    // Tentar $_SERVER primeiro (formato HTTP_X_HEADER_NAME)
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (!empty($_SERVER[$serverKey])) {
        return $_SERVER[$serverKey];
    }
    
    // Tentar getallheaders()
    $headers = getallheaders();
    if ($headers) {
        // Case-insensitive search
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
    }
    
    return null;
}

// Obter dados de segurança dos HEADERS
$deviceId = getHeader('X-Device-ID');
$timestamp = getHeader('X-Timestamp');
$rotatingKey = getHeader('X-Rotating-Key');
$nonce = getHeader('X-Nonce');
$signature = getHeader('X-Signature');

// Validar headers obrigatórios
$missingHeaders = [];
if (empty($deviceId)) $missingHeaders[] = 'X-Device-ID';
if (empty($timestamp)) $missingHeaders[] = 'X-Timestamp';
if (empty($rotatingKey)) $missingHeaders[] = 'X-Rotating-Key';
if (empty($nonce)) $missingHeaders[] = 'X-Nonce';
if (empty($signature)) $missingHeaders[] = 'X-Signature';

if (!empty($missingHeaders)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing security headers',
        'code' => 'MISSING_HEADERS',
        'missing' => $missingHeaders
    ]);
    exit;
}

$timestamp = (int) $timestamp;

// Obter dados criptografados do BODY
$rawBody = file_get_contents('php://input');
$requestData = json_decode($rawBody, true);

// O body deve conter apenas encrypted_data
$encryptedData = null;
if (!empty($requestData['encrypted_data'])) {
    $encryptedData = $requestData['encrypted_data'];
} else if (!empty($rawBody) && strpos($rawBody, '{') === false) {
    // Se o body não é JSON, assume que é o dado criptografado direto
    $encryptedData = $rawBody;
}

if (empty($encryptedData)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing encrypted_data in body', 'code' => 'MISSING_DATA']);
    exit;
}

// Criar validador
$validator = new DeviceKeyValidator($conn);

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
$innerRequest = json_decode($decryptedData, true);

if (!$innerRequest || empty($innerRequest['endpoint'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid decrypted data format', 'code' => 'INVALID_FORMAT']);
    exit;
}

$endpoint = $innerRequest['endpoint'];
$method = $innerRequest['method'] ?? 'GET';
$headers = $innerRequest['headers'] ?? [];
$body = $innerRequest['body'] ?? null;

// Mapear endpoints para arquivos
$endpointMap = [
    // Auth
    '/api/v1/auth/google-login.php' => __DIR__ . '/auth/google-login.php',
    '/api/v1/auth/device-login.php' => __DIR__ . '/auth/device-login.php',
    '/auth/google-login.php' => __DIR__ . '/auth/google-login.php',
    '/auth/device-login.php' => __DIR__ . '/auth/device-login.php',
    
    // User
    '/user/profile.php' => __DIR__ . '/../../user/profile.php',
    '/user/balance.php' => __DIR__ . '/../../user/balance.php',
    '/user/greeting.php' => __DIR__ . '/../../user/greeting.php',
    '/api/v1/users.php' => __DIR__ . '/users.php',
    
    // Ranking
    '/ranking/list.php' => __DIR__ . '/../../ranking/list.php',
    '/ranking/add_points.php' => __DIR__ . '/../../ranking/add_points.php',
    '/ranking/user_position.php' => __DIR__ . '/../../ranking/user_position.php',
    '/api/v1/ranking/list.php' => __DIR__ . '/../../ranking/list.php',
    
    // Notifications
    '/notifications/list.php' => __DIR__ . '/../../notifications/list.php',
    '/notifications/mark_read.php' => __DIR__ . '/../../notifications/mark_read.php',
    
    // Withdraw
    '/withdraw/request.php' => __DIR__ . '/../../withdraw/request.php',
    '/withdraw/history.php' => __DIR__ . '/../../withdraw/history.php',
    '/withdraw/recent.php' => __DIR__ . '/../../withdraw/recent.php',
    '/api/v1/withdraw/request.php' => __DIR__ . '/../../withdraw/request.php',
    
    // Monetag
    '/monetag/reward.php' => __DIR__ . '/../../monetag/reward.php',
    '/monetag/callback.php' => __DIR__ . '/../../monetag/callback.php',
    '/monetag/status.php' => __DIR__ . '/../../monetag/status.php',
    '/api/v1/monetag/reward.php' => __DIR__ . '/../../monetag/reward.php',
    
    // Check-in
    '/api/v1/checkin.php' => __DIR__ . '/checkin.php',
    '/checkin.php' => __DIR__ . '/checkin.php',
    
    // Settings
    '/settings/app.php' => __DIR__ . '/../../settings/app.php',
    '/settings/pix.php' => __DIR__ . '/../../settings/pix.php',
    
    // History
    '/history/points.php' => __DIR__ . '/../../history/points.php',
    
    // Payments
    '/api/v1/payments/pix.php' => __DIR__ . '/payments/pix.php',
    '/api/v1/payments/status.php' => __DIR__ . '/payments/status.php',
    
    // Invite
    '/api/v1/invite/validate.php' => __DIR__ . '/invite/validate.php',
    '/api/v1/invite/apply.php' => __DIR__ . '/invite/apply.php',
    
    // User PIX
    '/user/pix/save.php' => __DIR__ . '/../../user/pix/save.php',
    '/user/pix/get.php' => __DIR__ . '/../../user/pix/get.php',
];

// Encontrar arquivo do endpoint
$targetFile = null;
foreach ($endpointMap as $pattern => $file) {
    if ($endpoint === $pattern || strpos($endpoint, $pattern) !== false) {
        $targetFile = $file;
        break;
    }
}

if (!$targetFile || !file_exists($targetFile)) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => "Endpoint not found: $endpoint"]);
    exit;
}

// Configurar ambiente para o endpoint interno
$_SERVER['REQUEST_METHOD'] = $method;
$_SERVER['REQUEST_URI'] = $endpoint;

// Passar headers para o endpoint interno
foreach ($headers as $key => $value) {
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
    $_SERVER[$serverKey] = $value;
}

// Passar body para o endpoint interno
if ($body) {
    $GLOBALS['_SECURE_REQUEST_BODY'] = is_string($body) ? $body : json_encode($body);
}

// Capturar output do endpoint
ob_start();
try {
    include $targetFile;
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Internal error: ' . $e->getMessage()]);
    exit;
}
$response = ob_get_clean();

// Criptografar resposta
$encryptedResponse = $validator->encryptData($deviceKey, $response, $timestamp);

if ($encryptedResponse === null) {
    // Se falhar criptografia, retornar resposta sem criptografia (fallback)
    echo $response;
    exit;
}

// Retornar resposta criptografada
echo json_encode([
    'encrypted_response' => $encryptedResponse,
    'timestamp' => round(microtime(true) * 1000)
]);

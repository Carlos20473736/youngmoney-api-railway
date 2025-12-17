<?php
/**
 * Túnel Criptografado - Endpoint único para requisições criptografadas
 * 
 * TODAS as requisições do app passam por este endpoint.
 * O payload é totalmente criptografado com AES-256-CBC usando
 * uma chave rotativa que muda a cada 5 segundos.
 * 
 * Scripts NÃO conseguem usar este endpoint porque:
 * 1. Não têm acesso à chave rotativa (está em código nativo C++)
 * 2. A chave muda a cada 5 segundos
 * 3. Sem a chave correta, é impossível criptografar/descriptografar
 * 
 * @version 1.0.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Encrypted-Tunnel, X-Key-Window');

// Preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Apenas POST é permitido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Verificar header do túnel
$headers = array_change_key_case(getallheaders(), CASE_LOWER);
if (empty($headers['x-encrypted-tunnel']) || $headers['x-encrypted-tunnel'] !== 'true') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid tunnel request']);
    exit;
}

// Obter payload
$input = file_get_contents('php://input');
$payload = json_decode($input, true);

if (!$payload) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

// Validar campos obrigatórios
$requiredFields = ['encrypted_data', 'timestamp', 'key_window', 'rotating_key', 'signature', 'device_fingerprint', 'app_hash', 'nonce'];
foreach ($requiredFields as $field) {
    if (empty($payload[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing field: $field"]);
        exit;
    }
}

// ============================================
// VALIDAÇÃO DE SEGURANÇA
// ============================================

// Chave base (DEVE SER IDÊNTICA À DO CÓDIGO C++)
define('BASE_KEY', 'YM_ROTATING_KEY_2025_V3RY_S3CUR3');
define('ROTATION_SALT', 'youngmoney');
define('WINDOW_TOLERANCE', 2); // 2 janelas = 10 segundos de tolerância

/**
 * Gera a chave rotativa para uma janela específica
 * DEVE SER IDÊNTICO AO ALGORITMO DO CÓDIGO C++
 */
function generateRotatingKey($window) {
    $combined = BASE_KEY . ROTATION_SALT . $window;
    return hash('sha256', $combined);
}

/**
 * Deriva a chave AES-256 da chave rotativa
 * DEVE SER IDÊNTICO AO ALGORITMO DO CÓDIGO C++
 */
function deriveAESKey($rotatingKey) {
    $keyHash = hash('sha256', $rotatingKey . 'AES_KEY_DERIVATION');
    return hex2bin($keyHash);
}

/**
 * Descriptografa dados com AES-256-CBC
 */
function decryptAES256($ciphertext, $timestamp) {
    // Calcular janela
    $window = intval($timestamp / 5000);
    
    // Gerar chave rotativa
    $rotatingKey = generateRotatingKey($window);
    
    // Derivar chave AES
    $aesKey = deriveAESKey($rotatingKey);
    
    // Decodificar Base64
    $data = base64_decode($ciphertext);
    
    if (strlen($data) < 32) {
        return false;
    }
    
    // Extrair IV (primeiros 16 bytes)
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    
    // Descriptografar
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
    
    return $decrypted;
}

/**
 * Criptografa dados com AES-256-CBC
 */
function encryptAES256($plaintext, $timestamp) {
    // Calcular janela
    $window = intval($timestamp / 5000);
    
    // Gerar chave rotativa
    $rotatingKey = generateRotatingKey($window);
    
    // Derivar chave AES
    $aesKey = deriveAESKey($rotatingKey);
    
    // Gerar IV aleatório
    $iv = random_bytes(16);
    
    // Criptografar
    $encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
    
    // Combinar IV + ciphertext
    $result = $iv . $encrypted;
    
    return base64_encode($result);
}

// Validar timestamp (não pode ser muito antigo)
$currentTime = round(microtime(true) * 1000);
$timeDiff = abs($currentTime - $payload['timestamp']);
if ($timeDiff > 120000) { // 2 minutos
    http_response_code(403);
    echo json_encode(['error' => 'TIMESTAMP_EXPIRED', 'server_time' => $currentTime]);
    exit;
}

// Validar janela da chave
$serverWindow = intval($currentTime / 5000);
$clientWindow = intval($payload['key_window']);
$windowDiff = abs($serverWindow - $clientWindow);

if ($windowDiff > WINDOW_TOLERANCE) {
    http_response_code(403);
    echo json_encode([
        'error' => 'KEY_WINDOW_EXPIRED',
        'server_window' => $serverWindow,
        'client_window' => $clientWindow
    ]);
    exit;
}

// Validar chave rotativa
$expectedKey = generateRotatingKey($clientWindow);
$keyValid = false;

// Verificar janela atual e adjacentes
for ($i = -WINDOW_TOLERANCE; $i <= WINDOW_TOLERANCE; $i++) {
    $testKey = generateRotatingKey($clientWindow + $i);
    if (hash_equals($testKey, $payload['rotating_key'])) {
        $keyValid = true;
        break;
    }
}

if (!$keyValid) {
    http_response_code(403);
    echo json_encode(['error' => 'INVALID_ROTATING_KEY']);
    exit;
}

// ============================================
// DESCRIPTOGRAFAR REQUISIÇÃO
// ============================================

$decryptedData = decryptAES256($payload['encrypted_data'], $payload['timestamp']);

if ($decryptedData === false) {
    http_response_code(403);
    echo json_encode(['error' => 'DECRYPTION_FAILED']);
    exit;
}

// Parse do JSON descriptografado
$requestData = json_decode($decryptedData, true);

if (!$requestData) {
    http_response_code(400);
    echo json_encode(['error' => 'INVALID_DECRYPTED_DATA']);
    exit;
}

error_log("[Tunnel] Request descriptografada: " . $requestData['method'] . " " . $requestData['url']);

// ============================================
// EXECUTAR REQUISIÇÃO INTERNA
// ============================================

$url = $requestData['url'];
$method = $requestData['method'];
$headers = $requestData['headers'] ?? [];
$body = $requestData['body'] ?? '';

// Verificar se é uma URL interna
$baseUrl = 'https://youngmoney-api-railway-production.up.railway.app';
if (strpos($url, $baseUrl) !== 0 && strpos($url, '/') !== 0) {
    http_response_code(400);
    echo json_encode(['error' => 'INVALID_URL']);
    exit;
}

// Converter URL relativa para absoluta
if (strpos($url, '/') === 0) {
    $url = $baseUrl . $url;
}

// Preparar headers para a requisição interna
$curlHeaders = [];
foreach ($headers as $key => $value) {
    $curlHeaders[] = "$key: $value";
}
$curlHeaders[] = "X-Tunnel-Request: true";
$curlHeaders[] = "X-Device-Fingerprint: " . $payload['device_fingerprint'];
$curlHeaders[] = "X-App-Hash: " . $payload['app_hash'];

// Executar requisição via cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

switch (strtoupper($method)) {
    case 'POST':
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        break;
    case 'PUT':
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        break;
    case 'DELETE':
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        break;
    case 'GET':
    default:
        // GET é o padrão
        break;
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    error_log("[Tunnel] Erro cURL: $error");
    http_response_code(500);
    echo json_encode(['error' => 'INTERNAL_REQUEST_FAILED', 'details' => $error]);
    exit;
}

// ============================================
// CRIPTOGRAFAR RESPOSTA
// ============================================

$responseTimestamp = round(microtime(true) * 1000);
$encryptedResponse = encryptAES256($response, $responseTimestamp);

// Retornar resposta criptografada
echo json_encode([
    'encrypted_response' => $encryptedResponse,
    'timestamp' => $responseTimestamp,
    'http_code' => $httpCode
]);

error_log("[Tunnel] Resposta enviada - HTTP $httpCode");
?>

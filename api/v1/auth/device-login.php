<?php
/**


 * Login Endpoint - Aceita Google Token (JSON Puro - SEM CRIPTOGRAFIA)
 * 
 * Este endpoint redireciona para google-login.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 1. LER RAW INPUT
    $rawInput = file_get_contents('php://input');
    error_log("device-login.php - Raw input: " . substr($rawInput, 0, 200) . "...");
    
    if (empty($rawInput)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Corpo da requisição vazio'
        ]);
        exit;
    }
    
    // 2. DECODIFICAR JSON
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        error_log("device-login.php - Failed to decode JSON");
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'JSON inválido'
        ]);
        exit;
    }
    
    error_log("device-login.php - Decoded data: " . json_encode($data));
    
    // 3. EXTRAIR google_token do campo 'data' se estiver no formato criptografado
    if (isset($data['encrypted']) && isset($data['data'])) {
        // O app está enviando no formato criptografado, mas vamos ignorar a criptografia
        // e pedir para enviar JSON puro
        error_log("device-login.php - Received encrypted format, but encryption is disabled");
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Criptografia desabilitada. Envie JSON puro com google_token diretamente.'
        ]);
        exit;
    }
    
    // 4. VALIDAR DADOS
    if (!isset($data['google_token']) || empty($data['google_token'])) {
        error_log("device-login.php - ERROR: google_token is missing or empty");
        error_log("device-login.php - Available keys: " . implode(', ', array_keys($data)));
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Google token é obrigatório'
        ]);
        exit;
    }
    
    error_log("device-login.php - google_token received: " . substr($data['google_token'], 0, 50) . "...");
    
    // 5. REDIRECIONAR PARA GOOGLE-LOGIN
    error_log("device-login.php - Redirecting to google-login.php");
    
    // Passar dados via $_POST para o google-login.php
    $_POST = $data;
    
    include __DIR__ . '/google-login.php';
    
} catch (Exception $e) {
    error_log("device-login.php - Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>

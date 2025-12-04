<?php
/**
 * Security Challenge Endpoint
 * Gera desafio criptográfico para o cliente
 * 
 * Método: POST
 * Body: { "device_id": "uuid" }
 * Response: { "challenge": "hex", "expires_at": timestamp, "difficulty": 4 }
 */

header("Content-Type: application/json");
require_once __DIR__ . '/../../../database.php';
require_once __DIR__ . '/../../../includes/UltraSecuritySystem.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Obter device_id do body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['device_id']) || empty($input['device_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'device_id is required']);
        exit;
    }
    
    $deviceId = $input['device_id'];
    
    // Validar formato do device_id (UUID)
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $deviceId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid device_id format']);
        exit;
    }
    
    // Gerar desafio
    $conn = getDbConnection();
    $challengeData = generateSecurityChallenge($conn, $deviceId);
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $challengeData
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>

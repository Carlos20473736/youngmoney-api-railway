<?php
/**
 * Device Register - Registra a chave secreta do dispositivo
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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

require_once __DIR__ . '/../../../database.php';

try {
    $conn = getDbConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Obter dados da requisição
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

// Validar campos obrigatórios
$requiredFields = ['device_id', 'device_key', 'device_fingerprint', 'app_hash'];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing field: $field"]);
        exit;
    }
}

$deviceId = $conn->real_escape_string($data['device_id']);
$deviceKey = $conn->real_escape_string($data['device_key']);
$deviceFingerprint = $conn->real_escape_string($data['device_fingerprint']);
$appHash = $conn->real_escape_string($data['app_hash']);
$deviceInfo = isset($data['device_info']) ? $conn->real_escape_string(json_encode($data['device_info'])) : '{}';

// Verificar se dispositivo já existe
$result = $conn->query("SELECT id, device_key, created_at FROM device_keys WHERE device_id = '$deviceId'");

if ($result && $result->num_rows > 0) {
    $existing = $result->fetch_assoc();
    
    if ($existing['device_key'] === $data['device_key']) {
        // Atualizar último acesso
        $conn->query("UPDATE device_keys SET last_seen = NOW(), request_count = request_count + 1 WHERE device_id = '$deviceId'");
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Device already registered',
            'device_id' => $data['device_id'],
            'registered_at' => $existing['created_at'],
            'key_valid' => true
        ]);
    } else {
        // Atualizar a chave (permite reinstalação)
        $conn->query("
            UPDATE device_keys 
            SET device_key = '$deviceKey', 
                device_fingerprint = '$deviceFingerprint',
                app_hash = '$appHash',
                device_info = '$deviceInfo',
                last_seen = NOW(),
                key_updated_at = NOW()
            WHERE device_id = '$deviceId'
        ");
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Device key updated',
            'device_id' => $data['device_id'],
            'key_valid' => true
        ]);
    }
} else {
    // Novo dispositivo - registrar
    $insertResult = $conn->query("
        INSERT INTO device_keys 
        (device_id, device_key, device_fingerprint, app_hash, device_info, created_at, last_seen) 
        VALUES ('$deviceId', '$deviceKey', '$deviceFingerprint', '$appHash', '$deviceInfo', NOW(), NOW())
    ");
    
    if ($insertResult) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Device registered successfully',
            'device_id' => $data['device_id'],
            'key_valid' => true
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to register device: ' . $conn->error]);
    }
}

$conn->close();

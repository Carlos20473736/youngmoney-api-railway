<?php
/**
 * Device Register - Registra a chave secreta do dispositivo
 * 
 * Cada dispositivo gera uma chave única na primeira execução.
 * Esta chave é sincronizada com o backend para permitir criptografia E2E.
 * 
 * A chave é armazenada de forma segura no banco de dados e usada para:
 * - Validar requisições criptografadas
 * - Gerar chaves rotativas (muda a cada 5 segundos)
 * - Identificar dispositivos únicos
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

$deviceId = $data['device_id'];
$deviceKey = $data['device_key'];
$deviceFingerprint = $data['device_fingerprint'];
$appHash = $data['app_hash'];
$deviceInfo = $data['device_info'] ?? [];

try {
    // Verificar se dispositivo já existe
    $stmt = $pdo->prepare("SELECT id, device_key, created_at FROM device_keys WHERE device_id = ?");
    $stmt->execute([$deviceId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Dispositivo já registrado - verificar se a chave é a mesma
        if ($existing['device_key'] === $deviceKey) {
            // Atualizar último acesso
            $stmt = $pdo->prepare("UPDATE device_keys SET last_seen = NOW(), request_count = request_count + 1 WHERE device_id = ?");
            $stmt->execute([$deviceId]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Device already registered',
                'device_id' => $deviceId,
                'registered_at' => $existing['created_at'],
                'key_valid' => true
            ]);
        } else {
            // Chave diferente - possível tentativa de fraude ou reinstalação
            // Atualizar a chave (permite reinstalação)
            $stmt = $pdo->prepare("
                UPDATE device_keys 
                SET device_key = ?, 
                    device_fingerprint = ?,
                    app_hash = ?,
                    device_info = ?,
                    last_seen = NOW(),
                    key_updated_at = NOW()
                WHERE device_id = ?
            ");
            $stmt->execute([
                $deviceKey,
                $deviceFingerprint,
                $appHash,
                json_encode($deviceInfo),
                $deviceId
            ]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Device key updated',
                'device_id' => $deviceId,
                'key_valid' => true
            ]);
        }
    } else {
        // Novo dispositivo - registrar
        $stmt = $pdo->prepare("
            INSERT INTO device_keys 
            (device_id, device_key, device_fingerprint, app_hash, device_info, created_at, last_seen) 
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $deviceId,
            $deviceKey,
            $deviceFingerprint,
            $appHash,
            json_encode($deviceInfo)
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Device registered successfully',
            'device_id' => $deviceId,
            'key_valid' => true
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Device register error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}

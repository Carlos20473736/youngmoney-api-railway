<?php
/**
 * Security Unblock Endpoint
 * Permite admin desbloquear IP ou Device
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../../cors.php';
require_once __DIR__ . '/../../../database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// TODO: Verificar se é admin

$input = json_decode(file_get_contents('php://input'), true);

$type = $input['type'] ?? null; // 'ip' ou 'device'
$value = $input['value'] ?? null;

if (!$type || !$value) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'type and value are required']);
    exit;
}

try {
    $conn = getDbConnection();
    
    if ($type === 'ip') {
        $stmt = $conn->prepare("DELETE FROM security_blocked_ips WHERE ip_address = ?");
        $stmt->execute([$value]);
        $affected = $stmt->rowCount();
    } elseif ($type === 'device') {
        $stmt = $conn->prepare("DELETE FROM security_blocked_devices WHERE device_id = ?");
        $stmt->execute([$value]);
        $affected = $stmt->rowCount();
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid type. Use "ip" or "device"']);
        exit;
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => $affected > 0 ? 'Unblocked successfully' : 'Not found in blocklist',
        'affected' => $affected
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}

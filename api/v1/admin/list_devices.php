<?php
/**
 * Endpoint Temporário: Listar dispositivos vinculados
 * 
 * ATENÇÃO: Remover após uso!
 */

header('Content-Type: application/json');

$key = $_GET['key'] ?? '';
if ($key !== 'YOUNGMONEY_LIST_2024') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

require_once __DIR__ . '/../../../database.php';

try {
    $conn = getDbConnection();
    
    $res = $conn->query("SELECT id, device_id, user_id, is_active, created_at FROM device_bindings WHERE is_active = 1 ORDER BY id");
    $devices = [];
    
    while ($row = $res->fetch_assoc()) {
        $devices[] = [
            'id' => $row['id'],
            'device_id' => $row['device_id'],  // Completo
            'device_id_length' => strlen($row['device_id']),
            'user_id' => $row['user_id'],
            'is_active' => $row['is_active'],
            'created_at' => $row['created_at']
        ];
    }
    
    $conn->close();
    
    echo json_encode(['success' => true, 'devices' => $devices], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

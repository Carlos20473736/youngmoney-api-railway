<?php
/**
 * Endpoint Temporário: Listar dispositivos vinculados e verificar usuários
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
    
    // Listar dispositivos ativos
    $res = $conn->query("SELECT id, device_id, user_id, is_active, created_at FROM device_bindings WHERE is_active = 1 ORDER BY id");
    $devices = [];
    
    while ($row = $res->fetch_assoc()) {
        $devices[] = [
            'id' => $row['id'],
            'device_id' => $row['device_id'],
            'device_id_length' => strlen($row['device_id']),
            'user_id' => $row['user_id'],
            'is_active' => $row['is_active'],
            'created_at' => $row['created_at']
        ];
    }
    
    // Verificar se os usuários existem na tabela users
    $userIds = array_unique(array_column($devices, 'user_id'));
    $usersExist = [];
    
    foreach ($userIds as $userId) {
        $stmt = $conn->prepare("SELECT id, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        $usersExist[$userId] = $user ? ['exists' => true, 'email' => $user['email']] : ['exists' => false, 'email' => null];
    }
    
    // Testar a query do check.php diretamente
    $testDeviceId = 'b828a4526614c5ec8f56a74015e7e4c9aaa0dc5741c2fd6549bd355a6879fa1e';
    
    // Query original do check.php
    $stmt = $conn->prepare("
        SELECT 
            db.id,
            db.user_id,
            db.device_id,
            db.created_at,
            u.email,
            u.name
        FROM device_bindings db
        INNER JOIN users u ON db.user_id = u.id
        WHERE db.device_id = ?
        AND db.is_active = 1
        LIMIT 1
    ");
    
    $stmt->bind_param("s", $testDeviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    $checkResult = $result->fetch_assoc();
    $stmt->close();
    
    // Query sem JOIN para comparar
    $stmt = $conn->prepare("
        SELECT * FROM device_bindings 
        WHERE device_id = ? AND is_active = 1
    ");
    $stmt->bind_param("s", $testDeviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    $directResult = $result->fetch_assoc();
    $stmt->close();
    
    $conn->close();
    
    echo json_encode([
        'success' => true, 
        'devices' => $devices,
        'users_exist' => $usersExist,
        'test_device_id' => $testDeviceId,
        'check_query_result' => $checkResult,
        'direct_query_result' => $directResult
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

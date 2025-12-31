<?php
/**
 * Script para verificar e atualizar emails no banco
 * 
 * Endpoint: GET /api/v1/admin/fix_emails.php?key=YOUNGMONEY_FIX_2024
 */

header('Content-Type: application/json');

$key = $_GET['key'] ?? '';
if ($key !== 'YOUNGMONEY_FIX_2024') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

require_once __DIR__ . '/../../../database.php';

$result = ['success' => false];

try {
    $conn = getDbConnection();
    
    // Verificar registros de device_bindings
    $res = $conn->query("SELECT id, user_id, email, device_id, is_active FROM device_bindings ORDER BY id");
    $bindings = [];
    while ($row = $res->fetch_assoc()) {
        $bindings[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'email' => $row['email'],
            'device_id_prefix' => substr($row['device_id'], 0, 16) . '...',
            'is_active' => $row['is_active']
        ];
    }
    $result['device_bindings'] = $bindings;
    
    // Se foi passado email e id, atualizar
    $emailToSet = $_GET['email'] ?? null;
    $idToUpdate = $_GET['id'] ?? null;
    
    if ($emailToSet && $idToUpdate) {
        $stmt = $conn->prepare("UPDATE device_bindings SET email = ? WHERE id = ?");
        $stmt->bind_param("si", $emailToSet, $idToUpdate);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        $result['updated'] = $affected;
        $result['message'] = "Atualizado registro $idToUpdate com email $emailToSet";
    }
    
    $conn->close();
    $result['success'] = true;
    
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);

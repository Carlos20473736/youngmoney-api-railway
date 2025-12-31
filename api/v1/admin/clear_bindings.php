<?php
/**
 * Script para limpar registros antigos de device_bindings
 * 
 * Endpoint: GET /api/v1/admin/clear_bindings.php?key=YOUNGMONEY_CLEAR_2024&confirm=yes
 */

header('Content-Type: application/json');

$key = $_GET['key'] ?? '';
if ($key !== 'YOUNGMONEY_CLEAR_2024') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

$confirm = $_GET['confirm'] ?? '';

require_once __DIR__ . '/../../../database.php';

$result = ['success' => false];

try {
    $conn = getDbConnection();
    
    // Mostrar registros atuais
    $res = $conn->query("SELECT id, user_id, email, device_id, is_active FROM device_bindings ORDER BY id");
    $before = [];
    while ($row = $res->fetch_assoc()) {
        $before[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'email' => $row['email'],
            'device_id_prefix' => substr($row['device_id'], 0, 16) . '...',
            'is_active' => $row['is_active']
        ];
    }
    $result['before'] = $before;
    $result['total_before'] = count($before);
    
    if ($confirm === 'yes') {
        // Limpar todos os registros
        $conn->query("DELETE FROM device_bindings");
        $deleted = $conn->affected_rows;
        
        // Também limpar device_access_logs
        $conn->query("DELETE FROM device_access_logs");
        
        $result['deleted'] = $deleted;
        $result['message'] = "Todos os $deleted registros foram removidos. Agora quando os usuários fizerem login, o email será salvo corretamente.";
        
        // Mostrar registros após
        $res = $conn->query("SELECT COUNT(*) as cnt FROM device_bindings");
        $result['total_after'] = $res->fetch_assoc()['cnt'];
    } else {
        $result['message'] = "Adicione &confirm=yes para confirmar a limpeza";
    }
    
    $conn->close();
    $result['success'] = true;
    
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);

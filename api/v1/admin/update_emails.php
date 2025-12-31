<?php
/**
 * Script para atualizar emails nos registros antigos de device_bindings
 * 
 * Endpoint: GET /api/v1/admin/update_emails.php?key=YOUNGMONEY_UPDATE_2024
 */

header('Content-Type: application/json');

$key = $_GET['key'] ?? '';
if ($key !== 'YOUNGMONEY_UPDATE_2024') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

// Email para atualizar (pode ser passado como parâmetro)
$emailToSet = $_GET['email'] ?? null;
$userIdToUpdate = $_GET['user_id'] ?? null;

require_once __DIR__ . '/../../../database.php';

$result = ['success' => false, 'steps' => []];

try {
    $conn = getDbConnection();
    $result['steps'][] = 'Conectado ao banco';
    
    // Listar registros sem email
    $res = $conn->query("SELECT id, user_id, device_id, email, is_active FROM device_bindings WHERE email IS NULL OR email = ''");
    $withoutEmail = [];
    while ($row = $res->fetch_assoc()) {
        $withoutEmail[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'device_id_prefix' => substr($row['device_id'], 0, 16) . '...',
            'is_active' => $row['is_active']
        ];
    }
    $result['records_without_email'] = $withoutEmail;
    $result['steps'][] = 'Encontrados ' . count($withoutEmail) . ' registros sem email';
    
    // Se foi passado email e user_id, atualizar
    if ($emailToSet && $userIdToUpdate) {
        $stmt = $conn->prepare("UPDATE device_bindings SET email = ? WHERE user_id = ?");
        $stmt->bind_param("si", $emailToSet, $userIdToUpdate);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        $result['updated'] = $affected;
        $result['steps'][] = "Atualizados $affected registros do user_id $userIdToUpdate com email $emailToSet";
    }
    
    // Listar todos os registros após atualização
    $res = $conn->query("SELECT id, user_id, device_id, email, is_active FROM device_bindings ORDER BY id");
    $allRecords = [];
    while ($row = $res->fetch_assoc()) {
        $allRecords[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'device_id_prefix' => substr($row['device_id'], 0, 16) . '...',
            'email' => $row['email'],
            'is_active' => $row['is_active']
        ];
    }
    $result['all_records'] = $allRecords;
    
    $conn->close();
    $result['success'] = true;
    
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);

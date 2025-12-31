<?php
/**
 * Script para adicionar coluna email na tabela device_bindings
 * 
 * Endpoint: GET /api/v1/admin/add_email_column.php?key=YOUNGMONEY_MIGRATE_2024
 */

header('Content-Type: application/json');

$key = $_GET['key'] ?? '';
if ($key !== 'YOUNGMONEY_MIGRATE_2024') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

require_once __DIR__ . '/../../../database.php';

$result = ['success' => false, 'steps' => []];

try {
    $conn = getDbConnection();
    $result['steps'][] = 'Conectado ao banco';
    
    // Verificar se a coluna já existe
    $checkColumn = $conn->query("SHOW COLUMNS FROM device_bindings LIKE 'email'");
    
    if ($checkColumn->num_rows > 0) {
        $result['steps'][] = 'Coluna email já existe';
    } else {
        // Adicionar coluna email
        $conn->query("ALTER TABLE device_bindings ADD COLUMN email VARCHAR(255) NULL AFTER user_id");
        $result['steps'][] = 'Coluna email adicionada com sucesso';
    }
    
    // Verificar estrutura atual
    $columns = $conn->query("SHOW COLUMNS FROM device_bindings");
    $columnList = [];
    while ($col = $columns->fetch_assoc()) {
        $columnList[] = $col['Field'];
    }
    $result['columns'] = $columnList;
    
    $conn->close();
    $result['success'] = true;
    
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);

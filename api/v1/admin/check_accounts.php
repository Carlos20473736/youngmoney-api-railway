<?php
/**
 * Script para verificar a tabela accounts
 * 
 * Endpoint: GET /api/v1/admin/check_accounts.php?key=YOUNGMONEY_DEBUG_2024
 */

header('Content-Type: application/json');

$key = $_GET['key'] ?? '';
if ($key !== 'YOUNGMONEY_DEBUG_2024') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

require_once __DIR__ . '/../../../database.php';

$result = ['success' => false];

try {
    $conn = getDbConnection();
    
    // Listar todas as tabelas com seus registros
    $tables = ['accounts', 'active_sessions', 'device_bindings', 'user_tokens', 'user_battery'];
    
    foreach ($tables as $tableName) {
        $res = $conn->query("SELECT * FROM `$tableName` LIMIT 30");
        if ($res) {
            $records = [];
            while ($row = $res->fetch_assoc()) {
                $records[] = $row;
            }
            $result[$tableName] = $records;
        }
    }
    
    // Verificar se há alguma tabela com email e user_id 58
    $res = $conn->query("SHOW TABLES");
    while ($row = $res->fetch_array()) {
        $tableName = $row[0];
        
        // Verificar se a tabela tem coluna email ou google_email
        $colRes = $conn->query("SHOW COLUMNS FROM `$tableName` LIKE '%email%'");
        if ($colRes && $colRes->num_rows > 0) {
            $emailCol = $colRes->fetch_assoc()['Field'];
            
            // Buscar registros com email
            $dataRes = $conn->query("SELECT * FROM `$tableName` WHERE `$emailCol` IS NOT NULL AND `$emailCol` != '' LIMIT 10");
            if ($dataRes && $dataRes->num_rows > 0) {
                $records = [];
                while ($r = $dataRes->fetch_assoc()) {
                    $records[] = $r;
                }
                $result['emails_in_' . $tableName] = $records;
            }
        }
    }
    
    $conn->close();
    $result['success'] = true;
    
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);

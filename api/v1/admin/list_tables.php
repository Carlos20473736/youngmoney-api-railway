<?php
/**
 * Script para listar todas as tabelas e encontrar onde os usuários estão
 * 
 * Endpoint: GET /api/v1/admin/list_tables.php?key=YOUNGMONEY_DEBUG_2024
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
    
    // Listar todas as tabelas
    $tables = [];
    $res = $conn->query("SHOW TABLES");
    while ($row = $res->fetch_array()) {
        $tableName = $row[0];
        
        // Contar registros
        $countRes = $conn->query("SELECT COUNT(*) as cnt FROM `$tableName`");
        $count = $countRes->fetch_assoc()['cnt'];
        
        // Pegar colunas
        $colRes = $conn->query("SHOW COLUMNS FROM `$tableName`");
        $columns = [];
        while ($col = $colRes->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
        
        $tables[$tableName] = [
            'count' => $count,
            'columns' => $columns
        ];
    }
    $result['tables'] = $tables;
    
    // Procurar tabelas que possam ter usuários (com coluna email)
    $tablesWithEmail = [];
    foreach ($tables as $name => $info) {
        if (in_array('email', $info['columns'])) {
            $tablesWithEmail[] = $name;
        }
    }
    $result['tables_with_email_column'] = $tablesWithEmail;
    
    // Verificar se existe tabela accounts, usuarios, customers, etc
    $possibleUserTables = ['users', 'accounts', 'usuarios', 'customers', 'members', 'profiles', 'user_accounts'];
    $foundUserTables = [];
    foreach ($possibleUserTables as $tableName) {
        if (isset($tables[$tableName])) {
            $foundUserTables[] = $tableName;
            
            // Mostrar alguns registros
            $sampleRes = $conn->query("SELECT * FROM `$tableName` LIMIT 5");
            $samples = [];
            while ($row = $sampleRes->fetch_assoc()) {
                $samples[] = $row;
            }
            $result['sample_' . $tableName] = $samples;
        }
    }
    $result['found_user_tables'] = $foundUserTables;
    
    // Verificar user_id 58 em todas as tabelas com email
    foreach ($tablesWithEmail as $tableName) {
        $checkRes = $conn->query("SELECT * FROM `$tableName` WHERE id = 58 OR user_id = 58 LIMIT 1");
        if ($checkRes && $row = $checkRes->fetch_assoc()) {
            $result['user_58_in_' . $tableName] = $row;
        }
    }
    
    $conn->close();
    $result['success'] = true;
    
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);

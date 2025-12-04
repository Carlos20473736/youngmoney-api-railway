<?php
/**
 * Setup de Tabelas de Segurança Avançada
 * Cria tabelas para suportar validação de 30 headers
 */

require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

try {
    $conn = getDbConnection();
    
    $tables = [];
    
    // 1. Tabela para request_ids (anti-replay)
    $sql1 = "CREATE TABLE IF NOT EXISTS request_ids (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id VARCHAR(36) NOT NULL UNIQUE,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_request_id (request_id),
        INDEX idx_created_at (created_at),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql1)) {
        $tables['request_ids'] = 'OK';
    } else {
        $tables['request_ids'] = 'ERROR: ' . $conn->error;
    }
    
    // 2. Tabela para métricas de segurança
    $sql2 = "CREATE TABLE IF NOT EXISTS security_metrics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        security_score INT NOT NULL,
        headers_count INT NOT NULL,
        alerts_count INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql2)) {
        $tables['security_metrics'] = 'OK';
    } else {
        $tables['security_metrics'] = 'ERROR: ' . $conn->error;
    }
    
    // 3. Tabela para violações de segurança
    $sql3 = "CREATE TABLE IF NOT EXISTS security_violations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        violation_type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        headers TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_violation_type (violation_type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql3)) {
        $tables['security_violations'] = 'OK';
    } else {
        $tables['security_violations'] = 'ERROR: ' . $conn->error;
    }
    
    // 4. Adicionar colunas extras na tabela users (verificar se existem primeiro)
    $alterQueries = [];
    
    // Verificar quais colunas já existem
    $existingColumns = [];
    $result = $conn->query("SHOW COLUMNS FROM users");
    while ($row = $result->fetch_assoc()) {
        $existingColumns[] = $row['Field'];
    }
    
    // Adicionar apenas colunas que não existem
    if (!in_array('device_id', $existingColumns)) {
        $alterQueries[] = "ALTER TABLE users ADD COLUMN device_id VARCHAR(36)";
    }
    if (!in_array('device_fingerprint', $existingColumns)) {
        $alterQueries[] = "ALTER TABLE users ADD COLUMN device_fingerprint VARCHAR(64)";
    }
    if (!in_array('last_request_sequence', $existingColumns)) {
        $alterQueries[] = "ALTER TABLE users ADD COLUMN last_request_sequence INT DEFAULT 0";
    }
    if (!in_array('session_id', $existingColumns)) {
        $alterQueries[] = "ALTER TABLE users ADD COLUMN session_id VARCHAR(36)";
    }
    
    $alterResults = [];
    
    if (empty($alterQueries)) {
        $alterResults[] = 'ALL COLUMNS ALREADY EXIST';
    } else {
        foreach ($alterQueries as $query) {
            if ($conn->query($query)) {
                $alterResults[] = 'OK';
            } else {
                $alterResults[] = 'ERROR: ' . $conn->error;
            }
        }
    }
    
    $tables['users_columns'] = implode(', ', $alterResults);
    
    // 5. Limpar nonces e request_ids antigos (mais de 5 minutos)
    $conn->query("DELETE FROM request_nonces WHERE created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $conn->query("DELETE FROM request_ids WHERE created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Tabelas de segurança avançada criadas com sucesso!',
        'tables_created' => $tables,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

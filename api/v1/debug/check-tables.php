<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../../../database.php';

try {
    $conn = getDbConnection();
    
    // Verificar tabelas existentes
    $result = $conn->query("SHOW TABLES");
    $tables = [];
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    // Verificar se device_keys existe
    $deviceKeysExists = in_array('device_keys', $tables);
    $encryptedLogExists = in_array('encrypted_requests_log', $tables);
    
    // Se não existir, criar
    if (!$deviceKeysExists) {
        $sql1 = "CREATE TABLE IF NOT EXISTS device_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(64) NOT NULL UNIQUE,
            device_key VARCHAR(64) NOT NULL,
            device_fingerprint VARCHAR(64) NOT NULL,
            app_hash VARCHAR(64) NOT NULL,
            device_info JSON,
            created_at DATETIME NOT NULL,
            last_seen DATETIME NOT NULL,
            key_updated_at DATETIME,
            request_count INT DEFAULT 0,
            is_blocked BOOLEAN DEFAULT FALSE,
            blocked_reason VARCHAR(255),
            blocked_at DATETIME,
            INDEX idx_device_id (device_id),
            INDEX idx_last_seen (last_seen),
            INDEX idx_is_blocked (is_blocked)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->query($sql1);
        $deviceKeysExists = true;
    }
    
    if (!$encryptedLogExists) {
        $sql2 = "CREATE TABLE IF NOT EXISTS encrypted_requests_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(64) NOT NULL,
            endpoint VARCHAR(255) NOT NULL,
            method VARCHAR(10) NOT NULL,
            timestamp BIGINT NOT NULL,
            key_window BIGINT NOT NULL,
            nonce VARCHAR(64) NOT NULL,
            signature VARCHAR(64) NOT NULL,
            response_code INT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_device_id (device_id),
            INDEX idx_timestamp (timestamp),
            INDEX idx_nonce (nonce)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->query($sql2);
        $encryptedLogExists = true;
    }
    
    $conn->close();
    
    echo json_encode([
        'status' => 'success',
        'tables' => $tables,
        'device_keys_exists' => $deviceKeysExists,
        'encrypted_requests_log_exists' => $encryptedLogExists,
        'message' => 'Tables created/verified successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

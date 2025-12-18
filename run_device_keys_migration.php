<?php
/**
 * Migration para criar tabela device_keys
 */

header('Content-Type: text/plain');

echo "=== YoungMoney Device Keys Migration ===\n";

require_once __DIR__ . '/database.php';

try {
    echo "✓ Conexão com banco de dados estabelecida\n";
    
    // Criar tabela device_keys
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
    
    $pdo->exec($sql1);
    echo "✓ Tabela device_keys criada\n";
    
    // Criar tabela encrypted_requests_log
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
    
    $pdo->exec($sql2);
    echo "✓ Tabela encrypted_requests_log criada\n";
    
    echo "\n=== Migration concluída com sucesso! ===\n";
    
} catch (PDOException $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
}

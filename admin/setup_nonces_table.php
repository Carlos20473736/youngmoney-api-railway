<?php
/**
 * Setup - Criar tabela request_nonces
 * 
 * Executar uma vez para criar a tabela de nonces
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // Criar tabela request_nonces
    $sql1 = "CREATE TABLE IF NOT EXISTS request_nonces (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        nonce VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_user_nonce (user_id, nonce),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql1)) {
        throw new Exception("Erro ao criar tabela: " . $conn->error);
    }
    
    // Criar tabela rate_limit_log
    $sql2 = "CREATE TABLE IF NOT EXISTS rate_limit_log (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_user_time (user_id, created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql2)) {
        throw new Exception("Erro ao criar tabela rate_limit_log: " . $conn->error);
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Tabelas de segurança criadas com sucesso!',
        'tables_created' => [
            'request_nonces' => 'OK',
            'rate_limit_log' => 'OK'
        ]
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>

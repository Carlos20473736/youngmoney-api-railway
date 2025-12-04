<?php
/**
 * Setup X-Req Tokens Table
 * Cria tabela para armazenar tokens x-req rotativos
 */

header("Content-Type: application/json");

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // Dropar tabela se existir (para recriar corretamente)
    $conn->query("DROP TABLE IF EXISTS xreq_tokens");
    
    // Criar tabela xreq_tokens
    $sql = "CREATE TABLE xreq_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL,
        created_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        used_at DATETIME NULL,
        INDEX idx_token (token),
        INDEX idx_user_id (user_id),
        INDEX idx_expires_at (expires_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Table xreq_tokens created successfully',
            'table' => 'xreq_tokens'
        ]);
    } else {
        throw new Exception($conn->error);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to create table: ' . $e->getMessage()
    ]);
}
?>

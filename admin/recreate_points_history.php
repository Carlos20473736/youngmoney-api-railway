<?php
header('Content-Type: application/json' );
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // Dropar tabela antiga
    $conn->query("DROP TABLE IF EXISTS points_history");
    
    // Criar tabela nova com estrutura correta
    $conn->query("
        CREATE TABLE points_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            points INT NOT NULL,
            description VARCHAR(255) DEFAULT 'Pontos adicionados',
            created_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_created (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo json_encode([
        'success' => true,
        'message' => 'Tabela points_history recriada com sucesso!',
        'columns' => ['id', 'user_id', 'points', 'description', 'created_at']
    ]);
    
} catch (Exception $e) {
    http_response_code(500 );
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

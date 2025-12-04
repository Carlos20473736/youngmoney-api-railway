<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    $results = [];
    
    // Dropar tabela antiga
    $sql1 = "DROP TABLE IF EXISTS withdrawals";
    if ($conn->query($sql1)) {
        $results[] = "Tabela 'withdrawals' antiga removida";
    } else {
        $results[] = "Erro ao remover tabela antiga: " . $conn->error;
    }
    
    // Recriar tabela com nomes corretos
    $sql2 = "CREATE TABLE withdrawals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        pix_type VARCHAR(20) NOT NULL,
        pix_key VARCHAR(255) NOT NULL,
        status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($sql2)) {
        $results[] = "Tabela 'withdrawals' recriada com sucesso";
    } else {
        $results[] = "Erro ao recriar tabela: " . $conn->error;
    }
    
    // Recriar índices
    $sql3 = "CREATE INDEX idx_withdrawals_user_id ON withdrawals(user_id)";
    if ($conn->query($sql3)) {
        $results[] = "Índice user_id criado";
    } else {
        if ($conn->errno != 1061) {
            $results[] = "Erro ao criar índice user_id: " . $conn->error;
        }
    }
    
    $sql4 = "CREATE INDEX idx_withdrawals_status ON withdrawals(status)";
    if ($conn->query($sql4)) {
        $results[] = "Índice status criado";
    } else {
        if ($conn->errno != 1061) {
            $results[] = "Erro ao criar índice status: " . $conn->error;
        }
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

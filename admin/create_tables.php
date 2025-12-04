<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    $results = [];
    
    // Criar tabela withdrawals
    $sql1 = "CREATE TABLE IF NOT EXISTS withdrawals (
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
    
    if ($conn->query($sql1)) {
        $results[] = "Tabela 'withdrawals' criada com sucesso";
    } else {
        $results[] = "Erro ao criar 'withdrawals': " . $conn->error;
    }
    
    // Criar tabela point_transactions
    $sql2 = "CREATE TABLE IF NOT EXISTS point_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        points INT NOT NULL,
        type ENUM('credit', 'debit') NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($sql2)) {
        $results[] = "Tabela 'point_transactions' criada com sucesso";
    } else {
        $results[] = "Erro ao criar 'point_transactions': " . $conn->error;
    }
    
    // Criar índices (ignorar erros se já existirem)
    $indexes = [
        "CREATE INDEX idx_withdrawals_user_id ON withdrawals(user_id)",
        "CREATE INDEX idx_withdrawals_status ON withdrawals(status)",
        "CREATE INDEX idx_point_transactions_user_id ON point_transactions(user_id)",
        "CREATE INDEX idx_point_transactions_type ON point_transactions(type)"
    ];
    
    foreach ($indexes as $index) {
        if ($conn->query($index)) {
            $results[] = "Índice criado com sucesso";
        } else {
            // Ignorar erro se índice já existe (erro 1061)
            if ($conn->errno != 1061) {
                $results[] = "Erro ao criar índice: " . $conn->error;
            } else {
                $results[] = "Índice já existe (ignorado)";
            }
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

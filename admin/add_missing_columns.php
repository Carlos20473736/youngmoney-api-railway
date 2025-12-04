<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    $results = [];
    
    // Adicionar colunas faltantes na tabela users
    $sql_statements = [
        // Adicionar master_seed
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS master_seed VARCHAR(255) DEFAULT NULL",
        
        // Adicionar session_salt
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS session_salt VARCHAR(255) DEFAULT NULL",
        
        // Adicionar last_login_at
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL",
        
        // Adicionar índices para performance
        "CREATE INDEX IF NOT EXISTS idx_last_login ON users(last_login_at)",
        "CREATE INDEX IF NOT EXISTS idx_updated ON users(updated_at)"
    ];
    
    // Executar cada statement
    foreach ($sql_statements as $index => $sql) {
        if ($conn->query($sql)) {
            $results[] = "✅ Statement " . ($index + 1) . " executado com sucesso";
        } else {
            $results[] = "⚠️ Statement " . ($index + 1) . ": " . $conn->error;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Missing columns added successfully!',
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

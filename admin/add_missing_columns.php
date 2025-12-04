<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    $results = [];
    
    // Adicionar colunas faltantes na tabela users
    $sql_statements = [
        // Adicionar master_seed
        "ALTER TABLE users ADD COLUMN master_seed VARCHAR(255) DEFAULT NULL",
        
        // Adicionar session_salt
        "ALTER TABLE users ADD COLUMN session_salt VARCHAR(255) DEFAULT NULL",
        
        // Adicionar last_login_at
        "ALTER TABLE users ADD COLUMN last_login_at TIMESTAMP NULL"
    ];
    
    // Executar cada statement
    foreach ($sql_statements as $index => $sql) {
        if ($conn->query($sql)) {
            $results[] = "✅ Statement " . ($index + 1) . " executado com sucesso";
        } else {
            // Ignorar erro se coluna já existir
            if (strpos($conn->error, "Duplicate column name") !== false) {
                $results[] = "ℹ️ Statement " . ($index + 1) . ": Coluna já existe (ignorado)";
            } else {
                $results[] = "⚠️ Statement " . ($index + 1) . ": " . $conn->error;
            }
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

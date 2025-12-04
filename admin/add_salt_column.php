<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    $results = [];
    
    // Adicionar coluna salt_updated_at
    $sql = "ALTER TABLE users ADD COLUMN salt_updated_at TIMESTAMP NULL";
    
    if ($conn->query($sql)) {
        $results[] = "✅ Coluna salt_updated_at adicionada com sucesso";
    } else {
        // Ignorar erro se coluna já existir
        if (strpos($conn->error, "Duplicate column name") !== false) {
            $results[] = "ℹ️ Coluna salt_updated_at já existe (ignorado)";
        } else {
            $results[] = "⚠️ Erro ao adicionar salt_updated_at: " . $conn->error;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Column salt_updated_at added successfully!',
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

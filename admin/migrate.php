<?php
require_once __DIR__ . '/../database.php';

// Script de migration para adicionar coluna daily_points
header('Content-Type: application/json');

try {
    $conn = getDbConnection();
    
    // Verificar se a coluna já existe
    $checkStmt = $conn->query("SHOW COLUMNS FROM users LIKE 'daily_points'");
    
    if ($checkStmt->num_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Coluna daily_points já existe'
        ]);
        exit;
    }
    
    // Adicionar coluna daily_points
    $conn->query("ALTER TABLE users ADD COLUMN daily_points INT DEFAULT 0 NOT NULL");
    
    // Criar índice para melhor performance
    $conn->query("CREATE INDEX idx_daily_points ON users(daily_points DESC)");
    
    echo json_encode([
        'success' => true,
        'message' => 'Migration executada com sucesso! Coluna daily_points adicionada.'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>

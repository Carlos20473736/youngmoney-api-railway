<?php
/**
 * Migration: Adicionar coluna telegram_id na tabela users
 */

require_once __DIR__ . '/database.php';

try {
    $conn = getDbConnection();
    
    // Verificar se a coluna já existe
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'telegram_id'");
    
    if ($result && $result->num_rows > 0) {
        echo "✅ Coluna 'telegram_id' já existe!\n";
        exit(0);
    }
    
    // Adicionar coluna telegram_id
    $sql = "ALTER TABLE users ADD COLUMN telegram_id VARCHAR(100) UNIQUE AFTER google_id";
    
    if ($conn->query($sql)) {
        echo "✅ Coluna 'telegram_id' adicionada com sucesso!\n";
        
        // Criar índice para melhor performance
        $conn->query("CREATE INDEX idx_telegram_id ON users(telegram_id)");
        echo "✅ Índice criado com sucesso!\n";
        
    } else {
        echo "❌ Erro ao adicionar coluna: " . $conn->error . "\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
?>

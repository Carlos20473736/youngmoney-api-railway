<?php
/**
 * Migration: Adicionar coluna password_hash à tabela users
 * Execute este script uma vez para adicionar suporte a login por email/senha
 */

require_once __DIR__ . '/database.php';

echo "=== Migration: Adicionar password_hash ===\n\n";

try {
    $conn = getDbConnection();
    
    // Verificar se a coluna já existe
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'password_hash'");
    
    if ($result->num_rows > 0) {
        echo "✓ Coluna password_hash já existe na tabela users\n";
    } else {
        // Adicionar a coluna
        $sql = "ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER email";
        
        if ($conn->query($sql)) {
            echo "✓ Coluna password_hash adicionada com sucesso!\n";
        } else {
            echo "✗ Erro ao adicionar coluna: " . $conn->error . "\n";
        }
    }
    
    // Verificar se existe índice no email
    $result = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'idx_users_email'");
    
    if ($result->num_rows > 0) {
        echo "✓ Índice idx_users_email já existe\n";
    } else {
        // Tentar criar índice (pode falhar se já existir com outro nome)
        $sql = "CREATE INDEX idx_users_email ON users(email)";
        if ($conn->query($sql)) {
            echo "✓ Índice idx_users_email criado com sucesso!\n";
        } else {
            echo "! Índice não criado (pode já existir): " . $conn->error . "\n";
        }
    }
    
    $conn->close();
    echo "\n=== Migration concluída ===\n";
    
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
}
?>

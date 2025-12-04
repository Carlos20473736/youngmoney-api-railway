<?php
/**
 * Script de Migração: Adicionar coluna value_amount na tabela withdrawal_quick_values
 * 
 * Este script adiciona a coluna value_amount que está faltando no banco de dados.
 * Executar uma única vez via navegador.
 */

require_once __DIR__ . '/database.php';

try {
    $conn = getDbConnection();
    
    echo "✅ Conectado ao banco de dados com sucesso!\n\n";
    echo "📊 Database: " . $conn->get_server_info() . "\n";
    echo "🖥️ Host: " . $_ENV['MYSQLHOST'] . ":" . $_ENV['MYSQLPORT'] . "\n\n";
    
    // Verificar se a tabela existe
    $tableCheck = $conn->query("SHOW TABLES LIKE 'withdrawal_quick_values'");
    
    if ($tableCheck->num_rows === 0) {
        echo "⚠️ Tabela 'withdrawal_quick_values' não existe!\n";
        echo "🔧 Criando tabela...\n\n";
        
        $createTable = "
            CREATE TABLE withdrawal_quick_values (
                id INT AUTO_INCREMENT PRIMARY KEY,
                value_amount INT NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                display_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_value (value_amount)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if ($conn->query($createTable)) {
            echo "✅ Tabela 'withdrawal_quick_values' criada com sucesso!\n\n";
        } else {
            throw new Exception("Erro ao criar tabela: " . $conn->error);
        }
    } else {
        echo "✅ Tabela 'withdrawal_quick_values' existe!\n\n";
        
        // Verificar se a coluna value_amount já existe
        $columnCheck = $conn->query("SHOW COLUMNS FROM withdrawal_quick_values LIKE 'value_amount'");
        
        if ($columnCheck->num_rows > 0) {
            echo "ℹ️ Coluna 'value_amount' já existe na tabela!\n";
        } else {
            echo "🔧 Adicionando coluna 'value_amount' na tabela withdrawal_quick_values...\n\n";
            
            $alterTable = "
                ALTER TABLE withdrawal_quick_values 
                ADD COLUMN value_amount INT NOT NULL AFTER id,
                ADD UNIQUE KEY unique_value (value_amount)
            ";
            
            if ($conn->query($alterTable)) {
                echo "✅ Coluna 'value_amount' adicionada com sucesso!\n\n";
            } else {
                throw new Exception("Erro ao adicionar coluna: " . $conn->error);
            }
        }
    }
    
    // Exibir estrutura da tabela
    echo "📋 Estrutura da tabela withdrawal_quick_values:\n";
    echo "------------------------------------------------------------\n";
    
    $result = $conn->query("DESCRIBE withdrawal_quick_values");
    while ($row = $result->fetch_assoc()) {
        echo sprintf("%-20s %-20s %-10s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null']
        );
    }
    echo "------------------------------------------------------------\n\n";
    
    echo "🎉 Migração concluída com sucesso!\n";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
?>

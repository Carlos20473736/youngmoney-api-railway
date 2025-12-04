<?php
/**
 * Script de migração: Adicionar coluna description na tabela roulette_settings
 * 
 * Este script deve ser executado uma vez para adicionar a coluna description
 * que está faltando na tabela roulette_settings.
 */

// Configuração do banco de dados usando variáveis de ambiente
$host = getenv('MYSQLHOST') ?: 'localhost';
$port = getenv('MYSQLPORT') ?: '3306';
$user = getenv('MYSQLUSER') ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: '';
$database = getenv('MYSQLDATABASE') ?: 'railway';

try {
    // Conectar ao banco de dados
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Conectado ao banco de dados com sucesso!\n";
    echo "📊 Database: $database\n";
    echo "🖥️  Host: $host:$port\n\n";
    
    // Verificar se a coluna já existe
    $stmt = $pdo->query("SHOW COLUMNS FROM roulette_settings LIKE 'description'");
    $columnExists = $stmt->fetch();
    
    if ($columnExists) {
        echo "ℹ️  A coluna 'description' já existe na tabela roulette_settings.\n";
    } else {
        echo "🔧 Adicionando coluna 'description' na tabela roulette_settings...\n";
        
        // Adicionar a coluna
        $pdo->exec("ALTER TABLE roulette_settings ADD COLUMN description TEXT NULL");
        
        echo "✅ Coluna 'description' adicionada com sucesso!\n";
    }
    
    // Mostrar a estrutura atualizada da tabela
    echo "\n📋 Estrutura da tabela roulette_settings:\n";
    echo str_repeat("-", 60) . "\n";
    
    $stmt = $pdo->query("DESCRIBE roulette_settings");
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        printf("  %-20s %-15s %s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'] === 'YES' ? 'NULL' : 'NOT NULL'
        );
    }
    
    echo str_repeat("-", 60) . "\n";
    echo "\n🎉 Migração concluída com sucesso!\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

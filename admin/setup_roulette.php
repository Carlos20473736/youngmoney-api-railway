<?php
require_once __DIR__ . '/../database.php';

echo "=== Configurando Tabela de Configurações da Roleta ===\n\n";

try {
    $conn = getDbConnection();
    
    // Criar tabela
    echo "1. Criando tabela roulette_settings...\n";
    $sql = "
    CREATE TABLE IF NOT EXISTS roulette_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE COMMENT 'Chave da configuração (ex: prize_1, prize_2)',
        setting_value VARCHAR(255) NOT NULL COMMENT 'Valor da configuração',
        description VARCHAR(255) DEFAULT NULL COMMENT 'Descrição da configuração',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn->query($sql)) {
        echo "   ✓ Tabela criada com sucesso\n\n";
    } else {
        throw new Exception("Erro ao criar tabela: " . $conn->error);
    }
    
    // Inserir valores padrão
    echo "2. Inserindo valores padrão...\n";
    $prizes = [
        ['prize_1', '100', 'Prêmio 1 da roleta (em pontos)'],
        ['prize_2', '200', 'Prêmio 2 da roleta (em pontos)'],
        ['prize_3', '300', 'Prêmio 3 da roleta (em pontos)'],
        ['prize_4', '500', 'Prêmio 4 da roleta (em pontos)'],
        ['prize_5', '1000', 'Prêmio 5 da roleta (em pontos)'],
        ['prize_6', '2000', 'Prêmio 6 da roleta (em pontos)'],
        ['prize_7', '5000', 'Prêmio 7 da roleta (em pontos)'],
        ['prize_8', '10000', 'Prêmio 8 da roleta (em pontos)']
    ];
    
    $stmt = $conn->prepare("
        INSERT INTO roulette_settings (setting_key, setting_value, description) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            description = VALUES(description)
    ");
    
    foreach ($prizes as $prize) {
        $stmt->bind_param('sss', $prize[0], $prize[1], $prize[2]);
        $stmt->execute();
        echo "   ✓ {$prize[0]}: {$prize[1]} pontos\n";
    }
    
    $stmt->close();
    
    // Criar índice
    echo "\n3. Criando índice...\n";
    $sql = "CREATE INDEX IF NOT EXISTS idx_setting_key ON roulette_settings(setting_key)";
    if ($conn->query($sql)) {
        echo "   ✓ Índice criado com sucesso\n\n";
    }
    
    // Verificar
    echo "4. Verificando configurações...\n";
    $result = $conn->query("SELECT setting_key, setting_value FROM roulette_settings ORDER BY setting_key");
    
    echo "\n   Configurações atuais:\n";
    echo "   " . str_repeat("-", 40) . "\n";
    while ($row = $result->fetch_assoc()) {
        echo "   {$row['setting_key']}: {$row['setting_value']} pontos\n";
    }
    echo "   " . str_repeat("-", 40) . "\n";
    
    $conn->close();
    
    echo "\n✓ Configuração concluída com sucesso!\n";
    
} catch (Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

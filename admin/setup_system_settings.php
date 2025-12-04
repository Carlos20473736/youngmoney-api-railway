<?php
/**
 * Script de Setup para Tabelas de Configurações do Sistema
 * Execute este arquivo uma vez para criar as tabelas necessárias
 */

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    echo "Criando tabelas de configurações do sistema...\n\n";
    
    // 1. Criar tabela system_settings
    echo "1. Criando tabela system_settings...\n";
    $sql = "
        CREATE TABLE IF NOT EXISTS system_settings (
          id INT PRIMARY KEY AUTO_INCREMENT,
          setting_key VARCHAR(50) UNIQUE NOT NULL,
          setting_value TEXT NOT NULL,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn->query($sql)) {
        echo "✓ Tabela system_settings criada com sucesso!\n\n";
    } else {
        throw new Exception("Erro ao criar tabela system_settings: " . $conn->error);
    }
    
    // 2. Inserir valores padrão
    echo "2. Inserindo valores padrão...\n";
    $settings = [
        ['reset_time', '21:00'],
        ['min_withdrawal', '10'],
        ['max_withdrawal', '1000']
    ];
    
    $stmt = $conn->prepare("
        INSERT INTO system_settings (setting_key, setting_value) 
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    
    foreach ($settings as $setting) {
        $stmt->bind_param('ss', $setting[0], $setting[1]);
        $stmt->execute();
        echo "  ✓ {$setting[0]} = {$setting[1]}\n";
    }
    echo "\n";
    
    // 3. Criar tabela admin_logs
    echo "3. Criando tabela admin_logs...\n";
    $sql = "
        CREATE TABLE IF NOT EXISTS admin_logs (
          id INT PRIMARY KEY AUTO_INCREMENT,
          action VARCHAR(100) NOT NULL,
          details TEXT,
          admin_id INT DEFAULT NULL,
          ip_address VARCHAR(45) DEFAULT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_action (action),
          INDEX idx_created_at (created_at),
          INDEX idx_admin_id (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn->query($sql)) {
        echo "✓ Tabela admin_logs criada com sucesso!\n\n";
    } else {
        throw new Exception("Erro ao criar tabela admin_logs: " . $conn->error);
    }
    
    // 4. Verificar criação
    echo "4. Verificando tabelas criadas...\n";
    $result = $conn->query("
        SELECT 
            'system_settings' as table_name,
            COUNT(*) as record_count 
        FROM system_settings
        UNION ALL
        SELECT 
            'admin_logs' as table_name,
            COUNT(*) as record_count 
        FROM admin_logs
    ");
    
    while ($row = $result->fetch_assoc()) {
        echo "  ✓ {$row['table_name']}: {$row['record_count']} registros\n";
    }
    echo "\n";
    
    // 5. Exibir configurações atuais
    echo "5. Configurações atuais:\n";
    $result = $conn->query("SELECT * FROM system_settings ORDER BY setting_key");
    while ($row = $result->fetch_assoc()) {
        echo "  • {$row['setting_key']}: {$row['setting_value']}\n";
    }
    echo "\n";
    
    echo "✅ Setup concluído com sucesso!\n";
    echo "\nPróximos passos:\n";
    echo "1. Acesse o painel admin em: https://seu-painel.up.railway.app/settings\n";
    echo "2. Configure o horário de reset conforme necessário\n";
    echo "3. Verifique os logs em admin_logs para monitorar alterações\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
?>

<?php
/**
 * Script Mestre para Recriar Tabelas de Configuração
 * Corrige discrepâncias entre nomes de tabelas/colunas e o código dos endpoints
 */

require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

try {
    $conn = getDbConnection();
    
    $results = [];
    
    // ==========================================
    // 1. Tabela system_settings
    // ==========================================
    $conn->query("DROP TABLE IF EXISTS system_settings");
    $sql = "CREATE TABLE system_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_setting_key (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        // Inserir padrões
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        $defaults = [
            ['reset_time', '00:00'],
            ['min_withdrawal', '10'],
            ['max_withdrawal', '1000']
        ];
        foreach ($defaults as $d) {
            $stmt->bind_param('ss', $d[0], $d[1]);
            $stmt->execute();
        }
        $results[] = "✅ Tabela system_settings recriada com sucesso";
    } else {
        throw new Exception("Erro ao criar system_settings: " . $conn->error);
    }
    
    // ==========================================
    // 2. Tabela roulette_prizes (usada pelo settings.php)
    // ==========================================
    $conn->query("DROP TABLE IF EXISTS roulette_prizes");
    $sql = "CREATE TABLE roulette_prizes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prize_key VARCHAR(50) UNIQUE NOT NULL,
        prize_value DECIMAL(10,2) NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        // Inserir padrões
        $stmt = $conn->prepare("INSERT INTO roulette_prizes (prize_key, prize_value, description) VALUES (?, ?, ?)");
        $defaults = [
            ['prize_1', 100, 'Prêmio 1'],
            ['prize_2', 200, 'Prêmio 2'],
            ['prize_3', 300, 'Prêmio 3'],
            ['prize_4', 500, 'Prêmio 4'],
            ['prize_5', 1000, 'Prêmio 5'],
            ['prize_6', 2000, 'Prêmio 6'],
            ['prize_7', 5000, 'Prêmio 7'],
            ['prize_8', 10000, 'Prêmio 8']
        ];
        foreach ($defaults as $d) {
            $stmt->bind_param('sds', $d[0], $d[1], $d[2]);
            $stmt->execute();
        }
        $results[] = "✅ Tabela roulette_prizes recriada com sucesso";
    } else {
        throw new Exception("Erro ao criar roulette_prizes: " . $conn->error);
    }
    
    // ==========================================
    // 3. Tabela withdrawal_quick_values
    // ==========================================
    $conn->query("DROP TABLE IF EXISTS withdrawal_quick_values");
    $sql = "CREATE TABLE withdrawal_quick_values (
        id INT AUTO_INCREMENT PRIMARY KEY,
        value_amount DECIMAL(10,2) NOT NULL,
        display_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active_order (is_active, display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        // Inserir padrões
        $stmt = $conn->prepare("INSERT INTO withdrawal_quick_values (value_amount, display_order) VALUES (?, ?)");
        $defaults = [
            [1.00, 1],
            [10.00, 2],
            [20.00, 3],
            [50.00, 4]
        ];
        foreach ($defaults as $d) {
            $stmt->bind_param('di', $d[0], $d[1]);
            $stmt->execute();
        }
        $results[] = "✅ Tabela withdrawal_quick_values recriada com sucesso";
    } else {
        throw new Exception("Erro ao criar withdrawal_quick_values: " . $conn->error);
    }
    
    // ==========================================
    // 4. Tabela admin_logs
    // ==========================================
    $sql = "CREATE TABLE IF NOT EXISTS admin_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        admin_id INT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_action (action),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        $results[] = "✅ Tabela admin_logs verificada/criada com sucesso";
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Todas as tabelas de configuração foram recriadas!',
        'results' => $results
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

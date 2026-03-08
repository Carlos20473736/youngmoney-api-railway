<?php
/**
 * Script de Migração: Sistema de Saque com Crypto
 * 
 * Executa a migração para:
 * - Atualizar taxa de conversão para 5.000.000 pontos = R$ 1,00
 * - Adicionar suporte a Binance e FaucetPay (Litecoin)
 * - Criar tabela de endereços crypto
 * - Atualizar valores rápidos de saque
 * 
 * Uso: php run_crypto_migration.php
 * Ou acesse via browser: /run_crypto_migration.php
 */

header('Content-Type: application/json');
require_once __DIR__ . '/database.php';

try {
    $conn = getDbConnection();
    $results = [];
    
    // 1. Atualizar/inserir configurações do sistema
    $settings = [
        ['points_per_real', '5000000'],
        ['min_withdrawal_points', '5000000'],
        ['min_withdrawal_brl', '1.00'],
        ['max_withdrawal_brl', '1000.00'],
        ['withdrawal_methods', 'pix,binance,faucetpay'],
        ['crypto_currency', 'LTC'],
        ['crypto_enabled', '1'],
    ];
    
    foreach ($settings as $setting) {
        $stmt = $conn->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->bind_param("ss", $setting[0], $setting[1]);
        $stmt->execute();
        $stmt->close();
    }
    $results[] = "✅ Configurações do sistema atualizadas (5M pontos = R$1)";
    
    // 2. Adicionar colunas na tabela withdrawals
    $columns = [
        "ALTER TABLE withdrawals ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) DEFAULT 'pix'",
        "ALTER TABLE withdrawals ADD COLUMN IF NOT EXISTS crypto_address VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE withdrawals ADD COLUMN IF NOT EXISTS crypto_amount DECIMAL(18, 8) DEFAULT NULL",
        "ALTER TABLE withdrawals ADD COLUMN IF NOT EXISTS crypto_currency VARCHAR(10) DEFAULT NULL",
        "ALTER TABLE withdrawals ADD COLUMN IF NOT EXISTS points_debited BIGINT DEFAULT NULL",
        "ALTER TABLE withdrawals ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(18, 8) DEFAULT NULL",
    ];
    
    foreach ($columns as $sql) {
        try {
            $conn->query($sql);
        } catch (Exception $e) {
            // Coluna pode já existir
        }
    }
    $results[] = "✅ Colunas crypto adicionadas na tabela withdrawals";
    
    // 3. Criar tabela de endereços crypto
    $conn->query("
        CREATE TABLE IF NOT EXISTS user_crypto_addresses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            platform VARCHAR(20) NOT NULL,
            address VARCHAR(255) NOT NULL,
            currency VARCHAR(10) DEFAULT 'LTC',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_platform (user_id, platform),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $results[] = "✅ Tabela user_crypto_addresses criada";
    
    // 4. Atualizar valores rápidos de saque
    $conn->query("DELETE FROM withdrawal_quick_values");
    $quickValues = [
        [1, 1],
        [2, 2],
        [5, 3],
        [10, 4],
        [20, 5],
        [50, 6],
    ];
    
    foreach ($quickValues as $qv) {
        $stmt = $conn->prepare("
            INSERT INTO withdrawal_quick_values (value_amount, display_order, is_active)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE display_order = VALUES(display_order), is_active = 1
        ");
        $stmt->bind_param("ii", $qv[0], $qv[1]);
        $stmt->execute();
        $stmt->close();
    }
    $results[] = "✅ Valores rápidos atualizados: R$1, R$2, R$5, R$10, R$20, R$50";
    
    // 5. Criar índices
    try {
        $conn->query("CREATE INDEX idx_withdrawals_method ON withdrawals(payment_method)");
    } catch (Exception $e) {}
    try {
        $conn->query("CREATE INDEX idx_crypto_addresses_user ON user_crypto_addresses(user_id, platform)");
    } catch (Exception $e) {}
    $results[] = "✅ Índices criados";
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Migração executada com sucesso!',
        'results' => $results,
        'config' => [
            'points_per_real' => '5.000.000',
            'min_withdrawal' => 'R$ 1,00 (5.000.000 pontos)',
            'methods' => ['PIX', 'Binance (LTC)', 'FaucetPay (LTC)'],
            'quick_values' => ['R$1', 'R$2', 'R$5', 'R$10', 'R$20', 'R$50'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
}
?>

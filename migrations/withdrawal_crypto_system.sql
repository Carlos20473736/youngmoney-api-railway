-- ========================================
-- MIGRAÇÃO: Sistema de Saque com Pontos (5M = R$1) + Crypto (Binance/FaucetPay)
-- Data: 2026-03-08
-- ========================================

-- 1. Atualizar configurações do sistema com a nova taxa de conversão
INSERT INTO system_settings (setting_key, setting_value) VALUES
('points_per_real', '5000000'),
('min_withdrawal_points', '5000000'),
('min_withdrawal_brl', '1.00'),
('max_withdrawal_brl', '1000.00'),
('withdrawal_methods', 'pix,binance,faucetpay'),
('crypto_currency', 'LTC'),
('crypto_enabled', '1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- 2. Adicionar coluna de método de pagamento na tabela withdrawals (se não existir)
ALTER TABLE withdrawals 
ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) DEFAULT 'pix' COMMENT 'pix, binance, faucetpay',
ADD COLUMN IF NOT EXISTS crypto_address VARCHAR(255) DEFAULT NULL COMMENT 'Endereço crypto do usuário',
ADD COLUMN IF NOT EXISTS crypto_amount DECIMAL(18, 8) DEFAULT NULL COMMENT 'Valor em crypto (LTC)',
ADD COLUMN IF NOT EXISTS crypto_currency VARCHAR(10) DEFAULT NULL COMMENT 'Moeda crypto (LTC)',
ADD COLUMN IF NOT EXISTS points_debited BIGINT DEFAULT NULL COMMENT 'Pontos debitados do usuário',
ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(18, 8) DEFAULT NULL COMMENT 'Taxa de câmbio BRL->Crypto no momento';

-- 3. Criar tabela para armazenar endereços crypto dos usuários
CREATE TABLE IF NOT EXISTS user_crypto_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    platform VARCHAR(20) NOT NULL COMMENT 'binance, faucetpay',
    address VARCHAR(255) NOT NULL COMMENT 'Endereço ou ID da carteira',
    currency VARCHAR(10) DEFAULT 'LTC' COMMENT 'Moeda padrão',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_platform (user_id, platform),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Atualizar valores rápidos de saque (em BRL, baseados em 5M pontos = R$1)
DELETE FROM withdrawal_quick_values;
INSERT INTO withdrawal_quick_values (value_amount, display_order, is_active) VALUES
(1, 1, 1),
(2, 2, 1),
(5, 3, 1),
(10, 4, 1),
(20, 5, 1),
(50, 6, 1)
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order), is_active = VALUES(is_active);

-- 5. Índices para performance
CREATE INDEX IF NOT EXISTS idx_withdrawals_method ON withdrawals(payment_method);
CREATE INDEX IF NOT EXISTS idx_crypto_addresses_user ON user_crypto_addresses(user_id, platform);

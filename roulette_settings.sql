-- ========================================
-- TABELA DE CONFIGURAÇÕES DA ROLETA
-- ========================================

CREATE TABLE IF NOT EXISTS roulette_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE COMMENT 'Chave da configuração (ex: prize_1, prize_2)',
    setting_value VARCHAR(255) NOT NULL COMMENT 'Valor da configuração',
    description VARCHAR(255) DEFAULT NULL COMMENT 'Descrição da configuração',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir valores padrão para os 8 prêmios da roleta
INSERT INTO roulette_settings (setting_key, setting_value, description) VALUES
('prize_1', '100', 'Prêmio 1 da roleta (em pontos)'),
('prize_2', '200', 'Prêmio 2 da roleta (em pontos)'),
('prize_3', '300', 'Prêmio 3 da roleta (em pontos)'),
('prize_4', '500', 'Prêmio 4 da roleta (em pontos)'),
('prize_5', '1000', 'Prêmio 5 da roleta (em pontos)'),
('prize_6', '2000', 'Prêmio 6 da roleta (em pontos)'),
('prize_7', '5000', 'Prêmio 7 da roleta (em pontos)'),
('prize_8', '10000', 'Prêmio 8 da roleta (em pontos)'),
('max_daily_spins', '10', 'Número máximo de giros diários permitidos')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    description = VALUES(description);

-- Criar índice para busca rápida
CREATE INDEX IF NOT EXISTS idx_setting_key ON roulette_settings(setting_key);

-- ========================================
-- VERIFICAÇÃO
-- ========================================

SELECT * FROM roulette_settings ORDER BY setting_key;

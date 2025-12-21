-- ========================================
-- TABELA DE CONFIGURAÇÕES DO MONETAG
-- ========================================

-- Adicionar configurações de impressões necessárias na tabela roulette_settings
-- (reutilizando a tabela existente para configurações gerais)

INSERT INTO roulette_settings (setting_key, setting_value, description) VALUES
('monetag_required_impressions', '5', 'Número de impressões necessárias para desbloquear roleta'),
('monetag_required_clicks', '1', 'Número de cliques necessários para desbloquear roleta')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    description = VALUES(description);

-- ========================================
-- VERIFICAÇÃO
-- ========================================

SELECT * FROM roulette_settings WHERE setting_key LIKE 'monetag%';

-- Migration: Adicionar coluna required_clicks na tabela user_required_impressions
-- Data: 2026-01-23
-- Descrição: Adiciona suporte para randomização de cliques necessários (1-3) por usuário

-- Adicionar coluna required_clicks se não existir
ALTER TABLE user_required_impressions 
ADD COLUMN IF NOT EXISTS required_clicks INT DEFAULT 1 AFTER required_impressions;

-- Atualizar usuários existentes com valor aleatório entre 1 e 3
-- (Isso será feito pelo reset_postback.php, mas podemos definir um valor padrão aqui)
UPDATE user_required_impressions 
SET required_clicks = FLOOR(1 + RAND() * 3)
WHERE required_clicks IS NULL OR required_clicks = 0;

-- Adicionar configuração global de cliques necessários na roulette_settings
INSERT INTO roulette_settings (setting_key, setting_value, description)
VALUES ('monetag_required_clicks', '1', 'Número de cliques necessários para desbloquear roleta (1-3)')
ON DUPLICATE KEY UPDATE description = VALUES(description);

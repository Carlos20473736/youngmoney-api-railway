-- Migration: Configurações de Atualização do App
-- Adiciona configurações para controle de versão e atualização forçada

-- Inserir configurações padrão de atualização (se não existirem)
INSERT IGNORE INTO system_settings (setting_key, setting_value, description, created_at, updated_at) VALUES
('app_update_enabled', '0', 'Habilita verificação de atualização do app', NOW(), NOW()),
('app_update_min_version', '1.0.0', 'Versão mínima requerida do app', NOW(), NOW()),
('app_update_download_url', '', 'URL para download da nova versão do app', NOW(), NOW()),
('app_update_force', '0', 'Força atualização (bloqueia uso do app)', NOW(), NOW()),
('app_update_release_notes', '', 'Notas de lançamento da nova versão', NOW(), NOW());

-- Verificar se as configurações foram inseridas
SELECT * FROM system_settings WHERE setting_key LIKE 'app_update_%';

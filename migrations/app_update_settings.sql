-- Migration: Configurações de Atualização do App
-- Adiciona configurações para controle de versão e atualização forçada
-- Atualizado: Adicionado app_update_secondary_url para link direto do APK

-- Inserir configurações padrão de atualização (se não existirem)
INSERT IGNORE INTO system_settings (setting_key, setting_value, created_at, updated_at) VALUES
('app_update_enabled', '0', NOW(), NOW()),
('app_update_min_version', '1.0.0', NOW(), NOW()),
('app_update_download_url', '', NOW(), NOW()),
('app_update_secondary_url', '', NOW(), NOW()),
('app_update_force', '0', NOW(), NOW()),
('app_update_release_notes', '', NOW(), NOW());

-- Verificar se as configurações foram inseridas
SELECT * FROM system_settings WHERE setting_key LIKE 'app_update_%';

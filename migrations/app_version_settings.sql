-- Migration: Configurações de Versão do App
-- Data: 2026-01-11
-- Descrição: Adiciona configurações para controle de atualização do app

-- Criar tabela system_settings se não existir
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- Inserir configurações padrão de versão do app
INSERT INTO system_settings (setting_key, setting_value) VALUES
    ('app_version', '43.0'),
    ('app_version_code', '43'),
    ('app_update_enabled', '0'),
    ('app_download_url', ''),
    ('app_force_update', '0'),
    ('app_release_notes', '')
ON DUPLICATE KEY UPDATE updated_at = NOW();

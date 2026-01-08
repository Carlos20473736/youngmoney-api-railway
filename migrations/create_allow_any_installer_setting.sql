-- Migration: Adicionar configuração allow_any_installer
-- Esta configuração controla se o app pode ser instalado de qualquer lugar
-- Se TRUE: permite instalação de qualquer fonte (APK direto, lojas alternativas, etc)
-- Se FALSE: permite apenas instalação via Google Play Store

-- Criar tabela system_settings se não existir
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir configuração allow_any_installer (padrão: false = apenas Play Store)
INSERT INTO system_settings (setting_key, setting_value, description) 
VALUES ('allow_any_installer', 'false', 'Se ativado, permite instalação do app de qualquer fonte. Se desativado, apenas Play Store é permitida.')
ON DUPLICATE KEY UPDATE description = VALUES(description);

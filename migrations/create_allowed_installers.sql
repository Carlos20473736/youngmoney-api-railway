-- Migration: Criar tabela allowed_installers
-- Esta tabela armazena os pacotes de instaladores permitidos para o app Android
-- O app verifica se foi instalado por uma fonte permitida

CREATE TABLE IF NOT EXISTS allowed_installers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_name VARCHAR(255) NOT NULL UNIQUE COMMENT 'Nome do pacote do instalador (ex: com.android.vending)',
    description VARCHAR(500) DEFAULT NULL COMMENT 'Descrição do instalador (ex: Google Play Store)',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Se o instalador está ativo/permitido',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir instaladores padrão
INSERT IGNORE INTO allowed_installers (package_name, description, is_active) VALUES
('com.android.vending', 'Google Play Store', TRUE),
('com.android.shell', 'Instalação via ADB/Android Studio', TRUE),
('adb', 'ADB (Android Debug Bridge)', TRUE);

-- Índice para busca rápida por status ativo
CREATE INDEX IF NOT EXISTS idx_allowed_installers_active ON allowed_installers(is_active);

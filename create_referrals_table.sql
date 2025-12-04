-- ========================================
-- TABELA DE CONVITES (REFERRALS)
-- ========================================

CREATE TABLE IF NOT EXISTS referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_user_id INT NOT NULL COMMENT 'ID do usuário que convidou',
    referred_user_id INT NOT NULL COMMENT 'ID do usuário que foi convidado',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data do convite',
    
    -- Índices para performance
    INDEX idx_referrer (referrer_user_id),
    INDEX idx_referred (referred_user_id),
    
    -- Garantir que cada usuário só pode ser convidado uma vez
    UNIQUE KEY unique_referred (referred_user_id),
    
    -- Foreign keys (opcional, depende da estrutura)
    FOREIGN KEY (referrer_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- ADICIONAR COLUNA invite_code NA TABELA users
-- ========================================

ALTER TABLE users 
ADD COLUMN IF NOT EXISTS invite_code VARCHAR(20) DEFAULT NULL COMMENT 'Código de convite único do usuário',
ADD UNIQUE INDEX idx_invite_code (invite_code);

-- ========================================
-- TABELA DE CONFIGURAÇÕES DO SISTEMA
-- ========================================

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir valores padrão para pontos de convite
INSERT INTO system_settings (setting_key, setting_value) 
VALUES 
    ('invite_points_inviter', '500'),
    ('invite_points_invited', '500')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- ========================================
-- VERIFICAÇÃO
-- ========================================

-- Verificar estrutura da tabela referrals
DESCRIBE referrals;

-- Verificar estrutura da tabela users (coluna invite_code)
SHOW COLUMNS FROM users LIKE 'invite_code';

-- Verificar configurações
SELECT * FROM system_settings WHERE setting_key LIKE 'invite_points%';

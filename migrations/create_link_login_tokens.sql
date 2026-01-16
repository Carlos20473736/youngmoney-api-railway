-- Migration: Create link_login_tokens table
-- Description: Tabela para armazenar tokens temporários de login via deep link
-- Date: 2026-01-15

CREATE TABLE IF NOT EXISTS link_login_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Token temporário único (64 chars hex)',
    user_id INT NOT NULL COMMENT 'ID do usuário associado',
    auth_token VARCHAR(255) NOT NULL COMMENT 'Token de autenticação do usuário',
    session_salt VARCHAR(64) NOT NULL COMMENT 'Salt da sessão',
    encrypted_seed TEXT COMMENT 'Seed criptografada',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    used_at TIMESTAMP NULL COMMENT 'Data em que foi usado (NULL se não usado)',
    expires_at TIMESTAMP NOT NULL COMMENT 'Data de expiração',
    
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar evento para limpar tokens expirados automaticamente (opcional)
-- Requer SUPER privilege ou EVENT_SCHEDULER habilitado
-- CREATE EVENT IF NOT EXISTS cleanup_expired_link_tokens
-- ON SCHEDULE EVERY 1 HOUR
-- DO DELETE FROM link_login_tokens WHERE expires_at < NOW();

-- Script SQL para criar tabela XReq (versão simplificada)
-- Execute este script no seu banco de dados

CREATE TABLE IF NOT EXISTS defaultdb.xreq_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    is_used BOOLEAN DEFAULT FALSE,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    INDEX idx_token (token),
    INDEX idx_used (is_used),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

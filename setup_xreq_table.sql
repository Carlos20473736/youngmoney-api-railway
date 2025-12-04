-- Tabela para armazenar tokens XReq (uso único)
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

-- Limpar tokens antigos (mais de 5 minutos sem uso)
CREATE EVENT IF NOT EXISTS cleanup_expired_xreq_tokens
ON SCHEDULE EVERY 5 MINUTE
DO
DELETE FROM xreq_tokens 
WHERE is_used = FALSE 
AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE);

-- Limpar tokens usados (mais de 1 hora)
CREATE EVENT IF NOT EXISTS cleanup_used_xreq_tokens
ON SCHEDULE EVERY 1 HOUR
DO
DELETE FROM xreq_tokens 
WHERE is_used = TRUE 
AND used_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Tabela para rate limiting
CREATE TABLE IF NOT EXISTS rate_limit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_user_time (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar evento para limpar logs antigos automaticamente (a cada hora)
CREATE EVENT IF NOT EXISTS cleanup_rate_limit_log
ON SCHEDULE EVERY 1 HOUR
DO
  DELETE FROM rate_limit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);

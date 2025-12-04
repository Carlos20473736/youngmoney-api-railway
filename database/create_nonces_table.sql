-- Tabela para armazenar nonces e prevenir replay attacks
CREATE TABLE IF NOT EXISTS request_nonces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nonce VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_user_nonce (user_id, nonce),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar evento para limpar nonces antigos automaticamente (a cada hora)
CREATE EVENT IF NOT EXISTS cleanup_old_nonces
ON SCHEDULE EVERY 1 HOUR
DO
  DELETE FROM request_nonces WHERE created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE);

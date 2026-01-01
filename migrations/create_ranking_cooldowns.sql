-- Migration: Criar tabela de cooldowns do ranking
-- Top 1-3: 2 dias de cooldown
-- Top 4-10: 1 dia de cooldown

CREATE TABLE IF NOT EXISTS ranking_cooldowns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    position INT NOT NULL COMMENT 'Posição no ranking quando ganhou',
    prize_amount DECIMAL(10,2) NOT NULL COMMENT 'Valor do prêmio recebido',
    cooldown_days INT NOT NULL COMMENT 'Dias de cooldown (1 ou 2)',
    cooldown_until DATETIME NOT NULL COMMENT 'Data/hora até quando está bloqueado',
    reset_date DATE NOT NULL COMMENT 'Data do reset que gerou o cooldown',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_cooldown (user_id, cooldown_until),
    INDEX idx_cooldown_until (cooldown_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

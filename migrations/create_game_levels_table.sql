-- Migration: Create game_levels table
-- Description: Tabela para armazenar o progresso de levels dos usuários no jogo Candy

CREATE TABLE IF NOT EXISTS game_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    level INT NOT NULL DEFAULT 1,
    highest_level INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Comentários:
-- user_id: ID do usuário (referência à tabela users)
-- level: Level atual do usuário no jogo
-- highest_level: Maior level que o usuário já alcançou
-- created_at: Data de criação do registro
-- updated_at: Data da última atualização

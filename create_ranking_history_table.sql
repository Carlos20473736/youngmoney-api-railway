-- ========================================
-- TABELA DE HISTÓRICO COMPLETO DO RANKING
-- ========================================
-- Armazena snapshots completos do ranking

CREATE TABLE IF NOT EXISTS ranking_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATETIME NOT NULL,
    position INT NOT NULL,
    user_id INT NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    profile_picture VARCHAR(500) DEFAULT '',
    points INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_snapshot_date (snapshot_date),
    INDEX idx_position (position),
    INDEX idx_user_id (user_id),
    INDEX idx_snapshot_position (snapshot_date, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

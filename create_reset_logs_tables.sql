-- Tabela de logs de reset do check-in
CREATE TABLE IF NOT EXISTS checkin_reset_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reset_type VARCHAR(50) NOT NULL DEFAULT 'manual',
    triggered_by VARCHAR(100) NOT NULL,
    reset_datetime DATETIME,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reset_type (reset_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de logs de reset da roleta
CREATE TABLE IF NOT EXISTS spin_reset_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reset_type VARCHAR(50) NOT NULL DEFAULT 'manual',
    triggered_by VARCHAR(100) NOT NULL,
    spins_deleted INT DEFAULT 0,
    reset_datetime DATETIME,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reset_type (reset_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de logs de reset do ranking
CREATE TABLE IF NOT EXISTS ranking_reset_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reset_type VARCHAR(50) NOT NULL DEFAULT 'manual',
    triggered_by VARCHAR(100) NOT NULL,
    users_affected INT DEFAULT 0,
    reset_datetime DATETIME,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reset_type (reset_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

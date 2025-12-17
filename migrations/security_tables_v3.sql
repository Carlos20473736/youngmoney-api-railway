-- =====================================================
-- TABELAS DE SEGURANÇA V3 - YoungMoney
-- Sistema de proteção contra ataques
-- =====================================================

-- Tabela de Rate Limiting
CREATE TABLE IF NOT EXISTS security_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rate_key VARCHAR(255) NOT NULL,
    window_start BIGINT NOT NULL,
    request_count INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rate (rate_key, window_start),
    INDEX idx_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Nonces usados (anti-replay)
CREATE TABLE IF NOT EXISTS security_used_nonces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nonce_value VARCHAR(255) NOT NULL UNIQUE,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    device_id VARCHAR(100),
    INDEX idx_nonce (nonce_value),
    INDEX idx_used_at (used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de IPs bloqueados
CREATE TABLE IF NOT EXISTS security_blocked_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    blocked_until TIMESTAMP NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address),
    INDEX idx_blocked_until (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Devices bloqueados
CREATE TABLE IF NOT EXISTS security_blocked_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(100) NOT NULL UNIQUE,
    blocked_until TIMESTAMP NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_device (device_id),
    INDEX idx_blocked_until (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Violações de segurança
CREATE TABLE IF NOT EXISTS security_violations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    device_id VARCHAR(100),
    violation_type VARCHAR(50) NOT NULL,
    headers_json TEXT,
    endpoint VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address),
    INDEX idx_device (device_id),
    INDEX idx_type (violation_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Log de requisições
CREATE TABLE IF NOT EXISTS security_request_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    device_id VARCHAR(100),
    endpoint VARCHAR(255),
    success TINYINT(1) DEFAULT 0,
    response_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address),
    INDEX idx_device (device_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Challenges ativos
CREATE TABLE IF NOT EXISTS security_challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challenge_value VARCHAR(64) NOT NULL UNIQUE,
    device_id VARCHAR(100),
    ip_address VARCHAR(45),
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_challenge (challenge_value),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Device Fingerprints conhecidos
CREATE TABLE IF NOT EXISTS security_device_fingerprints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    device_id VARCHAR(100),
    fingerprint_hash VARCHAR(64) NOT NULL,
    device_info JSON,
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    trust_score INT DEFAULT 50,
    INDEX idx_user (user_id),
    INDEX idx_device (device_id),
    INDEX idx_fingerprint (fingerprint_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Evento para limpar nonces antigos (executar diariamente)
DROP EVENT IF EXISTS cleanup_old_nonces;
CREATE EVENT cleanup_old_nonces
ON SCHEDULE EVERY 1 DAY
DO
BEGIN
    -- Remover nonces com mais de 24 horas
    DELETE FROM security_used_nonces WHERE used_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
    
    -- Remover rate limits antigos
    DELETE FROM security_rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);
    
    -- Remover bloqueios expirados
    DELETE FROM security_blocked_ips WHERE blocked_until < NOW();
    DELETE FROM security_blocked_devices WHERE blocked_until < NOW();
    
    -- Remover challenges expirados
    DELETE FROM security_challenges WHERE expires_at < NOW();
    
    -- Remover logs antigos (manter 7 dias)
    DELETE FROM security_request_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    DELETE FROM security_violations WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
END;

-- Garantir que o scheduler está ativo
SET GLOBAL event_scheduler = ON;

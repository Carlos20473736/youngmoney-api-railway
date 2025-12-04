-- ========================================
-- SISTEMA DE RANKING COM RESET AUTOMÁTICO
-- ========================================

-- Tabela para armazenar períodos de ranking
CREATE TABLE IF NOT EXISTS ranking_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_type ENUM('daily', 'weekly', 'monthly') NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('active', 'finished') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_period_type (period_type),
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para armazenar pontos por período
CREATE TABLE IF NOT EXISTS ranking_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    period_id INT NOT NULL,
    points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (period_id) REFERENCES ranking_periods(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_period (user_id, period_id),
    INDEX idx_period_points (period_id, points DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- STORED PROCEDURE: Criar novo período
-- ========================================

DELIMITER //

CREATE PROCEDURE IF NOT EXISTS create_ranking_period(
    IN p_period_type VARCHAR(10)
)
BEGIN
    DECLARE v_start_date DATETIME;
    DECLARE v_end_date DATETIME;
    
    SET v_start_date = NOW();
    
    -- Calcular data de término baseado no tipo
    IF p_period_type = 'daily' THEN
        SET v_end_date = DATE_ADD(DATE(NOW()), INTERVAL 1 DAY);
    ELSEIF p_period_type = 'weekly' THEN
        SET v_end_date = DATE_ADD(DATE(NOW()), INTERVAL 7 DAY);
    ELSEIF p_period_type = 'monthly' THEN
        SET v_end_date = DATE_ADD(DATE(NOW()), INTERVAL 1 MONTH);
    END IF;
    
    -- Inserir novo período
    INSERT INTO ranking_periods (period_type, start_date, end_date, status)
    VALUES (p_period_type, v_start_date, v_end_date, 'active');
    
    SELECT LAST_INSERT_ID() as period_id;
END //

DELIMITER ;

-- ========================================
-- STORED PROCEDURE: Finalizar períodos expirados
-- ========================================

DELIMITER //

CREATE PROCEDURE IF NOT EXISTS finish_expired_periods()
BEGIN
    UPDATE ranking_periods
    SET status = 'finished'
    WHERE status = 'active' AND end_date <= NOW();
    
    SELECT ROW_COUNT() as periods_finished;
END //

DELIMITER ;

-- ========================================
-- STORED PROCEDURE: Obter período ativo
-- ========================================

DELIMITER //

CREATE PROCEDURE IF NOT EXISTS get_active_period(
    IN p_period_type VARCHAR(10)
)
BEGIN
    DECLARE v_period_id INT;
    
    -- Finalizar períodos expirados
    CALL finish_expired_periods();
    
    -- Buscar período ativo
    SELECT id INTO v_period_id
    FROM ranking_periods
    WHERE period_type = p_period_type
      AND status = 'active'
      AND start_date <= NOW()
      AND end_date > NOW()
    ORDER BY id DESC
    LIMIT 1;
    
    -- Se não existir, criar novo
    IF v_period_id IS NULL THEN
        CALL create_ranking_period(p_period_type);
        
        SELECT id INTO v_period_id
        FROM ranking_periods
        WHERE period_type = p_period_type
          AND status = 'active'
        ORDER BY id DESC
        LIMIT 1;
    END IF;
    
    SELECT v_period_id as period_id;
END //

DELIMITER ;

-- ========================================
-- INICIALIZAÇÃO
-- ========================================

-- Criar períodos iniciais
CALL create_ranking_period('daily');
CALL create_ranking_period('weekly');
CALL create_ranking_period('monthly');

-- ========================================
-- VERIFICAÇÃO
-- ========================================

SELECT * FROM ranking_periods ORDER BY id DESC LIMIT 10;

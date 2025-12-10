-- =====================================================
-- PIX Payment System - Database Schema
-- YoungMoney API
-- =====================================================

-- Tabela para armazenar chaves PIX dos usuários
CREATE TABLE IF NOT EXISTS `pix_keys` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `pix_key_type` ENUM('CPF', 'CNPJ', 'Email', 'Telefone', 'Chave Aleatória') NOT NULL,
    `pix_key` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_pix_key` (`pix_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para armazenar pagamentos PIX processados
CREATE TABLE IF NOT EXISTS `pix_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `position` INT NOT NULL COMMENT 'Posição no ranking (1-10)',
    `amount` DECIMAL(10, 2) NOT NULL COMMENT 'Valor em reais',
    `pix_key_type` ENUM('CPF', 'CNPJ', 'Email', 'Telefone', 'Chave Aleatória') NOT NULL,
    `pix_key` VARCHAR(255) NOT NULL,
    `status` ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    `transaction_id` VARCHAR(255) NULL COMMENT 'ID da transação do gateway de pagamento',
    `error_message` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_position` (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para armazenar histórico de ranking (se não existir)
CREATE TABLE IF NOT EXISTS `rankings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `position` INT NOT NULL,
    `daily_points` INT NOT NULL,
    `total_points` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_position` (`position`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Stored Procedures
-- =====================================================

-- Procedure para obter top 10 do ranking
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS `get_top_10_ranking`(IN p_date DATE)
BEGIN
    SELECT 
        u.id,
        u.username,
        u.email,
        r.position,
        r.daily_points,
        r.total_points,
        pk.pix_key_type,
        pk.pix_key
    FROM rankings r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN pix_keys pk ON u.id = pk.user_id
    WHERE DATE(r.created_at) = p_date
    ORDER BY r.position ASC
    LIMIT 10;
END //
DELIMITER ;

-- Procedure para processar pagamentos do top 10
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS `process_top_10_payments`(IN p_date DATE)
BEGIN
    DECLARE v_user_id INT;
    DECLARE v_position INT;
    DECLARE v_amount DECIMAL(10, 2);
    DECLARE v_pix_key_type VARCHAR(50);
    DECLARE v_pix_key VARCHAR(255);
    DECLARE v_done INT DEFAULT FALSE;
    
    DECLARE cursor_top_10 CURSOR FOR
        SELECT 
            u.id,
            r.position,
            CASE 
                WHEN r.position = 1 THEN 20.00
                WHEN r.position = 2 THEN 10.00
                WHEN r.position = 3 THEN 5.00
                ELSE 1.00
            END as amount,
            pk.pix_key_type,
            pk.pix_key
        FROM rankings r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN pix_keys pk ON u.id = pk.user_id
        WHERE DATE(r.created_at) = p_date
        ORDER BY r.position ASC
        LIMIT 10;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;
    
    OPEN cursor_top_10;
    
    read_loop: LOOP
        FETCH cursor_top_10 INTO v_user_id, v_position, v_amount, v_pix_key_type, v_pix_key;
        
        IF v_done THEN
            LEAVE read_loop;
        END IF;
        
        -- Verificar se usuário tem chave PIX
        IF v_pix_key IS NOT NULL THEN
            -- Inserir pagamento
            INSERT INTO pix_payments 
            (user_id, position, amount, pix_key_type, pix_key, status, created_at)
            VALUES (v_user_id, v_position, v_amount, v_pix_key_type, v_pix_key, 'pending', NOW());
        END IF;
    END LOOP;
    
    CLOSE cursor_top_10;
END //
DELIMITER ;

-- =====================================================
-- Views
-- =====================================================

-- View para relatório de pagamentos do dia
CREATE OR REPLACE VIEW `vw_daily_payments_report` AS
SELECT 
    DATE(pp.created_at) as payment_date,
    COUNT(*) as total_payments,
    SUM(pp.amount) as total_amount,
    SUM(CASE WHEN pp.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
    SUM(CASE WHEN pp.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN pp.status = 'failed' THEN 1 ELSE 0 END) as failed_count
FROM pix_payments pp
GROUP BY DATE(pp.created_at)
ORDER BY payment_date DESC;

-- View para pagamentos por usuário
CREATE OR REPLACE VIEW `vw_user_payments` AS
SELECT 
    u.id,
    u.username,
    u.email,
    COUNT(pp.id) as total_payments,
    SUM(pp.amount) as total_earned,
    SUM(CASE WHEN pp.status = 'completed' THEN pp.amount ELSE 0 END) as amount_received,
    MAX(pp.created_at) as last_payment_date
FROM users u
LEFT JOIN pix_payments pp ON u.id = pp.user_id
GROUP BY u.id, u.username, u.email;

-- =====================================================
-- Índices adicionais para performance
-- =====================================================

CREATE INDEX IF NOT EXISTS `idx_pix_payments_user_status` ON `pix_payments`(`user_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_pix_payments_date_status` ON `pix_payments`(`created_at`, `status`);
CREATE INDEX IF NOT EXISTS `idx_rankings_date_position` ON `rankings`(`created_at`, `position`);

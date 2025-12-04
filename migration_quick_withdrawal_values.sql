-- Migração: Adicionar suporte a valores rápidos de saque configuráveis
-- Data: 2024-11-22
-- Descrição: Cria tabela para armazenar valores rápidos de saque que aparecem no app

-- Criar tabela de valores rápidos
CREATE TABLE IF NOT EXISTS withdrawal_quick_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    value_amount INT NOT NULL COMMENT 'Valor em reais (ex: 10, 20, 50)',
    is_active TINYINT(1) DEFAULT 1 COMMENT '1 = ativo, 0 = inativo',
    display_order INT DEFAULT 0 COMMENT 'Ordem de exibição no app',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_value (value_amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir valores padrão
INSERT INTO withdrawal_quick_values (value_amount, display_order) VALUES
(1, 1),
(10, 2),
(20, 3),
(50, 4),
(100, 5)
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Adicionar índice para performance
CREATE INDEX idx_active_order ON withdrawal_quick_values(is_active, display_order);

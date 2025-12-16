-- Adicionar coluna receipt_url na tabela withdrawals
-- Esta coluna armazena a URL do comprovante de pagamento PIX

ALTER TABLE withdrawals 
ADD COLUMN IF NOT EXISTS receipt_url VARCHAR(500) NULL 
COMMENT 'URL do comprovante de pagamento PIX';

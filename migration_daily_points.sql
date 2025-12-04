-- Adicionar coluna daily_points na tabela users
ALTER TABLE users ADD COLUMN IF NOT EXISTS daily_points INT DEFAULT 0 NOT NULL;

-- Criar índice para melhorar performance do ranking
CREATE INDEX IF NOT EXISTS idx_daily_points ON users(daily_points DESC);

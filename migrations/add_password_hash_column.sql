-- Migration: Adicionar coluna password_hash à tabela users
-- Data: 2026-01-15
-- Descrição: Permite login por email e senha além do login Google

-- Adicionar coluna password_hash (pode ser NULL para usuários que só usam Google)
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) DEFAULT NULL 
AFTER email;

-- Adicionar índice para busca por email (se não existir)
-- CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

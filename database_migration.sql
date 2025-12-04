-- ========================================
-- MIGRAÇÃO DE BANCO DE DADOS - SEGURANÇA V2
-- ========================================

-- Adicionar colunas para armazenar master_seed e session_salt
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS master_seed TEXT DEFAULT NULL COMMENT 'Master seed criptografado (AES-256)',
ADD COLUMN IF NOT EXISTS session_salt VARCHAR(255) DEFAULT NULL COMMENT 'Session salt (base64)',
ADD COLUMN IF NOT EXISTS salt_updated_at DATETIME DEFAULT NULL COMMENT 'Última atualização do salt';

-- Criar índice para busca rápida por device_id
CREATE INDEX IF NOT EXISTS idx_device_id ON users(device_id);

-- Criar índice para busca rápida por email
CREATE INDEX IF NOT EXISTS idx_email ON users(email);

-- ========================================
-- VERIFICAÇÃO
-- ========================================

-- Verificar se as colunas foram adicionadas
DESCRIBE users;

-- Contar usuários que já têm seed
SELECT COUNT(*) as users_with_seed FROM users WHERE master_seed IS NOT NULL;

-- ========================================
-- LIMPEZA (OPCIONAL - USE COM CUIDADO!)
-- ========================================

-- Para resetar seeds de todos os usuários (forçar re-login)
-- DESCOMENTE APENAS SE NECESSÁRIO:
-- UPDATE users SET master_seed = NULL, session_salt = NULL, salt_updated_at = NULL;

-- ========================================
-- NOTAS
-- ========================================

/*
1. master_seed é armazenado CRIPTOGRAFADO com a chave do servidor (SERVER_ENCRYPTION_KEY)
2. session_salt é armazenado em texto plano (base64)
3. Ambos são gerados no login e enviados ao app
4. O app armazena localmente de forma segura (EncryptedSharedPreferences)
5. A cada requisição, a chave é derivada usando HKDF(master_seed, session_salt, timestamp_window)
6. A chave NUNCA é transmitida pela rede
*/

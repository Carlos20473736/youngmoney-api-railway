-- =====================================================
-- Script para adicionar configuração app_update_require_version
-- 
-- Esta configuração força o APK a enviar a versão em todas as requisições.
-- Se o APK não enviar a versão, a requisição será BLOQUEADA.
-- =====================================================

-- Adicionar configuração app_update_require_version (1 = ativo, 0 = desativado)
INSERT INTO system_settings (setting_key, setting_value, description, created_at, updated_at) 
VALUES (
    'app_update_require_version', 
    '1', 
    'Se ativo (1), bloqueia requisições de APKs que não enviam a versão. Isso força usuários com versões antigas a atualizarem.',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    description = VALUES(description),
    updated_at = NOW();

-- Verificar se as outras configurações existem, se não, criar
INSERT INTO system_settings (setting_key, setting_value, description, created_at, updated_at) 
VALUES (
    'app_update_enabled', 
    '1', 
    'Ativa/desativa a verificação de atualização do app',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

INSERT INTO system_settings (setting_key, setting_value, description, created_at, updated_at) 
VALUES (
    'app_update_force', 
    '1', 
    'Se ativo (1), força a atualização bloqueando versões antigas',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

INSERT INTO system_settings (setting_key, setting_value, description, created_at, updated_at) 
VALUES (
    'app_update_min_version', 
    '1.0.0', 
    'Versão mínima exigida do app',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

INSERT INTO system_settings (setting_key, setting_value, description, created_at, updated_at) 
VALUES (
    'app_update_message', 
    'Uma nova versão do app está disponível. Por favor, atualize para continuar.', 
    'Mensagem exibida quando atualização é necessária',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Mostrar configurações atuais
SELECT setting_key, setting_value, description FROM system_settings 
WHERE setting_key LIKE 'app_update%' OR setting_key LIKE 'maintenance%'
ORDER BY setting_key;

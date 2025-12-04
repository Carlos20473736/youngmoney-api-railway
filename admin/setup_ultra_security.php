<?php
/**
 * Setup Ultra Security Tables
 * Cria tabelas necessárias para o sistema de segurança ultra avançado
 */

require_once __DIR__ . '/../database.php';

$conn = getDbConnection();

try {
    echo "🔐 Criando tabelas do Ultra Security System...\n\n";
    
    // 1. Tabela de desafios
    $sql1 = "CREATE TABLE IF NOT EXISTS security_challenges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id VARCHAR(255) UNIQUE NOT NULL,
        challenge VARCHAR(255) NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_device (device_id),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql1)) {
        echo "✅ Tabela 'security_challenges' criada\n";
    }
    
    // 2. Tabela de blacklist de dispositivos
    $sql2 = "CREATE TABLE IF NOT EXISTS device_blacklist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id VARCHAR(255) UNIQUE NOT NULL,
        reason TEXT,
        expires_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_device (device_id),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql2)) {
        echo "✅ Tabela 'device_blacklist' criada\n";
    }
    
    // 3. Tabela de log de requisições (melhorada)
    $sql3 = "CREATE TABLE IF NOT EXISTS request_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id VARCHAR(255),
        user_id INT NULL,
        ip VARCHAR(45),
        endpoint VARCHAR(500),
        success TINYINT(1) DEFAULT 1,
        error_message TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_device (device_id),
        INDEX idx_user (user_id),
        INDEX idx_ip (ip),
        INDEX idx_created (created_at),
        INDEX idx_success (success)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql3)) {
        echo "✅ Tabela 'request_log' criada\n";
    }
    
    // 4. Tabela de dispositivos confiáveis
    $sql4 = "CREATE TABLE IF NOT EXISTS trusted_devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        device_id VARCHAR(255) NOT NULL,
        device_name VARCHAR(255),
        first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        request_count INT DEFAULT 0,
        UNIQUE KEY unique_user_device (user_id, device_id),
        INDEX idx_user (user_id),
        INDEX idx_device (device_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql4)) {
        echo "✅ Tabela 'trusted_devices' criada\n";
    }
    
    // 5. Limpar dados antigos (cleanup)
    echo "\n🧹 Limpando dados antigos...\n";
    
    // Limpar desafios expirados
    $conn->query("DELETE FROM security_challenges WHERE expires_at < NOW()");
    echo "✅ Desafios expirados removidos\n";
    
    // Limpar blacklist expirada
    $conn->query("DELETE FROM device_blacklist WHERE expires_at IS NOT NULL AND expires_at < NOW()");
    echo "✅ Blacklist expirada limpa\n";
    
    // Limpar logs antigos (manter últimos 30 dias)
    $conn->query("DELETE FROM request_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    echo "✅ Logs antigos removidos\n";
    
    // Remover tabelas antigas não usadas
    echo "\n🗑️  Removendo tabelas antigas...\n";
    $conn->query("DROP TABLE IF EXISTS used_request_ids");
    echo "✅ Tabela 'used_request_ids' removida\n";
    
    $conn->query("DROP TABLE IF EXISTS used_xreqs");
    echo "✅ Tabela 'used_xreqs' removida\n";
    
    $conn->query("DROP TABLE IF EXISTS headers_validation_log");
    echo "✅ Tabela 'headers_validation_log' removida\n";
    
    echo "\n✅ ✅ ✅ SETUP CONCLUÍDO COM SUCESSO! ✅ ✅ ✅\n";
    echo "\n📊 Tabelas criadas:\n";
    echo "  1. security_challenges - Desafios criptográficos\n";
    echo "  2. device_blacklist - Dispositivos bloqueados\n";
    echo "  3. request_log - Log de requisições\n";
    echo "  4. trusted_devices - Dispositivos confiáveis\n";
    
    echo "\n🔐 Sistema Ultra Security pronto para uso!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

$conn->close();
?>

<?php
/**
 * Setup Headers Validation Tables
 * 
 * Cria tabelas necessárias para validação de headers:
 * - used_request_ids: Previne replay attacks com Request-ID
 * - used_xreqs: Previne replay attacks com X-Req
 * - headers_validation_log: Log de validações para auditoria
 */

require_once __DIR__ . '/../database.php';

$conn = getDbConnection();

try {
    // 1. Tabela para Request IDs usados (anti-replay)
    $sql1 = "CREATE TABLE IF NOT EXISTS used_request_ids (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id VARCHAR(255) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_request_id (request_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql1)) {
        echo "✅ Tabela 'used_request_ids' criada com sucesso\n";
    }
    
    // 2. Tabela para X-Reqs usados (anti-replay)
    $sql2 = "CREATE TABLE IF NOT EXISTS used_xreqs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        xreq VARCHAR(500) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_xreq (xreq(255)),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql2)) {
        echo "✅ Tabela 'used_xreqs' criada com sucesso\n";
    }
    
    // 3. Tabela de log de validações (auditoria)
    $sql3 = "CREATE TABLE IF NOT EXISTS headers_validation_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        endpoint VARCHAR(255) NOT NULL,
        request_id VARCHAR(255),
        ip VARCHAR(45),
        user_agent TEXT,
        success BOOLEAN NOT NULL,
        errors JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_endpoint (endpoint),
        INDEX idx_success (success),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql3)) {
        echo "✅ Tabela 'headers_validation_log' criada com sucesso\n";
    }
    
    // 4. Criar evento para limpar dados antigos (opcional)
    $sql4 = "CREATE EVENT IF NOT EXISTS cleanup_headers_validation
        ON SCHEDULE EVERY 1 DAY
        DO BEGIN
            DELETE FROM used_request_ids WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);
            DELETE FROM used_xreqs WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);
            DELETE FROM headers_validation_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
        END";
    
    try {
        $conn->query($sql4);
        echo "✅ Evento de limpeza automática criado\n";
    } catch (Exception $e) {
        echo "⚠️ Evento de limpeza não criado (requer privilégios EVENT): " . $e->getMessage() . "\n";
    }
    
    echo "\n✅ Setup concluído com sucesso!\n";
    echo "\n📊 Tabelas criadas:\n";
    echo "  - used_request_ids (anti-replay Request-ID)\n";
    echo "  - used_xreqs (anti-replay X-Req)\n";
    echo "  - headers_validation_log (auditoria)\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

$conn->close();

<?php
/**
 * Security Status Endpoint
 * Retorna estatísticas de segurança para o admin
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../../cors.php';
require_once __DIR__ . '/../../../database.php';

// Verificar se é admin (via header ou token)
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
// TODO: Implementar verificação de admin

try {
    $conn = getDbConnection();
    
    // Estatísticas de violações (últimas 24h)
    $stmt = $conn->query("
        SELECT violation_type, COUNT(*) as count 
        FROM security_violations 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY violation_type
        ORDER BY count DESC
    ");
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // IPs bloqueados ativos
    $stmt = $conn->query("
        SELECT COUNT(*) as count FROM security_blocked_ips 
        WHERE blocked_until > NOW()
    ");
    $blockedIPs = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Devices bloqueados ativos
    $stmt = $conn->query("
        SELECT COUNT(*) as count FROM security_blocked_devices 
        WHERE blocked_until > NOW()
    ");
    $blockedDevices = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Requisições nas últimas 24h
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed
        FROM security_request_log 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $requests = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Top IPs com violações
    $stmt = $conn->query("
        SELECT ip_address, COUNT(*) as count 
        FROM security_violations 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY ip_address
        ORDER BY count DESC
        LIMIT 10
    ");
    $topViolationIPs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'violations_24h' => $violations,
            'blocked_ips' => (int)$blockedIPs,
            'blocked_devices' => (int)$blockedDevices,
            'requests_24h' => [
                'total' => (int)($requests['total'] ?? 0),
                'successful' => (int)($requests['successful'] ?? 0),
                'failed' => (int)($requests['failed'] ?? 0)
            ],
            'top_violation_ips' => $topViolationIPs,
            'security_version' => '3.0.0',
            'timestamp' => time()
        ]
    ]);
    
} catch (Exception $e) {
    // Se tabelas não existem, retornar dados vazios
    echo json_encode([
        'status' => 'success',
        'data' => [
            'violations_24h' => [],
            'blocked_ips' => 0,
            'blocked_devices' => 0,
            'requests_24h' => ['total' => 0, 'successful' => 0, 'failed' => 0],
            'top_violation_ips' => [],
            'security_version' => '3.0.0',
            'message' => 'Security tables not initialized',
            'timestamp' => time()
        ]
    ]);
}

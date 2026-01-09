<?php
/**
 * Debug Cooldowns - Verifica status de cooldowns ativos
 * Endpoint: /admin/debug_cooldowns.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // Configurar timezone
    date_default_timezone_set('America/Sao_Paulo');
    $now = date('Y-m-d H:i:s');
    
    // 1. Verificar cooldowns ativos
    $stmt = $conn->prepare("
        SELECT 
            rc.id,
            rc.user_id,
            u.name,
            u.email,
            rc.position,
            rc.prize_amount,
            rc.cooldown_days,
            rc.cooldown_until,
            rc.reset_date,
            rc.created_at,
            TIMESTAMPDIFF(HOUR, ?, rc.cooldown_until) as hours_remaining,
            TIMESTAMPDIFF(MINUTE, ?, rc.cooldown_until) as minutes_remaining
        FROM ranking_cooldowns rc
        JOIN users u ON rc.user_id = u.id
        WHERE rc.cooldown_until > ?
        ORDER BY rc.cooldown_until ASC
    ");
    $stmt->bind_param("sss", $now, $now, $now);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activeCooldowns = [];
    while ($row = $result->fetch_assoc()) {
        $activeCooldowns[] = $row;
    }
    $stmt->close();
    
    // 2. Verificar cooldowns expirados (histórico)
    $stmt = $conn->prepare("
        SELECT 
            rc.id,
            rc.user_id,
            u.name,
            rc.position,
            rc.cooldown_until,
            rc.reset_date
        FROM ranking_cooldowns rc
        JOIN users u ON rc.user_id = u.id
        WHERE rc.cooldown_until <= ?
        ORDER BY rc.cooldown_until DESC
        LIMIT 10
    ");
    $stmt->bind_param("s", $now);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $expiredCooldowns = [];
    while ($row = $result->fetch_assoc()) {
        $expiredCooldowns[] = $row;
    }
    $stmt->close();
    
    // 3. Verificar usuários no ranking (sem cooldown)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM users u
        LEFT JOIN ranking_cooldowns rc ON u.id = rc.user_id AND rc.cooldown_until > ?
        WHERE u.daily_points > 0 
          AND u.pix_key IS NOT NULL 
          AND u.pix_key != ''
          AND rc.id IS NULL
    ");
    $stmt->bind_param("s", $now);
    $stmt->execute();
    $result = $stmt->get_result();
    $usersInRanking = $result->fetch_assoc()['total'];
    $stmt->close();
    
    // 4. Total de usuários em cooldown
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM ranking_cooldowns
        WHERE cooldown_until > ?
    ");
    $stmt->bind_param("s", $now);
    $stmt->execute();
    $result = $stmt->get_result();
    $usersInCooldown = $result->fetch_assoc()['total'];
    $stmt->close();
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'server_time' => $now,
        'timezone' => 'America/Sao_Paulo',
        'summary' => [
            'users_in_ranking' => (int)$usersInRanking,
            'users_in_cooldown' => (int)$usersInCooldown
        ],
        'active_cooldowns' => $activeCooldowns,
        'expired_cooldowns' => $expiredCooldowns
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

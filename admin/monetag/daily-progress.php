<?php
/**
 * Daily Progress Endpoint - MoniTag Missions
 * GET - Retorna progresso diário de todos os usuários
 * 
 * CORREÇÃO DE TIMEZONE APLICADA:
 * - Usa CONVERT_TZ para converter datas UTC para Brasília
 */

// DEFINIR TIMEZONE NO INÍCIO DO ARQUIVO
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../database.php';

try {
    $conn = getDbConnection();
    
    // Metas diárias (configuráveis)
    $REQUIRED_IMPRESSIONS = 5;
    $REQUIRED_CLICKS = 1;
    
    // Data de hoje no timezone de Brasília
    $today = date('Y-m-d');
    
    // Buscar progresso diário de cada usuário
    // CORREÇÃO: Usar DATE(CONVERT_TZ()) para converter UTC para Brasília
    $stmt = $conn->prepare("
        SELECT 
            u.id as user_id,
            u.name as user_name,
            u.email,
            u.points,
            COALESCE(SUM(CASE WHEN m.event_type = 'impression' AND DATE(CONVERT_TZ(m.created_at, '+00:00', '-03:00')) = ? THEN 1 ELSE 0 END), 0) as impressions_today,
            COALESCE(SUM(CASE WHEN m.event_type = 'click' AND DATE(CONVERT_TZ(m.created_at, '+00:00', '-03:00')) = ? THEN 1 ELSE 0 END), 0) as clicks_today,
            MAX(CASE WHEN DATE(CONVERT_TZ(m.created_at, '+00:00', '-03:00')) = ? THEN m.created_at END) as last_activity
        FROM users u
        LEFT JOIN monetag_events m ON u.id = m.user_id
        GROUP BY u.id, u.name, u.email, u.points
        ORDER BY u.id DESC
        LIMIT 100
    ");
    
    $stmt->bind_param("sss", $today, $today, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    
    while ($row = $result->fetch_assoc()) {
        $impressions = (int)$row['impressions_today'];
        $clicks = (int)$row['clicks_today'];
        
        $impressions_completed = $impressions >= $REQUIRED_IMPRESSIONS;
        $clicks_completed = $clicks >= $REQUIRED_CLICKS;
        $mission_completed = $impressions_completed && $clicks_completed;
        
        $users[] = [
            'user_id' => (int)$row['user_id'],
            'user_name' => $row['user_name'],
            'email' => $row['email'],
            'points' => (int)$row['points'],
            'impressions' => [
                'current' => $impressions,
                'required' => $REQUIRED_IMPRESSIONS,
                'completed' => $impressions_completed,
                'progress' => min(100, ($impressions / $REQUIRED_IMPRESSIONS) * 100)
            ],
            'clicks' => [
                'current' => $clicks,
                'required' => $REQUIRED_CLICKS,
                'completed' => $clicks_completed,
                'progress' => min(100, ($clicks / $REQUIRED_CLICKS) * 100)
            ],
            'mission_completed' => $mission_completed,
            'last_activity' => $row['last_activity']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'users' => $users,
            'total' => count($users),
            'requirements' => [
                'impressions' => $REQUIRED_IMPRESSIONS,
                'clicks' => $REQUIRED_CLICKS
            ],
            'stats' => [
                'completed_today' => count(array_filter($users, fn($u) => $u['mission_completed'])),
                'active_today' => count(array_filter($users, fn($u) => $u['last_activity'] !== null))
            ],
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => 'America/Sao_Paulo'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

<?php
/**
 * MoniTag Progress Endpoint
 * GET - Retorna progresso diário do usuário autenticado
 */

require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';

try {
    // Autenticar usuário
    $user = authenticateUser();
    
    if (!$user) {
        throw new Exception('Usuário não autenticado');
    }
    
    $conn = getDbConnection();
    
    // Buscar progresso de hoje
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions_today,
            SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks_today,
            MAX(created_at) as last_activity
        FROM monetag_events
        WHERE user_id = ? AND DATE(created_at) = CURDATE()
    ");
    
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $impressions = (int)($row['impressions_today'] ?? 0);
    $clicks = (int)($row['clicks_today'] ?? 0);
    
    // Metas
    $REQUIRED_IMPRESSIONS = 5;
    $REQUIRED_CLICKS = 1;
    
    $impressionsCompleted = $impressions >= $REQUIRED_IMPRESSIONS;
    $clicksCompleted = $clicks >= $REQUIRED_CLICKS;
    $missionCompleted = $impressionsCompleted && $clicksCompleted;
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'impressions' => [
                'current' => $impressions,
                'required' => $REQUIRED_IMPRESSIONS,
                'completed' => $impressionsCompleted,
                'progress' => min(100, ($impressions / $REQUIRED_IMPRESSIONS) * 100)
            ],
            'clicks' => [
                'current' => $clicks,
                'required' => $REQUIRED_CLICKS,
                'completed' => $clicksCompleted,
                'progress' => min(100, ($clicks / $REQUIRED_CLICKS) * 100)
            ],
            'mission_completed' => $missionCompleted,
            'last_activity' => $row['last_activity']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

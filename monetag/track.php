<?php
/**
 * MoniTag Event Tracking Endpoint
 * POST - Registra impressões e cliques de anúncios
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
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $eventType = $input['event_type'] ?? null; // 'impression' ou 'click'
    $sessionId = $input['session_id'] ?? null;
    
    if (!$eventType || !in_array($eventType, ['impression', 'click'])) {
        throw new Exception('event_type inválido. Use "impression" ou "click"');
    }
    
    $conn = getDbConnection();
    
    // Obter informações da requisição
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Inserir evento
    $stmt = $conn->prepare("
        INSERT INTO monetag_events 
        (user_id, event_type, session_id, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param('issss', 
        $user['id'],
        $eventType,
        $sessionId,
        $ipAddress,
        $userAgent
    );
    
    $stmt->execute();
    $eventId = $conn->insert_id;
    
    // Buscar progresso atual do usuário hoje
    $stmt2 = $conn->prepare("
        SELECT 
            SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions_today,
            SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks_today
        FROM monetag_events
        WHERE user_id = ? AND DATE(created_at) = CURDATE()
    ");
    
    $stmt2->bind_param('i', $user['id']);
    $stmt2->execute();
    $result = $stmt2->get_result();
    $progress = $result->fetch_assoc();
    
    $impressions = (int)$progress['impressions_today'];
    $clicks = (int)$progress['clicks_today'];
    
    // Metas
    $REQUIRED_IMPRESSIONS = 5;
    $REQUIRED_CLICKS = 1;
    
    $impressionsCompleted = $impressions >= $REQUIRED_IMPRESSIONS;
    $clicksCompleted = $clicks >= $REQUIRED_CLICKS;
    $missionCompleted = $impressionsCompleted && $clicksCompleted;
    
    $stmt->close();
    $stmt2->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'progress' => [
                'impressions' => [
                    'current' => $impressions,
                    'required' => $REQUIRED_IMPRESSIONS,
                    'completed' => $impressionsCompleted
                ],
                'clicks' => [
                    'current' => $clicks,
                    'required' => $REQUIRED_CLICKS,
                    'completed' => $clicksCompleted
                ],
                'mission_completed' => $missionCompleted
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

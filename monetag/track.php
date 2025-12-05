<?php
/**
 * MoniTag Event Tracking Endpoint
 * POST - Registra impressões e cliques de anúncios (SEM AUTENTICAÇÃO)
 */

// CORS MUST be first
require_once __DIR__ . '/../cors.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

function sendSuccess($data = []) {
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Método não permitido', 405);
}

// Obter dados JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    sendError('JSON inválido');
}

$event_type = $data['event_type'] ?? null;
$session_id = $data['session_id'] ?? null;

if (!$event_type || !$session_id) {
    sendError('event_type e session_id são obrigatórios');
}

if (!in_array($event_type, ['impression', 'click'])) {
    sendError('event_type deve ser impression ou click');
}

// Extrair user_id do session_id (formato: userId_timestamp)
$parts = explode('_', $session_id);
$user_id = isset($parts[0]) && is_numeric($parts[0]) ? (int)$parts[0] : null;

if (!$user_id) {
    sendError('session_id inválido');
}

try {
    $conn = getDbConnection();
    
    // Inserir evento
    $stmt = $conn->prepare("
        INSERT INTO monetag_events (user_id, event_type, session_id, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iss", $user_id, $event_type, $session_id);
    $stmt->execute();
    $stmt->close();
    
    // Buscar progresso atualizado do dia
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN event_type = 'impression' THEN 1 END) as impressions,
            COUNT(CASE WHEN event_type = 'click' THEN 1 END) as clicks
        FROM monetag_events
        WHERE user_id = ? AND DATE(created_at) = ?
    ");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $progress = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    // Metas
    $required_impressions = 5;
    $required_clicks = 1;
    
    $response = [
        'event_registered' => true,
        'event_type' => $event_type,
        'progress' => [
            'impressions' => [
                'current' => (int)$progress['impressions'],
                'required' => $required_impressions,
                'completed' => (int)$progress['impressions'] >= $required_impressions
            ],
            'clicks' => [
                'current' => (int)$progress['clicks'],
                'required' => $required_clicks,
                'completed' => (int)$progress['clicks'] >= $required_clicks
            ],
            'all_completed' => (int)$progress['impressions'] >= $required_impressions && (int)$progress['clicks'] >= $required_clicks
        ]
    ];
    
    sendSuccess($response);
    
} catch (Exception $e) {
    error_log("MoniTag Track Error: " . $e->getMessage());
    sendError('Erro ao registrar evento: ' . $e->getMessage(), 500);
}
?>

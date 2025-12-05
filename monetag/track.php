<?php
/**
 * MoniTag Postback Endpoint
 * Recebe postbacks do MoniTag via GET
 * URL: /monetag/track.php?event_type={event_type}&zone_id={zone_id}&sub_id={sub_id}&sub_id2={sub_id2}&click_id={ymid}&revenue={estimated_price}
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

// Log para debug
error_log("MoniTag Postback - Method: " . $_SERVER['REQUEST_METHOD']);
error_log("MoniTag Postback - GET params: " . json_encode($_GET));

// Aceitar GET ou POST
$event_type = $_GET['event_type'] ?? $_POST['event_type'] ?? null;
$zone_id = $_GET['zone_id'] ?? $_POST['zone_id'] ?? null;
$sub_id = $_GET['sub_id'] ?? $_POST['sub_id'] ?? null; // user_id
$sub_id2 = $_GET['sub_id2'] ?? $_POST['sub_id2'] ?? null; // email
$click_id = $_GET['click_id'] ?? $_POST['click_id'] ?? null;
$revenue = $_GET['revenue'] ?? $_POST['revenue'] ?? 0;

error_log("MoniTag Postback - Parsed: event_type=$event_type, sub_id=$sub_id, sub_id2=$sub_id2");

// Validar event_type
if (!$event_type) {
    sendError('event_type é obrigatório');
}

if (!in_array($event_type, ['impression', 'click'])) {
    sendError('event_type deve ser impression ou click');
}

// Validar user_id (sub_id)
$user_id = null;
if ($sub_id && is_numeric($sub_id)) {
    $user_id = (int)$sub_id;
} else {
    error_log("MoniTag Postback - sub_id inválido: $sub_id");
    sendError('sub_id (user_id) é obrigatório e deve ser numérico');
}

// Gerar session_id único
$session_id = $click_id ?? ($user_id . '_' . time());

try {
    $conn = getDbConnection();
    
    // Inserir evento
    $stmt = $conn->prepare("
        INSERT INTO monetag_events (user_id, event_type, session_id, revenue, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $revenue_float = (float)$revenue;
    $stmt->bind_param("issd", $user_id, $event_type, $session_id, $revenue_float);
    $stmt->execute();
    $event_id = $stmt->insert_id;
    $stmt->close();
    
    error_log("MoniTag Postback - Event registered: ID=$event_id, user_id=$user_id, event_type=$event_type");
    
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
        'event_id' => $event_id,
        'event_type' => $event_type,
        'user_id' => $user_id,
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
    
    error_log("MoniTag Postback - Success: " . json_encode($response));
    sendSuccess($response);
    
} catch (Exception $e) {
    error_log("MoniTag Postback Error: " . $e->getMessage());
    sendError('Erro ao registrar evento: ' . $e->getMessage(), 500);
}
?>

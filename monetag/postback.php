<?php
/**
 * MoniTag Postback Endpoint (Simplificado)
 * Recebe postbacks do frontend via GET
 * URL: /monetag/postback.php?type={type}&user_id={user_id}
 * 
 * type: 'impression' ou 'click'
 * user_id: ID do usuário
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
error_log("MoniTag Postback Simple - Method: " . $_SERVER['REQUEST_METHOD']);
error_log("MoniTag Postback Simple - GET params: " . json_encode($_GET));

// Aceitar GET ou POST
$type = $_GET['type'] ?? $_POST['type'] ?? null;
$user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? null;

error_log("MoniTag Postback Simple - Parsed: type=$type, user_id=$user_id");

// Validar type
if (!$type) {
    sendError('type é obrigatório');
}

if (!in_array($type, ['impression', 'click'])) {
    sendError('type deve ser impression ou click');
}

// Validar user_id
if (!$user_id || !is_numeric($user_id)) {
    sendError('user_id é obrigatório e deve ser numérico');
}

$user_id = (int)$user_id;

// Gerar session_id único
$session_id = 'web_' . uniqid() . '_' . time();

try {
    $conn = getDbConnection();
    
    // Inserir evento
    $stmt = $conn->prepare("
        INSERT INTO monetag_events (user_id, event_type, session_id, revenue, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->bind_param("iss", $user_id, $type, $session_id);
    $stmt->execute();
    $event_id = $stmt->insert_id;
    $stmt->close();
    
    error_log("MoniTag Postback Simple - Event registered: ID=$event_id, user_id=$user_id, type=$type");
    
    // Buscar número de impressões e cliques necessários DO USUÁRIO
    $required_impressions = 5;
    $required_clicks = 1;
    
    $user_settings_stmt = $conn->prepare("
        SELECT required_impressions, required_clicks FROM user_required_impressions 
        WHERE user_id = ?
    ");
    $user_settings_stmt->bind_param("i", $user_id);
    $user_settings_stmt->execute();
    $user_settings_result = $user_settings_stmt->get_result();
    
    if ($user_row = $user_settings_result->fetch_assoc()) {
        $required_impressions = (int)$user_row['required_impressions'];
        $required_clicks = (int)($user_row['required_clicks'] ?? 1);
    }
    $user_settings_stmt->close();
    
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
    
    $response = [
        'event_registered' => true,
        'event_id' => $event_id,
        'event_type' => $type,
        'user_id' => $user_id,
        'session_id' => $session_id,
        'progress' => [
            'impressions' => (int)$progress['impressions'],
            'clicks' => (int)$progress['clicks'],
            'required_impressions' => $required_impressions,
            'required_clicks' => $required_clicks,
            'impressions_completed' => (int)$progress['impressions'] >= $required_impressions,
            'clicks_completed' => (int)$progress['clicks'] >= $required_clicks,
            'all_completed' => (int)$progress['impressions'] >= $required_impressions && (int)$progress['clicks'] >= $required_clicks
        ]
    ];
    
    error_log("MoniTag Postback Simple - Success: " . json_encode($response));
    sendSuccess($response);
    
} catch (Exception $e) {
    error_log("MoniTag Postback Simple Error: " . $e->getMessage());
    sendError('Erro ao registrar evento: ' . $e->getMessage(), 500);
}
?>

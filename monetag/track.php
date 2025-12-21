<?php
/**
 * MoniTag Postback Endpoint
 * Recebe postbacks do MoniTag via GET
 * URL: /monetag/track.php?event_type={event_type}&zone_id={zone_id}&sub_id={sub_id}&sub_id2={sub_id2}&ymid={ymid}&revenue={estimated_price}&request_var={request_var}
 * 
 * event_type: 'impression' (anúncio exibido) ou 'click' (anúncio clicado - NÃO é clique no botão de fechar)
 * zone_id: ID da zona de anúncios MoniTag
 * sub_id: user_id (numérico)
 * sub_id2: email do usuário
 * ymid: ID único da sessão do anúncio
 * revenue: valor estimado do clique/impressão
 * request_var: parâmetro de validação da MoniTag
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
$ymid = $_GET['ymid'] ?? $_POST['ymid'] ?? null; // click_id / session_id
$revenue = $_GET['revenue'] ?? $_POST['revenue'] ?? 0;
$request_var = $_GET['request_var'] ?? $_POST['request_var'] ?? null;

error_log("MoniTag Postback - Parsed: event_type=$event_type, sub_id=$sub_id, sub_id2=$sub_id2, ymid=$ymid, request_var=$request_var");

// Validar event_type
if (!$event_type) {
    sendError('event_type é obrigatório');
}

if (!in_array($event_type, ['impression', 'click'])) {
    sendError('event_type deve ser impression ou click');
}

// Validar zone_id
if (!$zone_id) {
    sendError('zone_id é obrigatório');
}

// Validar user_id (sub_id)
$user_id = null;
if ($sub_id && is_numeric($sub_id)) {
    $user_id = (int)$sub_id;
} else {
    error_log("MoniTag Postback - sub_id inválido: $sub_id");
    sendError('sub_id (user_id) é obrigatório e deve ser numérico');
}

// Validar ymid (session_id)
if (!$ymid) {
    error_log("MoniTag Postback - ymid não fornecido");
    sendError('ymid (session_id) é obrigatório');
}

// Gerar session_id único baseado em ymid
$session_id = $ymid;

try {
    $conn = getDbConnection();
    
    // Verificar se este evento já foi registrado (evitar duplicatas)
    $check_stmt = $conn->prepare("
        SELECT id FROM monetag_events 
        WHERE user_id = ? AND event_type = ? AND session_id = ? 
        LIMIT 1
    ");
    $check_stmt->bind_param("iss", $user_id, $event_type, $session_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Evento já foi registrado
        error_log("MoniTag Postback - Evento duplicado detectado: user_id=$user_id, event_type=$event_type, session_id=$session_id");
        $check_stmt->close();
        $conn->close();
        sendSuccess(['event_registered' => false, 'message' => 'Evento já foi registrado']);
    }
    
    $check_stmt->close();
    
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
    
    error_log("MoniTag Postback - Event registered: ID=$event_id, user_id=$user_id, event_type=$event_type, zone_id=$zone_id");
    
    // Buscar número de impressões necessárias do banco (randomizado)
    $required_impressions = 5; // valor padrão
    $required_clicks = 1; // fixo
    
    $settings_stmt = $conn->prepare("
        SELECT setting_value FROM roulette_settings 
        WHERE setting_key = 'monetag_required_impressions'
    ");
    $settings_stmt->execute();
    $settings_result = $settings_stmt->get_result();
    if ($settings_row = $settings_result->fetch_assoc()) {
        $required_impressions = (int)$settings_row['setting_value'];
    }
    $settings_stmt->close();
    
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
        'event_type' => $event_type,
        'user_id' => $user_id,
        'session_id' => $session_id,
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

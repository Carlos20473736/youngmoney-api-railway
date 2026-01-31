<?php
/**
 * MoniTag Postback Endpoint (CORRIGIDO v2.0)
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
 * 
 * CORREÇÕES APLICADAS:
 * 1. Timezone padronizado para America/Sao_Paulo
 * 2. Validação de limite diário ANTES de inserir
 * 3. Logs de debug melhorados
 * 4. FIX v2.0: Corrigido problema de timezone entre PHP e MySQL
 *    - Agora usa CONVERT_TZ() para converter UTC do MySQL para BRT
 *    - Isso resolve o problema das 21h às meia-noite onde as datas não batiam
 */

// DEFINIR TIMEZONE NO INÍCIO DO ARQUIVO
date_default_timezone_set('America/Sao_Paulo');

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
error_log("MoniTag Track - Method: " . $_SERVER['REQUEST_METHOD'] . " - Time: " . date('Y-m-d H:i:s'));
error_log("MoniTag Track - GET params: " . json_encode($_GET));

// Aceitar GET ou POST
$event_type = $_GET['event_type'] ?? $_POST['event_type'] ?? null;
$zone_id = $_GET['zone_id'] ?? $_POST['zone_id'] ?? null;
$sub_id = $_GET['sub_id'] ?? $_POST['sub_id'] ?? null; // user_id
$sub_id2 = $_GET['sub_id2'] ?? $_POST['sub_id2'] ?? null; // email
$ymid = $_GET['ymid'] ?? $_POST['ymid'] ?? null; // click_id / session_id
$revenue = $_GET['revenue'] ?? $_POST['revenue'] ?? 0;
$request_var = $_GET['request_var'] ?? $_POST['request_var'] ?? null;

error_log("MoniTag Track - Parsed: event_type=$event_type, sub_id=$sub_id, sub_id2=$sub_id2, ymid=$ymid, request_var=$request_var");

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
    error_log("MoniTag Track - sub_id inválido: $sub_id");
    sendError('sub_id (user_id) é obrigatório e deve ser numérico');
}

// Validar ymid (session_id)
if (!$ymid) {
    error_log("MoniTag Track - ymid não fornecido");
    sendError('ymid (session_id) é obrigatório');
}

// Gerar session_id único baseado em ymid
$session_id = $ymid;

try {
    $conn = getDbConnection();
    
    // ========================================
    // CONFIGURAR TIMEZONE DO MYSQL PARA BRASÍLIA
    // Isso garante que NOW() e DATE() usem o mesmo timezone do PHP
    // ========================================
    $conn->query("SET time_zone = '-03:00'");
    
    // ========================================
    // BUSCAR LIMITES DO USUÁRIO
    // ========================================
    $required_impressions = 5; // valor padrão
    $required_clicks = 1; // fixo
    
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
    
    // ========================================
    // VERIFICAR LIMITE DIÁRIO ANTES DE INSERIR
    // FIX: Usar DATE(CONVERT_TZ(created_at, '+00:00', '-03:00')) para converter UTC para BRT
    // ========================================
    $today = date('Y-m-d');
    
    $check_limit_stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM monetag_events 
        WHERE user_id = ? AND event_type = ? AND DATE(CONVERT_TZ(created_at, '+00:00', '-03:00')) = ?
    ");
    $check_limit_stmt->bind_param("iss", $user_id, $event_type, $today);
    $check_limit_stmt->execute();
    $limit_result = $check_limit_stmt->get_result();
    $current_count = (int)$limit_result->fetch_assoc()['total'];
    $check_limit_stmt->close();
    
    // Definir limite baseado no tipo
    $limit = ($event_type === 'click') ? $required_clicks : $required_impressions;
    
    // Se já atingiu o limite, retornar sucesso mas sem registrar
    if ($current_count >= $limit) {
        error_log("MoniTag Track - Limite diário atingido: user_id=$user_id, event_type=$event_type, count=$current_count, limit=$limit");
        
        // Buscar progresso atual para retornar
        $stmt = $conn->prepare("
            SELECT 
                COUNT(CASE WHEN event_type = 'impression' THEN 1 END) as impressions,
                COUNT(CASE WHEN event_type = 'click' THEN 1 END) as clicks
            FROM monetag_events
            WHERE user_id = ? AND DATE(CONVERT_TZ(created_at, '+00:00', '-03:00')) = ?
        ");
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $progress = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        sendSuccess([
            'event_registered' => false,
            'message' => 'Limite diário já atingido para ' . $event_type,
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
        ]);
    }
    
    // ========================================
    // VERIFICAR DUPLICATA POR SESSION_ID
    // ========================================
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
        error_log("MoniTag Track - Evento duplicado detectado: user_id=$user_id, event_type=$event_type, session_id=$session_id");
        $check_stmt->close();
        $conn->close();
        sendSuccess(['event_registered' => false, 'message' => 'Evento já foi registrado']);
    }
    
    $check_stmt->close();
    
    // ========================================
    // INSERIR EVENTO (LIMITE NÃO ATINGIDO E NÃO É DUPLICATA)
    // NOW() agora usa timezone -03:00 configurado acima
    // ========================================
    $stmt = $conn->prepare("
        INSERT INTO monetag_events (user_id, event_type, session_id, revenue, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $revenue_float = (float)$revenue;
    $stmt->bind_param("issd", $user_id, $event_type, $session_id, $revenue_float);
    $stmt->execute();
    $event_id = $stmt->insert_id;
    $stmt->close();
    
    error_log("MoniTag Track - Event registered: ID=$event_id, user_id=$user_id, event_type=$event_type, zone_id=$zone_id, time=" . date('Y-m-d H:i:s'));
    
    // Buscar progresso atualizado do dia
    // FIX: Usar CONVERT_TZ para garantir que a data seja comparada corretamente
    $stmt = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN event_type = 'impression' THEN 1 END) as impressions,
            COUNT(CASE WHEN event_type = 'click' THEN 1 END) as clicks
        FROM monetag_events
        WHERE user_id = ? AND DATE(CONVERT_TZ(created_at, '+00:00', '-03:00')) = ?
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
    
    error_log("MoniTag Track - Success: " . json_encode($response));
    sendSuccess($response);
    
} catch (Exception $e) {
    error_log("MoniTag Track Error: " . $e->getMessage());
    sendError('Erro ao registrar evento: ' . $e->getMessage(), 500);
}
?>

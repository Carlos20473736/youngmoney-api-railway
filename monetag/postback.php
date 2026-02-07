<?php
/**
 * MoniTag Postback Endpoint (v3 - APENAS IMPRESSÕES)
 * Recebe postbacks do frontend via GET
 * URL: /monetag/postback.php?type={type}&user_id={user_id}
 * 
 * type: 'impression'
 * user_id: ID do usuário
 * 
 * Lógica de cliques removida completamente
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
error_log("MoniTag Postback - Method: " . $_SERVER['REQUEST_METHOD'] . " - Time: " . date('Y-m-d H:i:s'));
error_log("MoniTag Postback - GET params: " . json_encode($_GET));

// Aceitar GET ou POST
$type = $_GET['type'] ?? $_POST['type'] ?? null;
$user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? null;

error_log("MoniTag Postback - Parsed: type=$type, user_id=$user_id");

// Validar type - APENAS IMPRESSÕES
if (!$type) {
    sendError('type é obrigatório');
}

if ($type !== 'impression') {
    sendError('type deve ser impression');
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
    
    // ========================================
    // BUSCAR LIMITES DO USUÁRIO - APENAS IMPRESSÕES
    // ========================================
    $required_impressions = 10;
    
    $user_settings_stmt = $conn->prepare("
        SELECT required_impressions FROM user_required_impressions 
        WHERE user_id = ?
    ");
    $user_settings_stmt->bind_param("i", $user_id);
    $user_settings_stmt->execute();
    $user_settings_result = $user_settings_stmt->get_result();
    
    if ($user_row = $user_settings_result->fetch_assoc()) {
        $required_impressions = (int)$user_row['required_impressions'];
    }
    $user_settings_stmt->close();
    
    // ========================================
    // VERIFICAR LIMITE DIÁRIO ANTES DE INSERIR
    // ========================================
    $today = date('Y-m-d');
    
    $check_limit_stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM monetag_events 
        WHERE user_id = ? AND event_type = 'impression' AND DATE(created_at) = ?
    ");
    $check_limit_stmt->bind_param("is", $user_id, $today);
    $check_limit_stmt->execute();
    $limit_result = $check_limit_stmt->get_result();
    $current_count = (int)$limit_result->fetch_assoc()['total'];
    $check_limit_stmt->close();
    
    // Se já atingiu o limite, retornar sucesso mas sem registrar
    if ($current_count >= $required_impressions) {
        error_log("MoniTag Postback - Limite diário atingido: user_id=$user_id, count=$current_count, limit=$required_impressions");
        
        $conn->close();
        
        sendSuccess([
            'event_registered' => false,
            'message' => 'Limite diário já atingido para impressões',
            'event_type' => $type,
            'user_id' => $user_id,
            'progress' => [
                'impressions' => $current_count,
                'clicks' => 0,
                'required_impressions' => $required_impressions,
                'required_clicks' => 0,
                'impressions_completed' => true,
                'clicks_completed' => true,
                'all_completed' => true
            ]
        ]);
    }
    
    // ========================================
    // INSERIR EVENTO (LIMITE NÃO ATINGIDO)
    // ========================================
    $stmt = $conn->prepare("
        INSERT INTO monetag_events (user_id, event_type, session_id, revenue, created_at)
        VALUES (?, 'impression', ?, 0, NOW())
    ");
    $stmt->bind_param("is", $user_id, $session_id);
    $stmt->execute();
    $event_id = $stmt->insert_id;
    $stmt->close();
    
    error_log("MoniTag Postback - Event registered: ID=$event_id, user_id=$user_id, time=" . date('Y-m-d H:i:s'));
    
    // Buscar progresso atualizado do dia
    $stmt = $conn->prepare("
        SELECT COUNT(*) as impressions
        FROM monetag_events
        WHERE user_id = ? AND event_type = 'impression' AND DATE(created_at) = ?
    ");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $progress = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    $impressions = (int)$progress['impressions'];
    
    $response = [
        'event_registered' => true,
        'event_id' => $event_id,
        'event_type' => 'impression',
        'user_id' => $user_id,
        'session_id' => $session_id,
        'progress' => [
            'impressions' => $impressions,
            'clicks' => 0,
            'required_impressions' => $required_impressions,
            'required_clicks' => 0,
            'impressions_completed' => $impressions >= $required_impressions,
            'clicks_completed' => true,
            'all_completed' => $impressions >= $required_impressions
        ]
    ];
    
    error_log("MoniTag Postback - Success: " . json_encode($response));
    sendSuccess($response);
    
} catch (Exception $e) {
    error_log("MoniTag Postback Error: " . $e->getMessage());
    sendError('Erro ao registrar evento: ' . $e->getMessage(), 500);
}
?>

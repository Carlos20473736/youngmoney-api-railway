<?php
/**
 * MoniTag Progress Endpoint (v4 - IMPRESSÕES + CLIQUES)
 * GET - Retorna progresso diário do usuário (SEM AUTENTICAÇÃO)
 * 
 * Requisitos fixos: 20 impressões + 2 cliques
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

// Apenas GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Método não permitido', 405);
}

$user_id = $_GET['user_id'] ?? null;

if (!$user_id || !is_numeric($user_id)) {
    sendError('user_id é obrigatório e deve ser numérico');
}

$user_id = (int)$user_id;

error_log("MoniTag Progress - user_id=$user_id, time=" . date('Y-m-d H:i:s'));

try {
    $conn = getDbConnection();
    
    // Valores fixos para todos os usuários (sem randomização)
    $required_impressions = 20;
    $required_clicks = 2;
    
    // Buscar progresso do dia - IMPRESSÕES E CLIQUES
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
    
    $impressions = (int)$progress['impressions'];
    $clicks = (int)$progress['clicks'];
    
    $impressions_completed = $impressions >= $required_impressions;
    $clicks_completed = $clicks >= $required_clicks;
    
    $response = [
        'impressions' => $impressions,
        'clicks' => $clicks,
        'required_impressions' => $required_impressions,
        'required_clicks' => $required_clicks,
        'impressions_completed' => $impressions_completed,
        'clicks_completed' => $clicks_completed,
        'all_completed' => $impressions_completed && $clicks_completed,
        'server_time' => date('Y-m-d H:i:s'),
        'timezone' => 'America/Sao_Paulo'
    ];
    
    error_log("MoniTag Progress - Response: " . json_encode($response));
    sendSuccess($response);
    
} catch (Exception $e) {
    error_log("MoniTag Progress Error: " . $e->getMessage());
    sendError('Erro ao buscar progresso: ' . $e->getMessage(), 500);
}
?>

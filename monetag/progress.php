<?php
/**
 * MoniTag Progress Endpoint (CORRIGIDO)
 * GET - Retorna progresso diário do usuário (SEM AUTENTICAÇÃO)
 * 
 * Agora cada usuário tem seu próprio número de impressões (5-12) e cliques (1) necessários
 * 
 * CORREÇÕES APLICADAS:
 * 1. Timezone padronizado para America/Sao_Paulo
 * 2. Range de impressões corrigido para 5-12
 * 3. Logs de debug melhorados
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
    
    // Buscar número de impressões e cliques necessários DO USUÁRIO (randomizado por usuário)
    $required_impressions = 5; // valor padrão
    $required_clicks = 1; // valor padrão (FIXO)
    
    // Primeiro, tentar buscar da tabela user_required_impressions
    $user_settings_stmt = $conn->prepare("
        SELECT required_impressions, required_clicks FROM user_required_impressions 
        WHERE user_id = ?
    ");
    $user_settings_stmt->bind_param("i", $user_id);
    $user_settings_stmt->execute();
    $user_settings_result = $user_settings_stmt->get_result();
    
    if ($user_row = $user_settings_result->fetch_assoc()) {
        // Usuário tem valor personalizado
        $required_impressions = (int)$user_row['required_impressions'];
        $required_clicks = (int)($user_row['required_clicks'] ?? 1);
    } else {
        // Usuário não tem valor ainda, criar um aleatório (impressões: 5-12, cliques: 1)
        $required_impressions = rand(5, 12); // CORRIGIDO: 5-12
        $required_clicks = 1; // FIXO em 1 clique
        
        $insert_stmt = $conn->prepare("
            INSERT INTO user_required_impressions (user_id, required_impressions, required_clicks)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                required_impressions = VALUES(required_impressions),
                required_clicks = VALUES(required_clicks)
        ");
        $insert_stmt->bind_param("iii", $user_id, $required_impressions, $required_clicks);
        $insert_stmt->execute();
        $insert_stmt->close();
        
        error_log("MoniTag Progress - Novo usuário: impressions=$required_impressions, clicks=$required_clicks");
    }
    $user_settings_stmt->close();
    
    // Buscar progresso do dia
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
        'impressions' => (int)$progress['impressions'],
        'clicks' => (int)$progress['clicks'],
        'required_impressions' => $required_impressions,
        'required_clicks' => $required_clicks,
        'impressions_completed' => (int)$progress['impressions'] >= $required_impressions,
        'clicks_completed' => (int)$progress['clicks'] >= $required_clicks,
        'all_completed' => (int)$progress['impressions'] >= $required_impressions && (int)$progress['clicks'] >= $required_clicks,
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

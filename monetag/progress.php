<?php
/**
 * MoniTag Progress Endpoint
 * GET - Retorna progresso diário do usuário (SEM AUTENTICAÇÃO)
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

// Apenas GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Método não permitido', 405);
}

$user_id = $_GET['user_id'] ?? null;

if (!$user_id || !is_numeric($user_id)) {
    sendError('user_id é obrigatório e deve ser numérico');
}

$user_id = (int)$user_id;

try {
    $conn = getDbConnection();
    
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
        'all_completed' => (int)$progress['impressions'] >= $required_impressions && (int)$progress['clicks'] >= $required_clicks
    ];
    
    sendSuccess($response);
    
} catch (Exception $e) {
    error_log("MoniTag Progress Error: " . $e->getMessage());
    sendError('Erro ao buscar progresso: ' . $e->getMessage(), 500);
}
?>

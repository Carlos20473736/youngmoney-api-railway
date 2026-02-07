<?php
/**
 * Endpoint para randomizar o número de impressões necessárias (v3 - APENAS IMPRESSÕES)
 * Deve ser chamado quando o ranking é resetado
 * 
 * GET /monetag/randomize_impressions.php
 * 
 * Randomiza:
 * - Impressões: 5 a 12
 * 
 * Lógica de cliques removida completamente
 */

// DEFINIR TIMEZONE NO INÍCIO DO ARQUIVO
date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

error_log("MoniTag Randomize - Iniciando randomização - Time: " . date('Y-m-d H:i:s'));

try {
    $conn = getDbConnection();
    
    // Gerar número aleatório - APENAS IMPRESSÕES
    $random_impressions = rand(5, 12);
    
    error_log("MoniTag Randomize - Valor gerado: impressions=$random_impressions");
    
    // Atualizar configuração global de impressões
    $check_stmt = $conn->prepare("
        SELECT id FROM roulette_settings 
        WHERE setting_key = 'monetag_required_impressions'
    ");
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("
            UPDATE roulette_settings 
            SET setting_value = ?, updated_at = NOW()
            WHERE setting_key = 'monetag_required_impressions'
        ");
        $stmt->bind_param("s", $random_impressions);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("
            INSERT INTO roulette_settings (setting_key, setting_value, description)
            VALUES ('monetag_required_impressions', ?, 'Número de impressões necessárias para desbloquear roleta')
        ");
        $stmt->bind_param("s", $random_impressions);
        $stmt->execute();
        $stmt->close();
    }
    $check_stmt->close();
    
    // Remover configuração de cliques se existir
    $conn->query("DELETE FROM roulette_settings WHERE setting_key = 'monetag_required_clicks'");
    
    $conn->close();
    
    error_log("MoniTag Randomize - Sucesso: impressions=$random_impressions");
    
    sendSuccess([
        'required_impressions' => $random_impressions,
        'required_clicks' => 0,
        'message' => 'Número de impressões randomizado com sucesso',
        'timestamp' => date('Y-m-d H:i:s'),
        'timezone' => 'America/Sao_Paulo'
    ]);
    
} catch (Exception $e) {
    error_log("MoniTag Randomize Error: " . $e->getMessage());
    sendError('Erro ao randomizar impressões: ' . $e->getMessage(), 500);
}
?>

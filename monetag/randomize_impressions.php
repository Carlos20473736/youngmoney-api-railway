<?php
/**
 * Endpoint para randomizar o número de impressões e cliques necessários
 * Deve ser chamado quando o ranking é resetado
 * 
 * GET /monetag/randomize_impressions.php
 * 
 * Randomiza:
 * - Impressões: 5 a 10
 * - Cliques: 1 a 3
 */

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

try {
    $conn = getDbConnection();
    
    // Gerar números aleatórios
    $random_impressions = rand(5, 10); // Entre 5 e 10 impressões
    $random_clicks = rand(1, 3); // Entre 1 e 3 cliques
    
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
    
    // Atualizar configuração global de cliques
    $check_clicks_stmt = $conn->prepare("
        SELECT id FROM roulette_settings 
        WHERE setting_key = 'monetag_required_clicks'
    ");
    $check_clicks_stmt->execute();
    $result_clicks = $check_clicks_stmt->get_result();
    
    if ($result_clicks->num_rows > 0) {
        $stmt = $conn->prepare("
            UPDATE roulette_settings 
            SET setting_value = ?, updated_at = NOW()
            WHERE setting_key = 'monetag_required_clicks'
        ");
        $stmt->bind_param("s", $random_clicks);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("
            INSERT INTO roulette_settings (setting_key, setting_value, description)
            VALUES ('monetag_required_clicks', ?, 'Número de cliques necessários para desbloquear roleta')
        ");
        $stmt->bind_param("s", $random_clicks);
        $stmt->execute();
        $stmt->close();
    }
    $check_clicks_stmt->close();
    
    $conn->close();
    
    error_log("MoniTag - Impressões randomizadas para: $random_impressions, Cliques randomizados para: $random_clicks");
    
    sendSuccess([
        'required_impressions' => $random_impressions,
        'required_clicks' => $random_clicks,
        'message' => 'Número de impressões e cliques randomizado com sucesso',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("MoniTag Randomize Error: " . $e->getMessage());
    sendError('Erro ao randomizar impressões e cliques: ' . $e->getMessage(), 500);
}
?>

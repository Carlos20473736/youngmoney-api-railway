<?php
/**
 * API ÚNICA DE RESET DO POSTBACK MONETAG
 * 
 * Endpoint para resetar todos os dados de postback da MoneyTag
 * e randomizar o número de impressões necessárias (5 a 30)
 * 
 * URL: GET /monetag/reset_postback.php
 * 
 * O que faz:
 * 1. Deleta todos os eventos de monetag_events (todos os usuários)
 * 2. Reseta contadores de impressões/cliques dos usuários
 * 3. Randomiza o número de impressões necessárias (5 a 30)
 * 
 * Usar no CronJob para resetar junto com o ranking
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

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
    
    // Iniciar transação
    $conn->begin_transaction();
    
    $results = [
        'deleted_events' => 0,
        'users_reset' => 0,
        'new_required_impressions' => 0,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // ========================================
    // 1. DELETAR TODOS OS EVENTOS DE MONETAG
    // ========================================
    $delete_events = $conn->query("DELETE FROM monetag_events");
    $results['deleted_events'] = $conn->affected_rows;
    error_log("Reset Postback: Deletados {$results['deleted_events']} eventos de monetag_events");
    
    // ========================================
    // 2. RESETAR CONTADORES DOS USUÁRIOS
    // ========================================
    // Verificar se as colunas existem na tabela users
    $columns_result = $conn->query("DESCRIBE users");
    $columns = [];
    while ($row = $columns_result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    $updates = [];
    if (in_array('monetag_impressions', $columns)) {
        $updates[] = 'monetag_impressions = 0';
    }
    if (in_array('monetag_clicks', $columns)) {
        $updates[] = 'monetag_clicks = 0';
    }
    
    if (!empty($updates)) {
        $update_query = 'UPDATE users SET ' . implode(', ', $updates);
        $conn->query($update_query);
        $results['users_reset'] = $conn->affected_rows;
        error_log("Reset Postback: Resetados contadores de {$results['users_reset']} usuários");
    }
    
    // ========================================
    // 3. RANDOMIZAR IMPRESSÕES NECESSÁRIAS (5 a 30)
    // ========================================
    $random_impressions = rand(5, 30);
    $results['new_required_impressions'] = $random_impressions;
    
    // Verificar se a configuração já existe
    $check_stmt = $conn->prepare("
        SELECT id FROM roulette_settings 
        WHERE setting_key = 'monetag_required_impressions'
    ");
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Atualizar valor existente
        $stmt = $conn->prepare("
            UPDATE roulette_settings 
            SET setting_value = ?, updated_at = NOW()
            WHERE setting_key = 'monetag_required_impressions'
        ");
        $stmt->bind_param("s", $random_impressions);
        $stmt->execute();
        $stmt->close();
    } else {
        // Inserir novo valor
        $stmt = $conn->prepare("
            INSERT INTO roulette_settings (setting_key, setting_value, description)
            VALUES ('monetag_required_impressions', ?, 'Número de impressões necessárias para desbloquear roleta')
        ");
        $stmt->bind_param("s", $random_impressions);
        $stmt->execute();
        $stmt->close();
    }
    $check_stmt->close();
    
    error_log("Reset Postback: Impressões randomizadas para: $random_impressions");
    
    // Commit da transação
    $conn->commit();
    $conn->close();
    
    // Retornar sucesso
    sendSuccess([
        'message' => 'Reset do postback MoneyTag realizado com sucesso!',
        'deleted_events' => $results['deleted_events'],
        'users_reset' => $results['users_reset'],
        'new_required_impressions' => $results['new_required_impressions'],
        'timestamp' => $results['timestamp']
    ]);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
        $conn->close();
    }
    
    error_log("Reset Postback Error: " . $e->getMessage());
    sendError('Erro ao resetar postback: ' . $e->getMessage(), 500);
}
?>

<?php
/**
 * API ÚNICA DE RESET COMPLETO - POSTBACK MONETAG + ROLETA
 * 
 * Endpoint: GET /monetag/reset_postback.php
 * 
 * O que faz (TUDO DE UMA VEZ):
 * 1. Deleta todos os eventos de monetag_events (todos os usuários)
 * 2. Reseta contadores de impressões/cliques dos usuários
 * 3. Randomiza o número de impressões necessárias (5 a 30)
 * 4. Reseta os giros da roleta (deleta spin_history)
 * 
 * Usar no CronJob para resetar TUDO junto
 */

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../database.php';

function sendSuccess($data = []) {
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn = getDbConnection();
    
    // Iniciar transação
    $conn->begin_transaction();
    
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');
    $current_datetime = date('Y-m-d H:i:s');
    
    $results = [
        'message' => 'Reset completo realizado com sucesso!',
        'monetag' => [
            'deleted_events' => 0,
            'users_reset' => 0,
            'new_required_impressions' => 0
        ],
        'roulette' => [
            'spins_deleted' => 0
        ],
        'timestamp' => $current_datetime,
        'timezone' => 'America/Sao_Paulo (GMT-3)'
    ];
    
    // ========================================
    // 1. DELETAR TODOS OS EVENTOS DE MONETAG
    // ========================================
    $delete_events = $conn->query("DELETE FROM monetag_events");
    $results['monetag']['deleted_events'] = $conn->affected_rows;
    error_log("Reset Completo: Deletados {$results['monetag']['deleted_events']} eventos de monetag_events");
    
    // ========================================
    // 2. RESETAR CONTADORES DOS USUÁRIOS
    // ========================================
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
        $results['monetag']['users_reset'] = $conn->affected_rows;
        error_log("Reset Completo: Resetados contadores de {$results['monetag']['users_reset']} usuários");
    }
    
    // ========================================
    // 3. RANDOMIZAR IMPRESSÕES NECESSÁRIAS (5 a 30)
    // ========================================
    $random_impressions = rand(5, 30);
    $results['monetag']['new_required_impressions'] = $random_impressions;
    
    $check_stmt = $conn->prepare("
        SELECT id FROM roulette_settings 
        WHERE setting_key = 'monetag_required_impressions'
    ");
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
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
    
    error_log("Reset Completo: Impressões randomizadas para: $random_impressions");
    
    // ========================================
    // 4. RESETAR ROLETA (DELETAR TODOS OS SPINS)
    // ========================================
    // Contar quantos spins serão deletados
    $count_result = $conn->query("SELECT COUNT(*) as total FROM spin_history");
    $count_row = $count_result->fetch_assoc();
    $spins_to_delete = $count_row['total'] ?? 0;
    
    // Deletar TODOS os registros de spin (reset completo)
    $conn->query("DELETE FROM spin_history");
    $results['roulette']['spins_deleted'] = $conn->affected_rows;
    
    error_log("Reset Completo: Deletados {$results['roulette']['spins_deleted']} spins da roleta");
    
    // Registrar log do reset (se a tabela existir)
    $log_stmt = $conn->prepare("
        INSERT INTO spin_reset_logs 
        (spins_deleted, reset_datetime, triggered_by) 
        VALUES (?, NOW(), 'cron_reset_postback')
    ");
    if ($log_stmt) {
        $log_stmt->bind_param("i", $results['roulette']['spins_deleted']);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    // Commit da transação
    $conn->commit();
    $conn->close();
    
    // Retornar sucesso
    sendSuccess($results);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
        $conn->close();
    }
    
    error_log("Reset Completo Error: " . $e->getMessage());
    sendError('Erro ao executar reset completo: ' . $e->getMessage(), 500);
}
?>

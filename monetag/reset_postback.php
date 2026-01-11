<?php
/**
 * API ÚNICA DE RESET COMPLETO - POSTBACK MONETAG + ROLETA
 * 
 * Endpoint: GET /monetag/reset_postback.php
 * 
 * O que faz (TUDO DE UMA VEZ):
 * 1. Reseta os dados no servidor monetag-postback-server (impressões e cliques reais)
 * 2. Deleta todos os eventos de monetag_events (todos os usuários) - RESETA IMPRESSÕES E CLIQUES locais
 * 3. Reseta contadores de impressões/cliques dos usuários na tabela users
 * 4. Randomiza o número de impressões necessárias (5 a 30)
 * 5. Reseta os giros da roleta (deleta spin_history)
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

/**
 * Função para chamar o reset do servidor monetag-postback-server
 * Este servidor armazena os dados reais de impressões e cliques
 */
function resetMonetagPostbackServer() {
    $url = 'https://monetag-postback-server-production.up.railway.app/api/reset';
    $token = 'ym_reset_monetag_scheduled_2024_secure';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url . '?token=' . $token,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Reset Monetag Server Error: " . $error);
        return [
            'success' => false,
            'error' => $error,
            'http_code' => $httpCode
        ];
    }
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 && isset($data['success']) && $data['success']) {
        error_log("Reset Monetag Server: Sucesso - " . json_encode($data));
        return [
            'success' => true,
            'data' => $data['data'] ?? [],
            'http_code' => $httpCode
        ];
    }
    
    error_log("Reset Monetag Server Failed: HTTP $httpCode - " . $response);
    return [
        'success' => false,
        'error' => $data['error'] ?? 'Erro desconhecido',
        'http_code' => $httpCode
    ];
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
        'monetag_server' => [
            'success' => false,
            'events_deleted' => 0,
            'impressions_deleted' => 0,
            'clicks_deleted' => 0,
            'users_affected' => 0
        ],
        'monetag_local' => [
            'deleted_events' => 0,
            'deleted_impressions' => 0,
            'deleted_clicks' => 0,
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
    // 1. RESETAR SERVIDOR MONETAG-POSTBACK-SERVER (DADOS REAIS DE IMPRESSÕES E CLIQUES)
    // ========================================
    error_log("Reset Completo: Iniciando reset do servidor monetag-postback-server...");
    
    $monetagServerResult = resetMonetagPostbackServer();
    
    if ($monetagServerResult['success']) {
        $serverData = $monetagServerResult['data'];
        $results['monetag_server'] = [
            'success' => true,
            'events_deleted' => $serverData['events_deleted'] ?? 0,
            'impressions_deleted' => $serverData['impressions_deleted'] ?? 0,
            'clicks_deleted' => $serverData['clicks_deleted'] ?? 0,
            'users_affected' => $serverData['users_affected'] ?? 0
        ];
        error_log("Reset Completo: Servidor monetag-postback-server resetado com sucesso!");
        error_log("Reset Completo: Impressões deletadas (servidor): " . ($serverData['impressions_deleted'] ?? 0));
        error_log("Reset Completo: Cliques deletados (servidor): " . ($serverData['clicks_deleted'] ?? 0));
    } else {
        $results['monetag_server']['error'] = $monetagServerResult['error'] ?? 'Erro ao conectar';
        error_log("Reset Completo: AVISO - Falha ao resetar servidor monetag-postback-server: " . ($monetagServerResult['error'] ?? 'Erro desconhecido'));
    }
    
    // ========================================
    // 2. CONTAR E DELETAR TODOS OS EVENTOS DE MONETAG LOCAIS (IMPRESSÕES E CLIQUES)
    // ========================================
    
    // Primeiro, contar impressões e cliques separadamente para o log
    $count_impressions = $conn->query("SELECT COUNT(*) as total FROM monetag_events WHERE event_type = 'impression'");
    $count_clicks = $conn->query("SELECT COUNT(*) as total FROM monetag_events WHERE event_type = 'click'");
    
    if ($count_impressions) {
        $row = $count_impressions->fetch_assoc();
        $results['monetag_local']['deleted_impressions'] = (int)($row['total'] ?? 0);
    }
    
    if ($count_clicks) {
        $row = $count_clicks->fetch_assoc();
        $results['monetag_local']['deleted_clicks'] = (int)($row['total'] ?? 0);
    }
    
    // Deletar TODOS os eventos (impressões e cliques)
    $delete_events = $conn->query("DELETE FROM monetag_events");
    $results['monetag_local']['deleted_events'] = $conn->affected_rows;
    
    error_log("Reset Completo: Deletados {$results['monetag_local']['deleted_events']} eventos de monetag_events (local)");
    error_log("Reset Completo: Impressões deletadas (local): {$results['monetag_local']['deleted_impressions']}");
    error_log("Reset Completo: Cliques deletados (local): {$results['monetag_local']['deleted_clicks']}");
    
    // ========================================
    // 3. RESETAR CONTADORES DOS USUÁRIOS NA TABELA USERS
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
        $results['monetag_local']['users_reset'] = $conn->affected_rows;
        error_log("Reset Completo: Resetados contadores de {$results['monetag_local']['users_reset']} usuários (impressões e cliques zerados)");
    }
    
    // ========================================
    // 4. RANDOMIZAR IMPRESSÕES NECESSÁRIAS (5 a 30)
    // ========================================
    $random_impressions = rand(5, 30);
    $results['monetag_local']['new_required_impressions'] = $random_impressions;
    
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
    // 5. RESETAR ROLETA (DELETAR TODOS OS SPINS)
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

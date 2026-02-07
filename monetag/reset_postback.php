<?php
/**
 * API ÚNICA DE RESET COMPLETO - POSTBACK MONETAG + ROLETA (v3 - APENAS IMPRESSÕES)
 * 
 * Endpoint: GET /monetag/reset_postback.php
 * 
 * O que faz (TUDO DE UMA VEZ):
 * 1. Reseta os dados no servidor monetag-postback-server (impressões reais)
 * 2. Deleta todos os eventos de monetag_events (todos os usuários) - RESETA IMPRESSÕES locais
 * 3. Reseta contadores de impressões dos usuários na tabela users
 * 4. Randomiza o número de impressões necessárias (5 a 12)
 * 5. Reseta os giros da roleta (deleta spin_history)
 * 
 * Lógica de cliques removida completamente
 */

error_reporting(0);
ini_set('display_errors', '0');

// DEFINIR TIMEZONE NO INÍCIO DO ARQUIVO
date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
 * Este servidor armazena os dados reais de impressões
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

error_log("Reset Completo - Iniciando - Time: " . date('Y-m-d H:i:s') . " - Timezone: America/Sao_Paulo");

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
            'users_affected' => 0
        ],
        'monetag_local' => [
            'deleted_events' => 0,
            'deleted_impressions' => 0,
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
    // 1. RESETAR SERVIDOR MONETAG-POSTBACK-SERVER
    // ========================================
    error_log("Reset Completo: Iniciando reset do servidor monetag-postback-server...");
    
    $monetagServerResult = resetMonetagPostbackServer();
    
    if ($monetagServerResult['success']) {
        $serverData = $monetagServerResult['data'];
        $results['monetag_server'] = [
            'success' => true,
            'events_deleted' => $serverData['events_deleted'] ?? 0,
            'impressions_deleted' => $serverData['impressions_deleted'] ?? 0,
            'users_affected' => $serverData['users_affected'] ?? 0
        ];
        error_log("Reset Completo: Servidor monetag-postback-server resetado com sucesso!");
        error_log("Reset Completo: Impressões deletadas (servidor): " . ($serverData['impressions_deleted'] ?? 0));
    } else {
        $results['monetag_server']['error'] = $monetagServerResult['error'] ?? 'Erro ao conectar';
        error_log("Reset Completo: AVISO - Falha ao resetar servidor monetag-postback-server: " . ($monetagServerResult['error'] ?? 'Erro desconhecido'));
    }
    
    // ========================================
    // 2. CONTAR E DELETAR TODOS OS EVENTOS DE MONETAG LOCAIS
    // ========================================
    
    $count_impressions = $conn->query("SELECT COUNT(*) as total FROM monetag_events WHERE event_type = 'impression'");
    
    if ($count_impressions) {
        $row = $count_impressions->fetch_assoc();
        $results['monetag_local']['deleted_impressions'] = (int)($row['total'] ?? 0);
    }
    
    // Deletar TODOS os eventos
    $delete_events = $conn->query("DELETE FROM monetag_events");
    $results['monetag_local']['deleted_events'] = $conn->affected_rows;
    
    error_log("Reset Completo: Deletados {$results['monetag_local']['deleted_events']} eventos de monetag_events (local)");
    error_log("Reset Completo: Impressões deletadas (local): {$results['monetag_local']['deleted_impressions']}");
    
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
        error_log("Reset Completo: Resetados contadores de {$results['monetag_local']['users_reset']} usuários");
    }
    
    // ========================================
    // 4. RANDOMIZAR IMPRESSÕES (5-12) NECESSÁRIAS POR USUÁRIO
    // ========================================
    
    // Criar tabela user_required_impressions se não existir
    $conn->query("
        CREATE TABLE IF NOT EXISTS user_required_impressions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            required_impressions INT DEFAULT 5,
            required_clicks INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id)
        )
    ");
    
    // Buscar todos os usuários
    $users_result = $conn->query("SELECT id FROM users");
    $users_randomized = 0;
    $randomized_details = [];
    
    while ($user = $users_result->fetch_assoc()) {
        $uid = $user['id'];
        $random_impressions = rand(5, 12);
        
        // Inserir ou atualizar impressões necessárias do usuário (cliques = 0)
        $stmt = $conn->prepare("
            INSERT INTO user_required_impressions (user_id, required_impressions, required_clicks, updated_at)
            VALUES (?, ?, 0, NOW())
            ON DUPLICATE KEY UPDATE 
                required_impressions = VALUES(required_impressions),
                required_clicks = 0,
                updated_at = NOW()
        ");
        $stmt->bind_param("ii", $uid, $random_impressions);
        $stmt->execute();
        $stmt->close();
        
        $users_randomized++;
        if ($users_randomized <= 20) {
            $randomized_details[] = [
                'user_id' => $uid, 
                'required_impressions' => $random_impressions
            ];
        }
    }
    
    $results['monetag_local']['users_randomized'] = $users_randomized;
    $results['monetag_local']['randomized_impressions_range'] = '5-12';
    $results['monetag_local']['randomized_sample'] = $randomized_details;
    
    error_log("Reset Completo: Impressões randomizadas (5-12) para $users_randomized usuários");
    
    // ========================================
    // 5. RESETAR ROLETA (DELETAR TODOS OS SPINS)
    // ========================================
    $count_result = $conn->query("SELECT COUNT(*) as total FROM spin_history");
    $count_row = $count_result->fetch_assoc();
    $spins_to_delete = $count_row['total'] ?? 0;
    
    $conn->query("DELETE FROM spin_history");
    $results['roulette']['spins_deleted'] = $conn->affected_rows;
    
    error_log("Reset Completo: Deletados {$results['roulette']['spins_deleted']} spins da roleta");
    
    // Registrar log do reset
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
    
    error_log("Reset Completo: SUCESSO - " . json_encode($results));
    
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

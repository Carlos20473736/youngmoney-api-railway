<?php
/**
 * Script para verificar a tabela active_sessions e encontrar emails
 * 
 * Endpoint: GET /api/v1/admin/check_sessions.php?key=YOUNGMONEY_DEBUG_2024
 */

header('Content-Type: application/json');

$key = $_GET['key'] ?? '';
if ($key !== 'YOUNGMONEY_DEBUG_2024') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

require_once __DIR__ . '/../../../database.php';

$result = ['success' => false];

try {
    $conn = getDbConnection();
    
    // Verificar active_sessions
    $res = $conn->query("SELECT * FROM active_sessions ORDER BY id DESC LIMIT 20");
    $sessions = [];
    while ($row = $res->fetch_assoc()) {
        $sessions[] = $row;
    }
    $result['active_sessions'] = $sessions;
    
    // Verificar se há sessões para user_id 58
    $res = $conn->query("SELECT * FROM active_sessions WHERE user_id = 58 OR ymid = 58 LIMIT 5");
    $user58Sessions = [];
    while ($row = $res->fetch_assoc()) {
        $user58Sessions[] = $row;
    }
    $result['user_58_sessions'] = $user58Sessions;
    
    // Verificar device_access_logs
    $res = $conn->query("SELECT * FROM device_access_logs ORDER BY id DESC LIMIT 20");
    $logs = [];
    while ($row = $res->fetch_assoc()) {
        $logs[] = $row;
    }
    $result['device_access_logs'] = $logs;
    
    // Verificar user_tokens
    $res = $conn->query("SELECT * FROM user_tokens ORDER BY id DESC LIMIT 20");
    $tokens = [];
    while ($row = $res->fetch_assoc()) {
        $tokens[] = $row;
    }
    $result['user_tokens'] = $tokens;
    
    // Verificar accounts (se existir)
    $res = $conn->query("SELECT * FROM accounts LIMIT 20");
    if ($res) {
        $accounts = [];
        while ($row = $res->fetch_assoc()) {
            $accounts[] = $row;
        }
        $result['accounts'] = $accounts;
    }
    
    $conn->close();
    $result['success'] = true;
    
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);

<?php
/**
 * Endpoint Simples de Configurações (SEM criptografia)
 * Para uso interno do WebView do app Android
 * 
 * GET /api/v1/config-simple.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responder OPTIONS para CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../../database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(['success' => false, 'error' => 'Método não permitido']);
        exit;
    }
    
    $conn = getDbConnection();
    
    // Buscar horário de reset configurado
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'reset_time' LIMIT 1");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $reset_time = $row ? $row['setting_value'] : '21:00';
    
    list($reset_hour, $reset_minute) = explode(':', $reset_time);
    
    $stmt->close();
    
    // Buscar valores rápidos de saque
    $stmt = $conn->prepare("SELECT value_amount FROM withdrawal_quick_values WHERE is_active = 1 ORDER BY value_amount ASC");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $quick_values = [];
    while ($row = $result->fetch_assoc()) {
        $quick_values[] = (int)$row['value_amount'];
    }
    
    // Se não houver valores, usar padrão
    if (empty($quick_values)) {
        $quick_values = [1, 10, 20, 50];
    }
    
    $stmt->close();
    $conn->close();
    
    // Enviar resposta SEM criptografia
    echo json_encode([
        'success' => true,
        'data' => [
            'reset_time' => $reset_time,
            'reset_hour' => (int)$reset_hour,
            'reset_minute' => (int)$reset_minute,
            'timezone' => 'America/Sao_Paulo',
            'server_timestamp' => time(), // Unix timestamp em segundos
            'server_time' => date('H:i:s'), // Hora formatada HH:mm:ss
            'quick_withdrawal_values' => $quick_values
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Config simple endpoint error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar configurações: ' . $e->getMessage()
    ]);
}
?>

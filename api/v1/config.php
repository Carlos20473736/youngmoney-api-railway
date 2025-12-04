<?php
/**


 * Endpoint Público de Configurações com Criptografia V2
 * Permite que o app Android busque configurações do sistema
 * 
 * GET /api/v1/config.php
 * 
 * Suporta criptografia V2 (X-Req header)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Req');

// Responder OPTIONS para CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../../includes/DecryptMiddleware.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        DecryptMiddleware::sendError('Método não permitido', 405);
        exit;
    }
    
    // Conectar diretamente ao banco usando variáveis de ambiente
    $db_host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
    $db_user = $_ENV['DB_USER'] ?? getenv('DB_USER');
    $db_pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
    $db_name = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
    $db_port = $_ENV['DB_PORT'] ?? getenv('DB_PORT');
    
    $conn = mysqli_init();
    
    if (!$conn) {
        throw new Exception("mysqli_init falhou");
    }
    
    mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
    
    if (!mysqli_real_connect($conn, $db_host, $db_user, $db_pass, $db_name, $db_port, NULL, MYSQLI_CLIENT_SSL)) {
        throw new Exception("Conexão falhou: " . mysqli_connect_error());
    }
    
    mysqli_set_charset($conn, "utf8mb4");
    
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
        $quick_values = [10, 20, 50, 100, 200, 500];
    }
    
    $stmt->close();
    
    // Buscar valores dos prêmios da roleta
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM roulette_settings WHERE setting_key LIKE 'prize_%' ORDER BY setting_key ASC");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $prize_values = [];
    while ($row = $result->fetch_assoc()) {
        $prize_values[] = (int)$row['setting_value'];
    }
    
    // Se não houver valores, usar padrão
    if (empty($prize_values)) {
        $prize_values = [100, 250, 500, 750, 1000, 1500, 2000, 5000];
    }
    
    $stmt->close();
    $conn->close();
    
    // Enviar resposta criptografada
    DecryptMiddleware::sendSuccess([
        'reset_time' => $reset_time,
        'reset_hour' => (int)$reset_hour,
        'reset_minute' => (int)$reset_minute,
        'timezone' => 'America/Sao_Paulo',
        'quick_withdrawal_values' => $quick_values,
        'prize_values' => $prize_values
    ], true); // true = criptografar resposta
    
} catch (Exception $e) {
    error_log("Config endpoint error: " . $e->getMessage());
    DecryptMiddleware::sendError('Erro ao buscar configurações: ' . $e->getMessage(), 500);
}
?>

<?php
/**
 * Endpoint Público - Valores Rápidos de Saque + Configuração de Pontos
 * Permite que o app Android busque os valores configurados
 * 
 * GET /api/v1/withdrawal_values.php
 * 
 * TAXA: 5.000.000 pontos = R$ 1,00
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../../includes/DecryptMiddleware.php';
require_once __DIR__ . '/../../database.php';
require_once __DIR__ . '/middleware/MaintenanceCheck.php';

// Constantes
define('POINTS_PER_REAL', 5000000);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        DecryptMiddleware::sendError('Método não permitido', 405);
        exit;
    }
    
    // ========================================
    // VERIFICAÇÃO DE MANUTENÇÃO E VERSÃO
    // ========================================
    $maintenanceConn = getDbConnection();
    $userEmail = $_GET['email'] ?? null;
    $appVersion = $_GET['app_version'] ?? $_SERVER['HTTP_X_APP_VERSION'] ?? null;
    checkMaintenanceAndVersion($maintenanceConn, $userEmail, $appVersion);
    $maintenanceConn->close();
    // ========================================
    
    // Conectar ao banco
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
    
    // Buscar valores ativos
    $result = $conn->query("
        SELECT value_amount 
        FROM withdrawal_quick_values 
        WHERE is_active = 1 
        ORDER BY display_order ASC
    ");
    
    $values = [];
    $valuesWithPoints = [];
    while ($row = $result->fetch_assoc()) {
        $brl = (float)$row['value_amount'];
        $values[] = $brl;
        $points = (int)($brl * POINTS_PER_REAL);
        $valuesWithPoints[] = [
            'brl' => $brl,
            'brl_formatted' => 'R$ ' . number_format($brl, 2, ',', '.'),
            'points' => $points,
            'points_formatted' => number_format($points, 0, '', '.'),
        ];
    }
    
    $conn->close();
    
    // Se não houver valores, retornar padrão
    if (empty($values)) {
        $defaultValues = [1.0, 2.0, 5.0, 10.0, 20.0, 50.0];
        $values = $defaultValues;
        foreach ($defaultValues as $brl) {
            $points = (int)($brl * POINTS_PER_REAL);
            $valuesWithPoints[] = [
                'brl' => $brl,
                'brl_formatted' => 'R$ ' . number_format($brl, 2, ',', '.'),
                'points' => $points,
                'points_formatted' => number_format($points, 0, '', '.'),
            ];
        }
    }
    
    // Enviar resposta criptografada
    DecryptMiddleware::sendSuccess([
        'values' => $values,
        'values_with_points' => $valuesWithPoints,
        'points_per_real' => POINTS_PER_REAL,
        'points_per_real_formatted' => number_format(POINTS_PER_REAL, 0, '', '.'),
        'min_withdrawal_brl' => 1.00,
        'min_withdrawal_points' => POINTS_PER_REAL,
        'available_methods' => ['pix', 'binance', 'faucetpay'],
    ], true);
    
} catch (Exception $e) {
    error_log("Withdrawal values endpoint error: " . $e->getMessage());
    DecryptMiddleware::sendError('Erro ao buscar valores: ' . $e->getMessage(), 500);
}
?>

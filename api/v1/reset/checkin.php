<?php
/**
 * API de Reset do Check-in Diário
 * 
 * Endpoint: POST /api/v1/reset/checkin.php
 * 
 * Função: Reseta o check-in do dia para permitir novo check-in
 * 
 * Lógica:
 * - Atualiza last_reset_datetime para o horário atual
 * - Permite que usuários façam novo check-in
 * - Preserva histórico de check-ins anteriores
 * 
 * Segurança:
 * - Token obrigatório via query parameter ou header
 * - Validação de conexão com banco de dados
 * - Transação para garantir consistência
 */

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar token de segurança
$token = $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$expectedToken = getenv('RESET_TOKEN') ?: 'ym_reset_checkin_2024_secure';

// Remover "Bearer " se presente
if (strpos($token, 'Bearer ') === 0) {
    $token = substr($token, 7);
}

if (empty($token) || $token !== $expectedToken) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Token inválido ou não fornecido',
        'required_param' => '?token=seu_token_aqui'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

try {
    // Conectar ao banco de dados
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME') ?: 'defaultdb';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ]);
    
    // Obter horário atual
    $current_datetime = date('Y-m-d H:i:s');
    $current_date = date('Y-m-d');
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    try {
        // Atualizar last_reset_datetime para permitir novo check-in
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_at)
            VALUES ('last_reset_datetime', :datetime, NOW())
            ON DUPLICATE KEY UPDATE 
                setting_value = :datetime,
                updated_at = NOW()
        ");
        $stmt->execute(['datetime' => $current_datetime]);
        
        // Registrar log do reset (opcional)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO checkin_reset_logs 
                (reset_type, triggered_by, reset_datetime, status) 
                VALUES ('manual', 'api-reset', NOW(), 'success')
            ");
            $stmt->execute();
        } catch (PDOException $e) {
            // Tabela de logs pode não existir, ignorar erro
        }
        
        // Commit da transação
        $pdo->commit();
        
        // Retornar sucesso
        echo json_encode([
            'success' => true,
            'message' => 'Check-in resetado com sucesso!',
            'data' => [
                'reset_type' => 'checkin',
                'description' => 'Usuários podem fazer novo check-in',
                'last_reset_datetime' => $current_datetime,
                'reset_date' => $current_date,
                'timezone' => 'America/Sao_Paulo (GMT-3)',
                'timestamp' => time()
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao conectar ao banco de dados',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao executar reset do check-in',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

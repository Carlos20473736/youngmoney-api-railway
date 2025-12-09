<?php
/**
 * API de Reset do Ranking
 * 
 * Endpoint: POST /api/v1/reset/ranking.php
 * 
 * Função: Reseta o ranking diário para zero
 * 
 * Lógica:
 * - Zera daily_points de todos os usuários
 * - Permite que usuários acumulem pontos novamente
 * - Reseta o ranking para começar novo ciclo
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
$expectedToken = getenv('RESET_TOKEN') ?: 'ym_reset_ranking_2024_secure';

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
    $host = getenv('MYSQLHOST') ?: (getenv('DB_HOST') ?: 'localhost');
    $port = getenv('MYSQLPORT') ?: (getenv('DB_PORT') ?: '3306');
    $dbname = getenv('MYSQLDATABASE') ?: (getenv('DB_NAME') ?: 'defaultdb');
    $username = getenv('MYSQLUSER') ?: (getenv('DB_USER') ?: 'root');
    $password = getenv('MYSQLPASSWORD') ?: (getenv('DB_PASSWORD') ?: '');
    
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ]);
    
    // Obter data e hora atual
    $current_date = date('Y-m-d');
    $current_datetime = date('Y-m-d H:i:s');
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    try {
        // Contar quantos usuários têm daily_points > 0
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM users 
            WHERE daily_points > 0
        ");
        $countStmt->execute();
        $countResult = $countStmt->fetch();
        $usersAffected = $countResult['total'] ?? 0;
        
        // Resetar daily_points para 0 para todos os usuários
        $stmt = $pdo->prepare("
            UPDATE users 
            SET daily_points = 0
        ");
        $stmt->execute();
        
        // Registrar log do reset (opcional)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ranking_reset_logs 
                (reset_type, triggered_by, users_affected, reset_datetime, status) 
                VALUES ('manual', 'api-reset', :users, NOW(), 'success')
            ");
            $stmt->execute(['users' => $usersAffected]);
        } catch (PDOException $e) {
            // Tabela de logs pode não existir, ignorar erro
        }
        
        // Commit da transação
        $pdo->commit();
        
        // Retornar sucesso
        echo json_encode([
            'success' => true,
            'message' => 'Ranking resetado com sucesso!',
            'data' => [
                'reset_type' => 'ranking',
                'description' => 'Todos os usuários tiveram daily_points zerado',
                'users_affected' => $usersAffected,
                'daily_points_reset_to' => 0,
                'reset_date' => $current_date,
                'reset_datetime' => $current_datetime,
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
        'error' => 'Erro ao executar reset do ranking',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

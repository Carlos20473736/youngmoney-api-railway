<?php
/**
 * API para Visualizar Pagamentos Pendentes
 * 
 * Endpoint: GET /api/v1/payments/pending.php
 * 
 * Função: Retorna lista de pagamentos pendentes criados pelo reset do ranking
 * 
 * Parâmetros:
 * - token: Token de segurança (obrigatório)
 * - limit: Limite de registros (opcional, padrão: 100)
 * - offset: Deslocamento (opcional, padrão: 0)
 * - status: Filtrar por status (optional: pending, completed, failed)
 * 
 * Retorna:
 * - Lista de pagamentos pendentes com informações do usuário
 * - Total de pagamentos e valor total
 * - Informações de paginação
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
$expectedToken = getenv('RESET_TOKEN') ?: 'ym_reset_ranking_scheduled_2024_secure';

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

require_once __DIR__ . '/../../../database.php';
require_once __DIR__ . '/../middleware/MaintenanceCheck.php';

// ========================================
// VERIFICAÇÃO DE MANUTENÇÃO E VERSÃO
// ========================================
try {
    $maintenanceConn = getDbConnection();
    $userEmail = $_GET['email'] ?? null;
    $appVersion = $_GET['app_version'] ?? $_SERVER['HTTP_X_APP_VERSION'] ?? null;
    checkMaintenanceAndVersion($maintenanceConn, $userEmail, $appVersion);
    $maintenanceConn->close();
} catch (Exception $e) {
    // Continuar mesmo se falhar a verificação
}
// ========================================

try {
    // Conectar ao banco de dados usando MySQLi
    $db_host = $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST') ?: 'localhost';
    $db_user = $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER') ?: 'root';
    $db_pass = $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?: '';
    $db_name = $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?: 'railway';
    $db_port = $_ENV['MYSQLPORT'] ?? getenv('MYSQLPORT') ?: 3306;
    
    $conn = mysqli_init();
    if (!$conn) {
        throw new Exception("mysqli_init failed");
    }
    
    $success = $conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
    if (!$success) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    // Obter parâmetros de paginação e filtro
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 1000) : 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : 'pending';
    
    // Validar status
    $valid_statuses = ['pending', 'completed', 'failed'];
    if (!in_array($status, $valid_statuses)) {
        $status = 'pending';
    }
    
    // Contar total de pagamentos com o status
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM pix_payments 
        WHERE status = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_payments = $row['total'] ?? 0;
    $stmt->close();
    
    // Buscar pagamentos com informações do usuário
    $stmt = $conn->prepare("
        SELECT 
            pp.id,
            pp.user_id,
            pp.position,
            pp.amount,
            pp.pix_key_type,
            pp.pix_key,
            pp.status,
            pp.transaction_id,
            pp.error_message,
            pp.created_at,
            pp.updated_at,
            u.name,
            u.email,
            u.phone
        FROM pix_payments pp
        JOIN users u ON pp.user_id = u.id
        WHERE pp.status = ?
        ORDER BY pp.position ASC, pp.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("sii", $status, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    $total_amount = 0;
    
    while ($row = $result->fetch_assoc()) {
        $payments[] = [
            'id' => (int)$row['id'],
            'position' => (int)$row['position'],
            'user' => [
                'id' => (int)$row['user_id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $row['phone']
            ],
            'amount' => (float)$row['amount'],
            'pix' => [
                'key_type' => $row['pix_key_type'],
                'key' => $row['pix_key']
            ],
            'status' => $row['status'],
            'transaction_id' => $row['transaction_id'],
            'error_message' => $row['error_message'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
        
        $total_amount += (float)$row['amount'];
    }
    
    $stmt->close();
    
    // Obter estatísticas de pagamentos por status
    $stmt = $conn->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            SUM(amount) as total
        FROM pix_payments
        GROUP BY status
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $statistics = [];
    while ($row = $result->fetch_assoc()) {
        $statistics[$row['status']] = [
            'count' => (int)$row['count'],
            'total' => (float)$row['total']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Pagamentos recuperados com sucesso',
        'data' => [
            'filter' => [
                'status' => $status,
                'limit' => $limit,
                'offset' => $offset
            ],
            'pagination' => [
                'total' => $total_payments,
                'limit' => $limit,
                'offset' => $offset,
                'pages' => ceil($total_payments / $limit),
                'current_page' => floor($offset / $limit) + 1
            ],
            'summary' => [
                'total_amount' => round($total_amount, 2),
                'payments_count' => count($payments)
            ],
            'statistics' => $statistics,
            'payments' => $payments,
            'timezone' => 'America/Sao_Paulo (GMT-3)',
            'timestamp' => time()
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao recuperar pagamentos',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

<?php
/**
 * API para Marcar Pagamentos como Completos
 * 
 * Endpoint: POST /api/v1/payments/complete.php
 * 
 * Função: Marca um ou mais pagamentos pendentes como completos
 * 
 * Parâmetros (POST/JSON):
 * - token: Token de segurança (obrigatório)
 * - payment_ids: Array de IDs de pagamentos ou 'all' para todos os pendentes (obrigatório)
 * - transaction_id: ID da transação (opcional)
 * 
 * Exemplo:
 * {
 *   "token": "ym_reset_ranking_scheduled_2024_secure",
 *   "payment_ids": [1, 2, 3],
 *   "transaction_id": "TXN123456"
 * }
 * 
 * Retorna:
 * - Número de pagamentos atualizados
 * - Informações dos pagamentos completos
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

// Apenas aceitar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método não permitido. Use POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Obter dados do POST
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Verificar token de segurança
$token = $input['token'] ?? $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
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
        'required_param' => 'token'
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
    $userEmail = $input['email'] ?? null;
    $appVersion = $input['app_version'] ?? $_SERVER['HTTP_X_APP_VERSION'] ?? null;
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
    
    // Obter IDs de pagamentos
    $payment_ids = $input['payment_ids'] ?? [];
    $transaction_id = $input['transaction_id'] ?? null;
    
    // Validar entrada
    if (empty($payment_ids)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'payment_ids é obrigatório'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        $updated_count = 0;
        $completed_payments = [];
        
        // NOVA LÓGICA v3: Verificar cooldown antes de completar pagamento
        // Usuários em countdown NÃO recebem pagamento
        $now_dt = date('Y-m-d H:i:s');
        
        // Se payment_ids é 'all', atualizar todos os pendentes (exceto usuários em cooldown)
        if ($payment_ids === 'all') {
            // NOVA LÓGICA v3: Excluir usuários em cooldown
            $stmt = $conn->prepare("
                UPDATE pix_payments pp
                SET pp.status = 'completed', 
                    pp.transaction_id = ?,
                    pp.updated_at = NOW()
                WHERE pp.status = 'pending'
                AND pp.user_id NOT IN (
                    SELECT rc.user_id FROM ranking_cooldowns rc WHERE rc.cooldown_until > ?
                )
            ");
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("ss", $transaction_id, $now_dt);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $updated_count = $stmt->affected_rows;
            $stmt->close();
            
            // Buscar pagamentos que foram atualizados
            $stmt = $conn->prepare("
                SELECT 
                    pp.id,
                    pp.user_id,
                    pp.position,
                    pp.amount,
                    pp.status,
                    u.name,
                    u.email
                FROM pix_payments pp
                JOIN users u ON pp.user_id = u.id
                WHERE pp.status = 'completed'
                AND pp.transaction_id = ?
                ORDER BY pp.position ASC
            ");
            
            if ($stmt) {
                $stmt->bind_param("s", $transaction_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $completed_payments[] = [
                        'id' => (int)$row['id'],
                        'position' => (int)$row['position'],
                        'user' => [
                            'id' => (int)$row['user_id'],
                            'name' => $row['name'],
                            'email' => $row['email']
                        ],
                        'amount' => (float)$row['amount'],
                        'status' => $row['status']
                    ];
                }
                
                $stmt->close();
            }
        } else {
            // Atualizar pagamentos específicos
            if (!is_array($payment_ids)) {
                $payment_ids = [$payment_ids];
            }
            
            // Sanitizar IDs
            $payment_ids = array_map('intval', $payment_ids);
            
            foreach ($payment_ids as $payment_id) {
                // NOVA LÓGICA v3: Verificar se usuário do pagamento está em cooldown
                $stmt_check = $conn->prepare("
                    SELECT pp.user_id, 
                           CASE WHEN rc.id IS NOT NULL THEN 1 ELSE 0 END as in_cooldown
                    FROM pix_payments pp
                    LEFT JOIN ranking_cooldowns rc ON pp.user_id = rc.user_id AND rc.cooldown_until > ?
                    WHERE pp.id = ?
                ");
                if ($stmt_check) {
                    $stmt_check->bind_param("si", $now_dt, $payment_id);
                    $stmt_check->execute();
                    $check_result = $stmt_check->get_result();
                    $check_row = $check_result->fetch_assoc();
                    $stmt_check->close();
                    
                    if ($check_row && (int)$check_row['in_cooldown'] === 1) {
                        // Usuário em countdown - pular pagamento
                        continue;
                    }
                }
                
                $stmt = $conn->prepare("
                    UPDATE pix_payments 
                    SET status = 'completed', 
                        transaction_id = ?,
                        updated_at = NOW()
                    WHERE id = ? AND status = 'pending'
                ");
                
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("si", $transaction_id, $payment_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                
                if ($stmt->affected_rows > 0) {
                    $updated_count++;
                    
                    // Buscar informações do pagamento atualizado
                    $stmt_select = $conn->prepare("
                        SELECT 
                            pp.id,
                            pp.user_id,
                            pp.position,
                            pp.amount,
                            pp.status,
                            u.name,
                            u.email
                        FROM pix_payments pp
                        JOIN users u ON pp.user_id = u.id
                        WHERE pp.id = ?
                    ");
                    
                    if ($stmt_select) {
                        $stmt_select->bind_param("i", $payment_id);
                        $stmt_select->execute();
                        $result = $stmt_select->get_result();
                        
                        if ($row = $result->fetch_assoc()) {
                            $completed_payments[] = [
                                'id' => (int)$row['id'],
                                'position' => (int)$row['position'],
                                'user' => [
                                    'id' => (int)$row['user_id'],
                                    'name' => $row['name'],
                                    'email' => $row['email']
                                ],
                                'amount' => (float)$row['amount'],
                                'status' => $row['status']
                            ];
                        }
                        
                        $stmt_select->close();
                    }
                }
                
                $stmt->close();
            }
        }
        
        // Commit da transação
        $conn->commit();
        
        // Retornar sucesso
        echo json_encode([
            'success' => true,
            'message' => "Pagamentos marcados como completos com sucesso!",
            'data' => [
                'updated_count' => $updated_count,
                'transaction_id' => $transaction_id,
                'completed_payments' => $completed_payments,
                'timestamp' => time()
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao marcar pagamentos como completos',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

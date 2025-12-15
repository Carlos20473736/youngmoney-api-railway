<?php
/**
 * API de Reset do Ranking com Pagamentos Automáticos
 * 
 * Endpoint: POST /api/v1/reset/ranking.php
 * 
 * Função: Reseta o ranking diário e cria pagamentos automáticos
 * 
 * Lógica:
 * - Obtém o top 10 do ranking ANTES de resetar
 * - Cria registros de pagamento pendentes na tabela pix_payments
 * - Zera daily_points de todos os usuários
 * - Permite que usuários acumulem pontos novamente
 * 
 * Valores de Pagamento (conforme APK):
 * - 1º lugar: R$ 20,00
 * - 2º lugar: R$ 10,00
 * - 3º lugar: R$ 5,00
 * - 4º ao 10º lugar: R$ 1,00 cada
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
    
    // Obter data e hora atual
    $current_date = date('Y-m-d');
    $current_datetime = date('Y-m-d H:i:s');
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // PASSO 1: Obter o top 10 do ranking ANTES de resetar
        // Tabela de valores de pagamento conforme APK
        $payment_values = [
            1 => 20.00,  // 1º lugar: R$ 20,00
            2 => 10.00,  // 2º lugar: R$ 10,00
            3 => 5.00,   // 3º lugar: R$ 5,00
            4 => 1.00,   // 4º ao 10º lugar: R$ 1,00
            5 => 1.00,
            6 => 1.00,
            7 => 1.00,
            8 => 1.00,
            9 => 1.00,
            10 => 1.00
        ];
        
        // Buscar top 10 do ranking com suas chaves PIX (agora na tabela users)
        $stmt = $conn->prepare("
            SELECT 
                id as user_id,
                name,
                email,
                daily_points,
                pix_key_type,
                pix_key
            FROM users
            WHERE daily_points > 0
            ORDER BY daily_points DESC, created_at ASC
            LIMIT 10
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $top_10_users = [];
        $position = 1;
        $payments_created = 0;
        $total_payment_amount = 0;
        
        while ($row = $result->fetch_assoc()) {
            $user_id = $row['user_id'];
            $amount = $payment_values[$position] ?? 1.00;
            
            $top_10_users[] = [
                'position' => $position,
                'user_id' => $user_id,
                'name' => $row['name'],
                'email' => $row['email'],
                'daily_points' => (int)$row['daily_points'],
                'pix_key_type' => $row['pix_key_type'],
                'pix_key' => $row['pix_key'],
                'payment_amount' => $amount
            ];
            
            // PASSO 2: Criar registro de pagamento pendente
            // Apenas criar pagamento se o usuário tem chave PIX
            if (!empty($row['pix_key'])) {
                $stmt_payment = $conn->prepare("
                    INSERT INTO pix_payments 
                    (user_id, position, amount, pix_key_type, pix_key, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ");
                
                if (!$stmt_payment) {
                    throw new Exception("Prepare payment failed: " . $conn->error);
                }
                
                $stmt_payment->bind_param(
                    "iidss",
                    $user_id,
                    $position,
                    $amount,
                    $row['pix_key_type'],
                    $row['pix_key']
                );
                
                if (!$stmt_payment->execute()) {
                    throw new Exception("Execute payment failed: " . $stmt_payment->error);
                }
                
                $stmt_payment->close();
                $payments_created++;
                $total_payment_amount += $amount;
            }
            
            $position++;
        }
        
        $stmt->close();
        
        // PASSO 3: Contar quantos usuários têm daily_points > 0
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM users 
            WHERE daily_points > 0
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $usersAffected = $row['total'] ?? 0;
        $stmt->close();
        
        // PASSO 4: Resetar daily_points para 0 para todos os usuários
        $stmt = $conn->prepare("
            UPDATE users 
            SET daily_points = 0
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Commit da transação
        $conn->commit();
        
        // Retornar sucesso com informações dos pagamentos criados
        echo json_encode([
            'success' => true,
            'message' => 'Ranking resetado com sucesso! Pagamentos pendentes criados.',
            'data' => [
                'reset_type' => 'ranking',
                'description' => 'Todos os usuários tiveram daily_points zerado. Pagamentos pendentes criados para o top 10.',
                'users_affected' => $usersAffected,
                'daily_points_reset_to' => 0,
                'reset_date' => $current_date,
                'reset_datetime' => $current_datetime,
                'timezone' => 'America/Sao_Paulo (GMT-3)',
                'timestamp' => time(),
                'payments' => [
                    'total_created' => $payments_created,
                    'total_amount' => round($total_payment_amount, 2),
                    'status' => 'pending',
                    'top_10_ranking' => $top_10_users
                ]
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
        'error' => 'Erro ao executar reset do ranking',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

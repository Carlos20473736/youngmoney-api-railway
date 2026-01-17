<?php
/**
 * API de Reset do Ranking Agendada com Pagamentos Automáticos
 * 
 * Endpoint: POST /api/v1/reset/ranking_scheduled.php
 * 
 * Função: Reseta o ranking diário baseado na hora configurada no painel ADM
 *         e cria pagamentos automáticos para o top 10 do ranking
 * 
 * Lógica:
 * - Lê a hora de reset configurada em system_settings (reset_time)
 * - Verifica se é a hora certa para resetar
 * - Obtém o top 10 do ranking ANTES de resetar
 * - Cria registros de pagamento pendentes na tabela pix_payments
 * - Zera daily_points de todos os usuários
 * - Permite que usuários acumulem pontos novamente
 * - CORRIGIDO: Agora cria cooldowns para os vencedores
 * 
 * Valores de Pagamento (padronizado):
 * - 1º lugar: R$ 10,00
 * - 2º lugar: R$ 5,00
 * - 3º lugar: R$ 2,50
 * - 4º ao 10º lugar: R$ 1,00 cada
 * 
 * Sistema de Cooldown:
 * - Top 1, 2, 3: 2 dias de cooldown
 * - Top 4 a 10: 1 dia de cooldown
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
    $current_time = date('H:i:s');
    $current_datetime = date('Y-m-d H:i:s');
    
    // Buscar hora de reset configurada no painel ADM
    $stmt = $conn->prepare("
        SELECT setting_value 
        FROM system_settings 
        WHERE setting_key = 'reset_time' 
        LIMIT 1
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $reset_time = $row['setting_value'] ?? '00:00:00';
    $stmt->close();
    
    // Verificar se é a hora certa para resetar (com margem de 1 minuto)
    $reset_hour = substr($reset_time, 0, 2);
    $reset_minute = substr($reset_time, 3, 2);
    $current_hour = date('H');
    $current_minute = date('i');
    
    $is_reset_time = ($current_hour === $reset_hour && $current_minute === $reset_minute);
    
    // SE NAO FOR A HORA CERTA, RETORNAR SEM FAZER RESET
    if (!$is_reset_time) {
        echo json_encode([
            'success' => true,
            'message' => 'Reset agendado, mas ainda nao eh a hora certa',
            'data' => [
                'reset_type' => 'ranking_scheduled',
                'description' => 'Reset nao foi executado - aguardando horario configurado',
                'reset_time_configured' => $reset_time,
                'is_reset_time' => false,
                'current_time' => $current_time,
                'users_affected' => 0,
                'payments_created' => 0,
                'reset_date' => $current_date,
                'reset_datetime' => $current_datetime,
                'timezone' => 'America/Sao_Paulo (GMT-3)',
                'timestamp' => time()
            ]
        ], JSON_UNESCAPED_UNICODE);
        $conn->close();
        exit;
    }
    
    // Iniciar transacao
    $conn->begin_transaction();
    
    try {
        // ============================================
        // CRIAR TABELA DE COOLDOWNS SE NÃO EXISTIR
        // ============================================
        $conn->query("
            CREATE TABLE IF NOT EXISTS ranking_cooldowns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                position INT NOT NULL COMMENT 'Posição no ranking quando ganhou',
                prize_amount DECIMAL(10,2) NOT NULL COMMENT 'Valor do prêmio recebido',
                cooldown_days INT NOT NULL COMMENT 'Dias de cooldown (1 ou 2)',
                cooldown_until DATETIME NOT NULL COMMENT 'Data/hora até quando está bloqueado',
                reset_date DATE NOT NULL COMMENT 'Data do reset que gerou o cooldown',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_cooldown (user_id, cooldown_until),
                INDEX idx_cooldown_until (cooldown_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // PASSO 1: Obter o top 10 do ranking ANTES de resetar
        // Tabela de valores de pagamento (padronizado)
        $payment_values = [
            1 => 10.00,  // 1º lugar: R$ 10,00
            2 => 5.00,   // 2º lugar: R$ 5,00
            3 => 2.50,   // 3º lugar: R$ 2,50
            4 => 1.00,   // 4º ao 10º lugar: R$ 1,00
            5 => 1.00,
            6 => 1.00,
            7 => 1.00,
            8 => 1.00,
            9 => 1.00,
            10 => 1.00
        ];
        
        // Dias de cooldown por posição
        // Top 1-3: 2 dias | Top 4-10: 1 dia
        $cooldownDays = [
            1 => 2,
            2 => 2,
            3 => 2,
            4 => 1,
            5 => 1,
            6 => 1,
            7 => 1,
            8 => 1,
            9 => 1,
            10 => 1
        ];
        
        // Buscar top 10 do ranking com suas chaves PIX (agora na tabela users)
        // IMPORTANTE: Apenas usuários com PIX cadastrado e SEM cooldown ativo participam do ranking
        $stmt = $conn->prepare("
            SELECT 
                u.id as user_id,
                u.name,
                u.email,
                u.daily_points,
                u.pix_key_type,
                u.pix_key
            FROM users u
            LEFT JOIN ranking_cooldowns rc ON u.id = rc.user_id AND rc.cooldown_until > ?
            WHERE u.daily_points > 0
              AND u.pix_key IS NOT NULL 
              AND u.pix_key != ''
              AND rc.id IS NULL
            ORDER BY u.daily_points DESC, u.created_at ASC
            LIMIT 10
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $current_datetime);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $top_10_users = [];
        $position = 1;
        $payments_created = 0;
        $total_payment_amount = 0;
        $cooldowns_created = [];
        
        while ($row = $result->fetch_assoc()) {
            $user_id = $row['user_id'];
            $amount = $payment_values[$position] ?? 1.00;
            $userCooldownDays = $cooldownDays[$position] ?? 1;
            
            $top_10_users[] = [
                'position' => $position,
                'user_id' => $user_id,
                'name' => $row['name'],
                'email' => $row['email'],
                'daily_points' => (int)$row['daily_points'],
                'pix_key_type' => $row['pix_key_type'],
                'pix_key' => $row['pix_key'],
                'payment_amount' => $amount,
                'cooldown_days' => $userCooldownDays
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
            
            // ============================================
            // PASSO 3: REGISTRAR COOLDOWN PARA O VENCEDOR
            // ============================================
            $cooldownUntil = date('Y-m-d H:i:s', strtotime("+{$userCooldownDays} days"));
            
            $stmt_cooldown = $conn->prepare("
                INSERT INTO ranking_cooldowns 
                (user_id, position, prize_amount, cooldown_days, cooldown_until, reset_date, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if (!$stmt_cooldown) {
                throw new Exception("Prepare cooldown failed: " . $conn->error);
            }
            
            $stmt_cooldown->bind_param("iidiss", 
                $user_id, 
                $position, 
                $amount, 
                $userCooldownDays, 
                $cooldownUntil, 
                $current_date
            );
            
            if (!$stmt_cooldown->execute()) {
                throw new Exception("Execute cooldown failed: " . $stmt_cooldown->error);
            }
            
            $stmt_cooldown->close();
            
            $cooldowns_created[] = [
                'user_id' => $user_id,
                'name' => $row['name'],
                'position' => $position,
                'cooldown_days' => $userCooldownDays,
                'cooldown_until' => $cooldownUntil
            ];
            
            $position++;
        }
        
        $stmt->close();
        
        // PASSO 4: Coletar IDs do top 10 para resetar apenas eles
        $top_10_ids = array_column($top_10_users, 'user_id');
        $usersAffected = count($top_10_ids);
        
        // PASSO 5: Resetar daily_points para 0 APENAS para o top 10
        if (!empty($top_10_ids)) {
            $placeholders = implode(',', array_fill(0, count($top_10_ids), '?'));
            $stmt = $conn->prepare("
                UPDATE users 
                SET daily_points = 0
                WHERE id IN ($placeholders)
            ");
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            // Bind dos IDs dinamicamente
            $types = str_repeat('i', count($top_10_ids));
            $stmt->bind_param($types, ...$top_10_ids);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $stmt->close();
        }
        
        // PASSO 6: Registrar log do reset
        $stmt = $conn->prepare("
            INSERT INTO ranking_reset_logs 
            (users_affected, reset_datetime) 
            VALUES (?, NOW())
        ");
        
        if ($stmt) {
            $stmt->bind_param("i", $usersAffected);
            $stmt->execute();
            $stmt->close();
        }
        
        // Commit da transação
        $conn->commit();
        
        // Retornar sucesso com informações dos pagamentos criados
        echo json_encode([
            'success' => true,
            'message' => 'Reset do ranking executado com sucesso! Pagamentos pendentes e cooldowns criados.',
            'data' => [
                'reset_type' => 'ranking_scheduled',
                'description' => 'Apenas o top 10 teve daily_points zerado. Demais usuários mantiveram seus pontos. Pagamentos pendentes e cooldowns criados para o top 10.',
                'reset_time_configured' => $reset_time,
                'is_reset_time' => $is_reset_time,
                'current_time' => $current_time,
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
                ],
                'cooldowns' => [
                    'total_created' => count($cooldowns_created),
                    'details' => $cooldowns_created
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

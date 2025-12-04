<?php
/**
 * Endpoint de Reset Automático Diário
 * 
 * Este endpoint é chamado pelo cron-job.org a cada minuto.
 * Reseta TUDO quando o horário configurado no painel admin for atingido:
 * 
 * 1. Ranking (daily_points = 0)
 * 2. Spin (DELETE registros de HOJE)
 * 3. Check-in (Atualiza last_reset_datetime - histórico preservado)
 * 
 * Lógica:
 * - Busca o horário configurado no painel admin
 * - Busca o último horário em que o reset foi executado
 * - Se horário atual >= horário configurado E ainda não resetou neste horário:
 *   * Reseta daily_points de todos os usuários
 *   * Deleta registros de spin_history de HOJE
 *   * Deleta registros de daily_checkin de HOJE
 *   * Atualiza last_reset_time
 */

// Verificar token de segurança
$token = $_GET['token'] ?? '';
$expectedToken = 'ym_auto_reset_2024_secure_xyz';

if ($token !== $expectedToken) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Token inválido'
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
    ]);
    
    // Obter horário atual
    $current_time = date('H:i');
    $current_date = date('Y-m-d');
    $current_datetime = date('Y-m-d H:i:s');
    $current_hour = (int)date('H');
    $current_minute = (int)date('i');
    
    // Buscar horário configurado no painel admin
    $stmt = $pdo->prepare("
        SELECT setting_value 
        FROM system_settings 
        WHERE setting_key = 'reset_time'
        LIMIT 1
    ");
    $stmt->execute();
    $reset_time_config = $stmt->fetch();
    
    // Se não houver configuração, retornar erro
    if (!$reset_time_config || empty($reset_time_config['setting_value'])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Horário de reset não configurado no painel admin'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $reset_time = $reset_time_config['setting_value'];
    
    // Extrair hora e minuto do horário configurado
    list($reset_hour, $reset_minute) = explode(':', $reset_time);
    $reset_hour = (int)$reset_hour;
    $reset_minute = (int)$reset_minute;
    
    // Buscar último horário de reset
    $stmt = $pdo->prepare("
        SELECT setting_value 
        FROM system_settings 
        WHERE setting_key = 'last_reset_time'
        LIMIT 1
    ");
    $stmt->execute();
    $last_reset = $stmt->fetch();
    $last_reset_time = $last_reset ? $last_reset['setting_value'] : null;
    
    // Verificar se deve resetar
    $should_reset = false;
    $reason = '';
    
    // Converter horários para minutos totais do dia
    $current_minutes_total = ($current_hour * 60) + $current_minute;
    $reset_minutes_total = ($reset_hour * 60) + $reset_minute;
    
    // Condição: Horário atual >= horário configurado E ainda não resetou neste horário
    $time_reached = ($current_minutes_total >= $reset_minutes_total);
    $not_reset_at_this_time = ($last_reset_time !== $reset_time);
    
    if ($time_reached && $not_reset_at_this_time) {
        $should_reset = true;
        $reason = 'Horário de reset atingido';
    } elseif (!$time_reached) {
        $reason = "Horário atual ($current_time) ainda não atingiu o horário configurado ($reset_time)";
    } elseif (!$not_reset_at_this_time) {
        $reason = "Já resetou no horário configurado ($reset_time)";
    }
    
    // Se não deve resetar, retornar informações
    if (!$should_reset) {
        echo json_encode([
            'success' => true,
            'reset_executed' => false,
            'reason' => $reason,
            'current_time' => $current_time,
            'configured_reset_time' => $reset_time,
            'last_reset_time' => $last_reset_time,
            'current_date' => $current_date,
            'timezone' => 'America/Sao_Paulo (GMT-3)',
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ============================================
    // RESETAR TUDO
    // ============================================
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    // 1. RANKING - Contar e resetar daily_points
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE daily_points > 0");
    $usersAffected = $countStmt->fetch()['total'];
    $pdo->exec("UPDATE users SET daily_points = 0");
    
    // 2. SPIN - Deletar registros de HOJE
    $spinsDeleted = $pdo->exec("DELETE FROM spin_history WHERE DATE(created_at) = '$current_date'");
    
    // 3. CHECK-IN - Atualizar last_reset_datetime (histórico preservado)
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_at)
        VALUES ('last_reset_datetime', :datetime, NOW())
        ON DUPLICATE KEY UPDATE 
            setting_value = :datetime,
            updated_at = NOW()
    ");
    $stmt->execute(['datetime' => $current_datetime]);
    $checkinsDeleted = 0; // Não deleta, apenas atualiza timestamp
    
    // 4. Atualizar último horário de reset
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_at)
        VALUES ('last_reset_time', :time, NOW())
        ON DUPLICATE KEY UPDATE 
            setting_value = :time,
            updated_at = NOW()
    ");
    $stmt->execute(['time' => $reset_time]);
    
    // 5. Registrar log do reset (opcional)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ranking_reset_logs 
            (reset_type, triggered_by, users_affected, status, reset_time) 
            VALUES ('automatic', 'cron-job.org', :users, 'success', NOW())
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
        'reset_executed' => true,
        'message' => 'Reset diário executado com sucesso!',
        'data' => [
            'ranking' => [
                'users_affected' => $usersAffected,
                'description' => 'daily_points resetado para 0'
            ],
            'spin' => [
                'records_deleted' => $spinsDeleted,
                'description' => 'Registros de giros de hoje deletados'
            ],
            'checkin' => [
                'records_deleted' => $checkinsDeleted,
                'description' => 'Usa dia virtual baseado em last_reset_datetime - histórico preservado'
            ],
            'reset_date' => $current_date,
            'reset_time' => $current_time,
            'configured_reset_time' => $reset_time,
            'timezone' => 'America/Sao_Paulo (GMT-3)',
            'timestamp' => time()
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    // Rollback em caso de erro
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao conectar ao banco de dados: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao executar reset: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

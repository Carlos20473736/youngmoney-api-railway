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
 */

header('Content-Type: application/json');

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

// Incluir arquivo de conexão
require_once __DIR__ . '/../../../database.php';

try {
    // Usar a função de conexão padrão
    $mysqli = getDbConnection();
    
    // Obter horário atual
    $current_time = date('H:i');
    $current_date = date('Y-m-d');
    $current_datetime = date('Y-m-d H:i:s');
    $current_hour = (int)date('H');
    $current_minute = (int)date('i');
    
    // Buscar horário configurado no painel admin
    $stmt = $mysqli->prepare("
        SELECT setting_value 
        FROM system_settings 
        WHERE setting_key = 'reset_time'
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $reset_time_config = $result->fetch_assoc();
    
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
    $stmt = $mysqli->prepare("
        SELECT setting_value 
        FROM system_settings 
        WHERE setting_key = 'last_reset_time'
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $last_reset = $result->fetch_assoc();
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
    $mysqli->begin_transaction();
    
    // ============================================
    // 0. PAGAR PRÊMIOS DO RANKING (Top 10)
    // ============================================
    
    // Valores dos prêmios por posição (em reais)
    $rankingPrizes = [
        1 => 10.00,  // 1º lugar: R$ 10,00
        2 => 5.00,   // 2º lugar: R$ 5,00
        3 => 2.50,   // 3º lugar: R$ 2,50
        4 => 1.00,   // 4º lugar: R$ 1,00
        5 => 1.00,   // 5º lugar: R$ 1,00
        6 => 1.00,   // 6º lugar: R$ 1,00
        7 => 1.00,   // 7º lugar: R$ 1,00
        8 => 1.00,   // 8º lugar: R$ 1,00
        9 => 1.00,   // 9º lugar: R$ 1,00
        10 => 1.00   // 10º lugar: R$ 1,00
    ];
    
    // Buscar Top 10 do ranking (apenas usuários com pontos > 0)
    $topRankingResult = $mysqli->query("
        SELECT id, name, email, daily_points, pix_key, pix_key_type 
        FROM users 
        WHERE daily_points > 0 
        ORDER BY daily_points DESC 
        LIMIT 10
    ");
    
    $prizesAwarded = [];
    $position = 1;
    
    while ($user = $topRankingResult->fetch_assoc()) {
        $prizeAmount = $rankingPrizes[$position] ?? 0;
        
        if ($prizeAmount > 0) {
            // Verificar se usuário tem PIX cadastrado
            $hasPixKey = !empty($user['pix_key']) && !empty($user['pix_key_type']);
            
            if ($hasPixKey) {
                // Criar solicitação de saque automática (status: pending)
                $stmt = $mysqli->prepare("
                    INSERT INTO withdrawals (user_id, amount, pix_key, pix_key_type, status, description, created_at)
                    VALUES (?, ?, ?, ?, 'pending', ?, NOW())
                ");
                $description = "Prêmio Ranking Diário - {$position}º lugar";
                $stmt->bind_param("idsss", 
                    $user['id'], 
                    $prizeAmount, 
                    $user['pix_key'], 
                    $user['pix_key_type'],
                    $description
                );
                $stmt->execute();
                
                $prizesAwarded[] = [
                    'position' => $position,
                    'user_id' => $user['id'],
                    'name' => $user['name'],
                    'points' => $user['daily_points'],
                    'prize' => $prizeAmount,
                    'status' => 'withdrawal_created',
                    'pix_key' => substr($user['pix_key'], 0, 4) . '****' // Mascarar PIX
                ];
            } else {
                // Usuário sem PIX - converter prêmio em pontos (R$ 1,00 = 10.000 pontos)
                $pointsBonus = intval($prizeAmount * 10000);
                
                $stmt = $mysqli->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                $stmt->bind_param("ii", $pointsBonus, $user['id']);
                $stmt->execute();
                
                // Registrar no histórico de pontos
                $stmt = $mysqli->prepare("
                    INSERT INTO points_history (user_id, points, description, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $description = "Prêmio Ranking Diário - {$position}º lugar (convertido em pontos - sem PIX cadastrado)";
                $stmt->bind_param("iis", $user['id'], $pointsBonus, $description);
                $stmt->execute();
                
                $prizesAwarded[] = [
                    'position' => $position,
                    'user_id' => $user['id'],
                    'name' => $user['name'],
                    'points' => $user['daily_points'],
                    'prize' => $prizeAmount,
                    'status' => 'converted_to_points',
                    'points_bonus' => $pointsBonus
                ];
            }
        }
        
        $position++;
    }
    
    // 1. RANKING - Contar e resetar daily_points
    $countResult = $mysqli->query("SELECT COUNT(*) as total FROM users WHERE daily_points > 0");
    $usersAffected = $countResult->fetch_assoc()['total'];
    $mysqli->query("UPDATE users SET daily_points = 0");
    
    // 2. SPIN - Deletar registros de HOJE
    $spinsDeleted = $mysqli->query("DELETE FROM spin_history WHERE DATE(created_at) = '$current_date'");
    $spinsDeletedCount = $mysqli->affected_rows;
    
    // 3. CHECK-IN - Atualizar last_reset_datetime (histórico preservado)
    $stmt = $mysqli->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_at)
        VALUES ('last_reset_datetime', ?, NOW())
        ON DUPLICATE KEY UPDATE 
            setting_value = ?,
            updated_at = NOW()
    ");
    $stmt->bind_param("ss", $current_datetime, $current_datetime);
    $stmt->execute();
    
    // 4. Atualizar último horário de reset
    $stmt = $mysqli->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_at)
        VALUES ('last_reset_time', ?, NOW())
        ON DUPLICATE KEY UPDATE 
            setting_value = ?,
            updated_at = NOW()
    ");
    $stmt->bind_param("ss", $reset_time, $reset_time);
    $stmt->execute();
    
    // 5. Registrar log do reset (opcional)
    try {
        $stmt = $mysqli->prepare("
            INSERT INTO ranking_reset_logs 
            (reset_type, triggered_by, users_affected, status, reset_time) 
            VALUES ('automatic', 'cron-job.org', ?, 'success', NOW())
        ");
        $stmt->bind_param("i", $usersAffected);
        $stmt->execute();
    } catch (Exception $e) {
        // Tabela de logs pode não existir, ignorar erro
    }
    
    // Commit da transação
    $mysqli->commit();
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'reset_executed' => true,
        'message' => 'Reset diário executado com sucesso!',
        'data' => [
            'prizes' => [
                'total_awarded' => count($prizesAwarded),
                'details' => $prizesAwarded,
                'description' => 'Prêmios do ranking pagos antes do reset'
            ],
            'ranking' => [
                'users_affected' => $usersAffected,
                'description' => 'daily_points resetado para 0'
            ],
            'spin' => [
                'records_deleted' => $spinsDeletedCount,
                'description' => 'Registros de giros de hoje deletados'
            ],
            'checkin' => [
                'records_deleted' => 0,
                'description' => 'Usa dia virtual baseado em last_reset_datetime - histórico preservado'
            ],
            'reset_date' => $current_date,
            'reset_time' => $current_time,
            'configured_reset_time' => $reset_time,
            'timezone' => 'America/Sao_Paulo (GMT-3)',
            'timestamp' => time()
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($mysqli) && $mysqli->ping()) {
        $mysqli->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao executar reset: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

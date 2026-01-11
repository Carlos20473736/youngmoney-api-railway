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
 * NOVO: Sistema de Cooldown para vencedores do ranking
 * - Top 1, 2, 3: 2 dias de cooldown
 * - Top 4 a 10: 1 dia de cooldown
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
    
    // ============================================
    // MODIFICADO: RESET A QUALQUER MOMENTO
    // ============================================
    // O reset agora pode ser executado a qualquer hora
    // Sem restrição de horário
    $should_reset = true;
    $reason = 'Reset executado a qualquer momento (sem restrição de horário)';
    
    // ============================================
    // RESETAR TUDO
    // ============================================
    
    // Iniciar transação
    $mysqli->begin_transaction();
    
    // ============================================
    // CRIAR TABELA DE COOLDOWNS SE NÃO EXISTIR
    // ============================================
    $mysqli->query("
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
    
    // Buscar Top 10 do ranking (apenas usuários com pontos > 0)
    $topRankingResult = $mysqli->query("
        SELECT id, name, email, daily_points, pix_key, pix_key_type 
        FROM users 
        WHERE daily_points > 0 
        ORDER BY daily_points DESC 
        LIMIT 10
    ");
    
    $prizesAwarded = [];
    $cooldownsCreated = [];
    $position = 1;
    
    while ($user = $topRankingResult->fetch_assoc()) {
        $prizeAmount = $rankingPrizes[$position] ?? 0;
        $userCooldownDays = $cooldownDays[$position] ?? 1;
        
        if ($prizeAmount > 0) {
            // Verificar se usuário tem PIX cadastrado
            $hasPixKey = !empty($user['pix_key']) && !empty($user['pix_key_type']);
            
            if ($hasPixKey) {
                // Criar solicitação de saque automática (status: pending)
                $stmt = $mysqli->prepare("
                    INSERT INTO withdrawals (user_id, amount, pix_key, pix_type, status, created_at)
                    VALUES (?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->bind_param("idss", 
                    $user['id'], 
                    $prizeAmount, 
                    $user['pix_key'], 
                    $user['pix_key_type']
                );
                $stmt->execute();
                
                $prizesAwarded[] = [
                    'position' => $position,
                    'user_id' => $user['id'],
                    'name' => $user['name'],
                    'points' => $user['daily_points'],
                    'prize' => $prizeAmount,
                    'status' => 'withdrawal_created',
                    'pix_key' => substr($user['pix_key'], 0, 4) . '****', // Mascarar PIX
                    'cooldown_days' => $userCooldownDays
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
                    'points_bonus' => $pointsBonus,
                    'cooldown_days' => $userCooldownDays
                ];
            }
            
            // ============================================
            // REGISTRAR COOLDOWN PARA O VENCEDOR
            // ============================================
            $cooldownUntil = date('Y-m-d H:i:s', strtotime("+{$userCooldownDays} days"));
            
            $stmt = $mysqli->prepare("
                INSERT INTO ranking_cooldowns 
                (user_id, position, prize_amount, cooldown_days, cooldown_until, reset_date, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("iidiss", 
                $user['id'], 
                $position, 
                $prizeAmount, 
                $userCooldownDays, 
                $cooldownUntil, 
                $current_date
            );
            $stmt->execute();
            
            $cooldownsCreated[] = [
                'user_id' => $user['id'],
                'name' => $user['name'],
                'position' => $position,
                'cooldown_days' => $userCooldownDays,
                'cooldown_until' => $cooldownUntil
            ];
        }
        
        $position++;
    }
    
    // 1. RANKING - Contar e resetar daily_points APENAS DO TOP 10
    // CORRIGIDO: Antes estava zerando TODOS os usuários, agora zera apenas o top 10
    $countResult = $mysqli->query("SELECT COUNT(*) as total FROM users WHERE daily_points > 0");
    $totalUsersWithPoints = $countResult->fetch_assoc()['total'];
    
    // Coletar IDs do top 10 que já foram processados
    $top10UserIds = array_column($prizesAwarded, 'user_id');
    
    // Resetar APENAS os usuários do top 10
    $usersAffected = 0;
    if (!empty($top10UserIds)) {
        $placeholders = implode(',', array_fill(0, count($top10UserIds), '?'));
        $types = str_repeat('i', count($top10UserIds));
        
        $stmt = $mysqli->prepare("UPDATE users SET daily_points = 0 WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$top10UserIds);
        $stmt->execute();
        $usersAffected = $stmt->affected_rows;
        $stmt->close();
    }
    
    // Log: Usuários do 11º em diante MANTÊM seus pontos
    $usersKeptPoints = $totalUsersWithPoints - $usersAffected;
    
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
    
    // 4. Atualizar último horário de reset (com timestamp completo para rastrear resets a qualquer hora)
    $stmt = $mysqli->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_at)
        VALUES ('last_reset_time', ?, NOW())
        ON DUPLICATE KEY UPDATE 
            setting_value = ?,
            updated_at = NOW()
    ");
     $stmt->bind_param("ss", $current_datetime, $current_datetime);
    $stmt->execute();
    
    // 5. MONETAG - Randomizar número de impressões necessárias (5 a 30)
    $random_impressions = rand(5, 10);
    
    // Verificar se a configuração já existe
    $check_stmt = $mysqli->prepare("
        SELECT id FROM roulette_settings 
        WHERE setting_key = 'monetag_required_impressions'
    ");
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Atualizar valor existente
        $stmt = $mysqli->prepare("
            UPDATE roulette_settings 
            SET setting_value = ?, updated_at = NOW()
            WHERE setting_key = 'monetag_required_impressions'
        ");
        $stmt->bind_param("s", $random_impressions);
        $stmt->execute();
    } else {
        // Inserir novo valor
        $stmt = $mysqli->prepare("
            INSERT INTO roulette_settings (setting_key, setting_value, description)
            VALUES ('monetag_required_impressions', ?, 'Número de impressões necessárias para desbloquear roleta')
        ");
        $stmt->bind_param("s", $random_impressions);
        $stmt->execute();
    }
    $check_stmt->close();
    
    // 6. MONETAG - Deletar eventos de monetag de todos os usuários (resetar progresso)
    $monetagDeleted = $mysqli->query("DELETE FROM monetag_events");
    $monetagDeletedCount = $mysqli->affected_rows;
    
    // 7. Registrar log do reset (opcional)
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
            'cooldowns' => [
                'total_created' => count($cooldownsCreated),
                'details' => $cooldownsCreated,
                'description' => 'Cooldowns aplicados: Top 1-3 = 2 dias, Top 4-10 = 1 dia'
            ],
            'ranking' => [
                'users_reset' => $usersAffected,
                'users_kept_points' => $usersKeptPoints,
                'total_users_with_points' => $totalUsersWithPoints,
                'top10_ids' => $top10UserIds,
                'description' => 'APENAS Top 10 teve daily_points resetado para 0. Demais usuários mantêm seus pontos.'
            ],
            'spin' => [
                'records_deleted' => $spinsDeletedCount,
                'description' => 'Registros de giros de hoje deletados'
            ],
            'checkin' => [
                'records_deleted' => 0,
                'description' => 'Usa dia virtual baseado em last_reset_datetime - histórico preservado'
            ],
            'monetag' => [
                'events_deleted' => $monetagDeletedCount,
                'new_required_impressions' => $random_impressions,
                'description' => 'Eventos resetados e impressões randomizadas (5-30)'
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

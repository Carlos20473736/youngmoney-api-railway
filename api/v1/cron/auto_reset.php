<?php
/**
 * Endpoint de Reset Automático Diário - CORRIGIDO
 * 
 * Este endpoint é chamado pelo cron-job.org a cada minuto.
 * Reseta APENAS às 20:00 (8 da noite) horário de Brasília:
 * 
 * 1. Ranking (daily_points = 0) - APENAS TOP 10
 * 2. Spin (DELETE registros de HOJE)
 * 3. Check-in (Atualiza last_reset_datetime - histórico preservado)
 * 
 * NOVO: Sistema de Cooldown para vencedores do ranking
 * - Top 1, 2, 3: 2 dias de cooldown
 * - Top 4 a 10: 1 dia de cooldown
 * 
 * CORREÇÃO: Agora verifica se é exatamente 20:00 e se já não foi resetado hoje
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

// Configurar timezone para Brasília
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
    
    // ============================================
    // HORÁRIO FIXO: 20:00 (8 da noite)
    // ============================================
    $reset_hour = 20;  // 8 da noite
    $reset_minute = 0; // em ponto
    $reset_time = '20:00';
    
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
    // VERIFICAÇÃO DE HORÁRIO - CORRIGIDO
    // ============================================
    // Só executa o reset se:
    // 1. For exatamente 20:00 (hora e minuto)
    // 2. Não tiver sido executado hoje ainda
    
    $should_reset = false;
    $reason = '';
    
    // Verificar se já foi feito reset hoje
    $last_reset_date = null;
    if ($last_reset_time) {
        $last_reset_date = date('Y-m-d', strtotime($last_reset_time));
    }
    
    $already_reset_today = ($last_reset_date === $current_date);
    
    // Verificar se é a hora certa (20:00)
    $is_reset_time = ($current_hour === $reset_hour && $current_minute === $reset_minute);
    
    if ($already_reset_today) {
        $should_reset = false;
        $reason = 'Reset já foi executado hoje às ' . date('H:i:s', strtotime($last_reset_time));
    } elseif (!$is_reset_time) {
        $should_reset = false;
        $reason = 'Ainda não é o horário de reset. Horário atual: ' . $current_time . '. Reset programado para: ' . $reset_time;
    } else {
        $should_reset = true;
        $reason = 'Horário de reset atingido (20:00) e ainda não foi executado hoje';
    }
    
    // ============================================
    // SE NÃO FOR HORA DE RESETAR, RETORNAR
    // ============================================
    if (!$should_reset) {
        echo json_encode([
            'success' => true,
            'reset_executed' => false,
            'message' => $reason,
            'data' => [
                'current_time' => $current_time,
                'current_date' => $current_date,
                'configured_reset_time' => $reset_time,
                'last_reset' => $last_reset_time,
                'already_reset_today' => $already_reset_today,
                'is_reset_time' => $is_reset_time,
                'timezone' => 'America/Sao_Paulo (GMT-3)'
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ============================================
    // RESETAR TUDO (apenas se passou nas verificações)
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
    
    // Cooldown por posição (em horas)
    // Top 1-3: 1 dia (24 horas) | Top 4-10: 2 horas
    $cooldownHours = [
        1 => 24,
        2 => 24,
        3 => 24,
        4 => 2,
        5 => 2,
        6 => 2,
        7 => 2,
        8 => 2,
        9 => 2,
        10 => 2
    ];
    
    // Buscar Top 10 do ranking (apenas usuários com pontos > 0, PIX cadastrado e SEM cooldown ativo)
    $topRankingResult = $mysqli->query("
        SELECT u.id, u.name, u.email, u.daily_points, u.pix_key, u.pix_key_type 
        FROM users u
        LEFT JOIN ranking_cooldowns rc ON u.id = rc.user_id AND rc.cooldown_until > NOW()
        WHERE u.daily_points > 0 
          AND u.pix_key IS NOT NULL 
          AND u.pix_key != ''
          AND rc.id IS NULL
        ORDER BY u.daily_points DESC 
        LIMIT 10
    ");
    
    $prizesAwarded = [];
    $cooldownsCreated = [];
    $position = 1;
    
    while ($user = $topRankingResult->fetch_assoc()) {
        $prizeAmount = $rankingPrizes[$position] ?? 0;
        $userCooldownHours = $cooldownHours[$position] ?? 2;
        
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
                    'cooldown_hours' => $userCooldownHours
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
                    'cooldown_hours' => $userCooldownHours
                ];
            }
            
            // ============================================
            // REGISTRAR COOLDOWN PARA O VENCEDOR
            // ============================================
            $cooldownUntil = date('Y-m-d H:i:s', strtotime("+{$userCooldownHours} hours"));
            
            $stmt = $mysqli->prepare("
                INSERT INTO ranking_cooldowns 
                (user_id, position, prize_amount, cooldown_days, cooldown_until, reset_date, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("iidiss", 
                $user['id'], 
                $position, 
                $prizeAmount, 
                $userCooldownHours, 
                $cooldownUntil, 
                $current_date
            );
            $stmt->execute();
            
            $cooldownsCreated[] = [
                'user_id' => $user['id'],
                'name' => $user['name'],
                'position' => $position,
                'cooldown_hours' => $userCooldownHours,
                'cooldown_until' => $cooldownUntil
            ];
        }
        
        $position++;
    }
    
    // ============================================
    // 1. RANKING - Resetar daily_points APENAS DO TOP 10
    // ============================================
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
    
    // 4. Atualizar último horário de reset
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
    $random_impressions = rand(5, 30);
    
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
    
    // 7. CANDY CRUSH - Resetar níveis do jogo (volta todos para level 1)
    $candyLevelsReset = 0;
    $candyResult = $mysqli->query("UPDATE game_levels SET level = 1, highest_level = 1, last_level_score = 0, total_score = 0");
    if ($candyResult) {
        $candyLevelsReset = $mysqli->affected_rows;
    }
    
    // 8. CANDY CRUSH - Resetar progresso de level
    $mysqli->query("DELETE FROM candy_level_progress");
    $candyProgressDeleted = $mysqli->affected_rows;
    
    // 9. CANDY CRUSH - Resetar scores (se tabela existir)
    @$mysqli->query("DELETE FROM candy_scores");
    
    // 10. Registrar log do reset (opcional)
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
        'message' => 'Reset diário executado com sucesso às 20:00!',
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
            'candy_crush' => [
                'levels_reset' => $candyLevelsReset,
                'progress_deleted' => $candyProgressDeleted,
                'description' => 'Todos os níveis do Candy foram resetados para 1'
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

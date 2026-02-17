<?php
/**
 * Admin: Forçar Reset do Ranking Top 10
 * 
 * Endpoint: GET /admin/force_ranking_reset.php?token=ym_force_reset_2024
 * 
 * Este endpoint força a execução do reset do ranking top 10,
 * ignorando as verificações de horário.
 * 
 * Executa:
 * 1. Paga prêmios do Top 10
 * 2. Aplica cooldowns
 * 3. Reseta daily_points do Top 10
 * 4. Reseta spin_history
 * 5. Reseta monetag_events
 * 6. Reseta níveis do Candy Crush
 */

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

// Verificar token de segurança
$token = $_GET['token'] ?? '';
$expectedToken = 'ym_force_reset_2024';

if ($token !== $expectedToken) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Token inválido. Use: ?token=ym_force_reset_2024'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Configurar timezone para Brasília
date_default_timezone_set('America/Sao_Paulo');

try {
    $mysqli = getDbConnection();
    
    $current_time = date('H:i');
    $current_date = date('Y-m-d');
    $current_datetime = date('Y-m-d H:i:s');
    
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
            cooldown_days INT NOT NULL COMMENT 'Horas de cooldown (24 ou 2)',
            cooldown_until DATETIME NOT NULL COMMENT 'Data/hora até quando está bloqueado',
            reset_date DATE NOT NULL COMMENT 'Data do reset que gerou o cooldown',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_cooldown (user_id, cooldown_until),
            INDEX idx_cooldown_until (cooldown_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // ============================================
    // PAGAR PRÊMIOS DO RANKING (Top 10)
    // ============================================
    
    $rankingPrizes = [
        1 => 10.00,
        2 => 5.00,
        3 => 2.50,
        4 => 1.00,
        5 => 1.00,
        6 => 1.00,
        7 => 1.00,
        8 => 1.00,
        9 => 1.00,
        10 => 1.00
    ];
    
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
    
    // Buscar Top 10 do ranking
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
            $hasPixKey = !empty($user['pix_key']) && !empty($user['pix_key_type']);
            
            if ($hasPixKey) {
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
                    'pix_key' => substr($user['pix_key'], 0, 4) . '****',
                    'cooldown_hours' => $userCooldownHours
                ];
            } else {
                $pointsBonus = intval($prizeAmount * 10000);
                
                $stmt = $mysqli->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                $stmt->bind_param("ii", $pointsBonus, $user['id']);
                $stmt->execute();
                
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
            
            // Registrar cooldown
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
    // RANKING - Resetar daily_points APENAS DO TOP 10
    // ============================================
    $countResult = $mysqli->query("SELECT COUNT(*) as total FROM users WHERE daily_points > 0");
    $totalUsersWithPoints = $countResult->fetch_assoc()['total'];
    
    $top10UserIds = array_column($prizesAwarded, 'user_id');
    
    // NOVA LÓGICA v3: Buscar usuários em cooldown para NÃO resetar seus pontos
    $cooldownUserIds = [];
    $cooldownCheckResult = $mysqli->query("SELECT user_id FROM ranking_cooldowns WHERE cooldown_until > NOW()");
    if ($cooldownCheckResult) {
        while ($row = $cooldownCheckResult->fetch_assoc()) {
            $cooldownUserIds[] = (int)$row['user_id'];
        }
    }
    
    // Filtrar: NÃO resetar pontos de quem está em cooldown
    $top10UserIdsFiltered = array_values(array_diff($top10UserIds, $cooldownUserIds));
    
    $usersAffected = 0;
    if (!empty($top10UserIdsFiltered)) {
        $placeholders = implode(',', array_fill(0, count($top10UserIdsFiltered), '?'));
        $types = str_repeat('i', count($top10UserIdsFiltered));
        
        $stmt = $mysqli->prepare("UPDATE users SET daily_points = 0 WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$top10UserIdsFiltered);
        $stmt->execute();
        $usersAffected = $stmt->affected_rows;
        $stmt->close();
    }
    
    $usersKeptPoints = $totalUsersWithPoints - $usersAffected;
    
    // SPIN - Deletar registros de HOJE
    $mysqli->query("DELETE FROM spin_history WHERE DATE(created_at) = '$current_date'");
    $spinsDeletedCount = $mysqli->affected_rows;
    
    // CHECK-IN - Atualizar last_reset_datetime
    $stmt = $mysqli->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_at)
        VALUES ('last_reset_datetime', ?, NOW())
        ON DUPLICATE KEY UPDATE 
            setting_value = ?,
            updated_at = NOW()
    ");
    $stmt->bind_param("ss", $current_datetime, $current_datetime);
    $stmt->execute();
    
    // Atualizar último horário de reset
    $stmt = $mysqli->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_at)
        VALUES ('last_reset_time', ?, NOW())
        ON DUPLICATE KEY UPDATE 
            setting_value = ?,
            updated_at = NOW()
    ");
    $stmt->bind_param("ss", $current_datetime, $current_datetime);
    $stmt->execute();
    
    // MONETAG - Randomizar impressões
    $random_impressions = rand(5, 30);
    
    $check_stmt = $mysqli->prepare("
        SELECT id FROM roulette_settings 
        WHERE setting_key = 'monetag_required_impressions'
    ");
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $stmt = $mysqli->prepare("
            UPDATE roulette_settings 
            SET setting_value = ?, updated_at = NOW()
            WHERE setting_key = 'monetag_required_impressions'
        ");
        $stmt->bind_param("s", $random_impressions);
        $stmt->execute();
    } else {
        $stmt = $mysqli->prepare("
            INSERT INTO roulette_settings (setting_key, setting_value, description)
            VALUES ('monetag_required_impressions', ?, 'Número de impressões necessárias para desbloquear roleta')
        ");
        $stmt->bind_param("s", $random_impressions);
        $stmt->execute();
    }
    $check_stmt->close();
    
    // MONETAG - Deletar eventos
    $mysqli->query("DELETE FROM monetag_events");
    $monetagDeletedCount = $mysqli->affected_rows;
    
    // CANDY CRUSH - Resetar níveis
    $candyLevelsReset = 0;
    $candyResult = $mysqli->query("UPDATE game_levels SET level = 1, highest_level = 1, last_level_score = 0, total_score = 0");
    if ($candyResult) {
        $candyLevelsReset = $mysqli->affected_rows;
    }
    
    $mysqli->query("DELETE FROM candy_level_progress");
    $candyProgressDeleted = $mysqli->affected_rows;
    
    @$mysqli->query("DELETE FROM candy_scores");
    
    // Log do reset
    try {
        $stmt = $mysqli->prepare("
            INSERT INTO ranking_reset_logs 
            (reset_type, triggered_by, users_affected, status, reset_time) 
            VALUES ('manual_force', 'admin', ?, 'success', NOW())
        ");
        $stmt->bind_param("i", $usersAffected);
        $stmt->execute();
    } catch (Exception $e) {
        // Ignorar erro se tabela não existir
    }
    
    // Commit
    $mysqli->commit();
    
    echo json_encode([
        'success' => true,
        'reset_executed' => true,
        'message' => 'Reset FORÇADO do ranking top 10 executado com sucesso!',
        'data' => [
            'prizes' => [
                'total_awarded' => count($prizesAwarded),
                'details' => $prizesAwarded
            ],
            'cooldowns' => [
                'total_created' => count($cooldownsCreated),
                'details' => $cooldownsCreated
            ],
            'ranking' => [
                'users_reset' => $usersAffected,
                'users_kept_points' => $usersKeptPoints,
                'total_users_with_points' => $totalUsersWithPoints,
                'top10_ids' => $top10UserIds
            ],
            'spin' => [
                'records_deleted' => $spinsDeletedCount
            ],
            'monetag' => [
                'events_deleted' => $monetagDeletedCount,
                'new_required_impressions' => $random_impressions
            ],
            'candy_crush' => [
                'levels_reset' => $candyLevelsReset,
                'progress_deleted' => $candyProgressDeleted
            ],
            'reset_date' => $current_date,
            'reset_time' => $current_time,
            'timezone' => 'America/Sao_Paulo (GMT-3)'
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if (isset($mysqli) && $mysqli->ping()) {
        $mysqli->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao executar reset forçado: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

<?php
/**
 * Admin Ranking Reset Endpoint
 * 
 * Reset manual do ranking pelo painel administrativo
 * CORRIGIDO: Agora cria cooldowns para os vencedores
 * 
 * Sistema de Cooldown:
 * - Top 1, 2, 3: 24 HORAS de cooldown
 * - Top 4 a 10: 2 HORAS de cooldown
 */

require_once __DIR__ . '/../../admin/cors.php';
require_once __DIR__ . '/../../database.php';

header('Content-Type: application/json');

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

try {
    $conn = getDbConnection();
    
    // Obter data e hora atual
    $current_date = date('Y-m-d');
    $current_datetime = date('Y-m-d H:i:s');
    
    // Iniciar transação
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
        
        // Valores dos prêmios por posição (padronizado)
        $rankingPrizes = [
            1 => 10.00,  // 1º lugar: R$ 10,00
            2 => 5.00,   // 2º lugar: R$ 5,00
            3 => 2.50,   // 3º lugar: R$ 2,50
            4 => 1.00,   // 4º lugar: R$ 1,00
            5 => 1.00,
            6 => 1.00,
            7 => 1.00,
            8 => 1.00,
            9 => 1.00,
            10 => 1.00
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
        
        // 1. Buscar o top 10 do ranking antes de resetar
        // IMPORTANTE: Apenas usuários com PIX cadastrado e SEM cooldown ativo participam do ranking
        $stmt = $conn->prepare("
            SELECT u.id, u.name, u.daily_points
            FROM users u
            LEFT JOIN ranking_cooldowns rc ON u.id = rc.user_id AND rc.cooldown_until > ?
            WHERE u.daily_points > 0 
              AND u.pix_key IS NOT NULL 
              AND u.pix_key != ''
              AND rc.id IS NULL
            ORDER BY u.daily_points DESC, u.created_at ASC 
            LIMIT 10
        ");
        $stmt->bind_param("s", $current_datetime);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $top_10_ids = [];
        $cooldowns_created = [];
        $position = 1;
        
        while ($row = $result->fetch_assoc()) {
            $user_id = $row['id'];
            $top_10_ids[] = $user_id;
            
            $prizeAmount = $rankingPrizes[$position] ?? 1.00;
            $userCooldownHours = $cooldownHours[$position] ?? 2;
            
            // ============================================
            // REGISTRAR COOLDOWN PARA O VENCEDOR
            // ============================================
            $cooldownUntil = date('Y-m-d H:i:s', strtotime("+{$userCooldownHours} hours"));
            
            $stmt_cooldown = $conn->prepare("
                INSERT INTO ranking_cooldowns 
                (user_id, position, prize_amount, cooldown_days, cooldown_until, reset_date, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt_cooldown->bind_param("iidiss", 
                $user_id, 
                $position, 
                $prizeAmount, 
                $userCooldownHours, 
                $cooldownUntil, 
                $current_date
            );
            $stmt_cooldown->execute();
            $stmt_cooldown->close();
            
            $cooldowns_created[] = [
                'user_id' => $user_id,
                'name' => $row['name'],
                'position' => $position,
                'prize_amount' => $prizeAmount,
                'cooldown_hours' => $userCooldownHours,
                'cooldown_until' => $cooldownUntil
            ];
            
            $position++;
        }
        $stmt->close();
        
        // NOVA LÓGICA v3: Buscar usuários em cooldown para NÃO resetar seus pontos
        $cooldownUserIds = [];
        $cooldownCheckResult = $conn->query("SELECT user_id FROM ranking_cooldowns WHERE cooldown_until > NOW()");
        if ($cooldownCheckResult) {
            while ($row = $cooldownCheckResult->fetch_assoc()) {
                $cooldownUserIds[] = (int)$row['user_id'];
            }
        }
        
        // 2. Resetar daily_points APENAS do top 10 (exceto quem está em cooldown)
        $top_10_ids_filtered = array_values(array_diff($top_10_ids, $cooldownUserIds));
        $users_reset = 0;
        if (!empty($top_10_ids_filtered)) {
            $placeholders = implode(',', array_fill(0, count($top_10_ids_filtered), '?'));
            $stmt = $conn->prepare("
                UPDATE users 
                SET daily_points = 0 
                WHERE id IN ($placeholders)
            ");
            
            $types = str_repeat('i', count($top_10_ids_filtered));
            $stmt->bind_param($types, ...$top_10_ids_filtered);
            $stmt->execute();
            $users_reset = $stmt->affected_rows;
            $stmt->close();
        }
        
        // 3. Deletar registros de spin de hoje
        // CORREÇÃO v2: Removido CONVERT_TZ - MySQL já está em Brasília (-03:00)
        $stmt = $conn->prepare("DELETE FROM spin_history WHERE DATE(created_at) = ?");
        $stmt->bind_param("s", $current_date);
        $stmt->execute();
        
        // 4. Atualizar last_reset_datetime (para liberar check-in)
        $stmt = $conn->prepare("
            UPDATE system_settings 
            SET setting_value = NOW() 
            WHERE setting_key = 'last_reset_datetime'
        ");
        $stmt->execute();
        
        // Se não existe, inserir
        if ($stmt->affected_rows === 0) {
            $stmt = $conn->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES ('last_reset_datetime', NOW())
            ");
            $stmt->execute();
        }
        
        // 5. Randomizar número de impressões necessárias (5 a 30)
        $random_impressions = rand(5, 30);
        
        // Verificar se a configuração já existe
        $check_stmt = $conn->prepare("
            SELECT id FROM roulette_settings 
            WHERE setting_key = 'monetag_required_impressions'
        ");
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Atualizar valor existente
            $stmt = $conn->prepare("
                UPDATE roulette_settings 
                SET setting_value = ?, updated_at = NOW()
                WHERE setting_key = 'monetag_required_impressions'
            ");
            $stmt->bind_param("s", $random_impressions);
            $stmt->execute();
        } else {
            // Inserir novo valor
            $stmt = $conn->prepare("
                INSERT INTO roulette_settings (setting_key, setting_value, description)
                VALUES ('monetag_required_impressions', ?, 'Número de impressões necessárias para desbloquear roleta')
            ");
            $stmt->bind_param("s", $random_impressions);
            $stmt->execute();
        }
        $check_stmt->close();
        
        // 6. Deletar eventos de monetag de todos os usuários (resetar progresso)
        $stmt = $conn->prepare("DELETE FROM monetag_events");
        $stmt->execute();
        
        // 7. Resetar níveis do Candy Crush (game_levels) - volta todos para level 1
        $candy_reset = 0;
        $stmt = $conn->prepare("UPDATE game_levels SET level = 1, highest_level = 1, last_level_score = 0, total_score = 0");
        if ($stmt->execute()) {
            $candy_reset = $stmt->affected_rows;
        }
        $stmt->close();
        
        // 8. Resetar progresso de level do Candy (candy_level_progress)
        $stmt = $conn->prepare("DELETE FROM candy_level_progress");
        $stmt->execute();
        $stmt->close();
        
        // 9. Resetar scores do Candy (candy_scores) se existir
        $stmt = $conn->prepare("DELETE FROM candy_scores");
        @$stmt->execute(); // @ para ignorar erro se tabela não existir
        @$stmt->close();
        
        // Commit da transação
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Sistema resetado com sucesso! (Top 10 do Ranking + Cooldowns + Spin + Check-in + MoniTag + Candy Levels)',
            'users_reset' => $users_reset,
            'top_10_ids' => $top_10_ids,
            'monetag_impressions' => $random_impressions,
            'candy_levels_reset' => $candy_reset,
            'cooldowns_created' => count($cooldowns_created),
            'cooldowns_details' => $cooldowns_created,
            'note' => 'Apenas o top 10 teve pontos zerados e recebeu cooldown. Demais usuários mantiveram seus pontos. Todos os levels do Candy foram resetados para 1.'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>

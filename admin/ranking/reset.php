<?php
require_once __DIR__ . '/../../admin/cors.php';
require_once __DIR__ . '/../../database.php';

header('Content-Type: application/json');

try {
    $conn = getDbConnection();
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // 1. Buscar o top 10 do ranking antes de resetar
        // IMPORTANTE: Apenas usuários com PIX cadastrado participam do ranking
        $stmt = $conn->prepare("
            SELECT id 
            FROM users 
            WHERE daily_points > 0 
              AND pix_key IS NOT NULL 
              AND pix_key != ''
            ORDER BY daily_points DESC, created_at ASC 
            LIMIT 10
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $top_10_ids = [];
        while ($row = $result->fetch_assoc()) {
            $top_10_ids[] = $row['id'];
        }
        $stmt->close();
        
        // 2. Resetar daily_points APENAS do top 10 (demais mantêm seus pontos)
        $users_reset = 0;
        if (!empty($top_10_ids)) {
            $placeholders = implode(',', array_fill(0, count($top_10_ids), '?'));
            $stmt = $conn->prepare("
                UPDATE users 
                SET daily_points = 0 
                WHERE id IN ($placeholders)
            ");
            
            $types = str_repeat('i', count($top_10_ids));
            $stmt->bind_param($types, ...$top_10_ids);
            $stmt->execute();
            $users_reset = $stmt->affected_rows;
            $stmt->close();
        }
        
        // 3. Deletar registros de spin de hoje
        $stmt = $conn->prepare("DELETE FROM spin_history WHERE DATE(created_at) = CURDATE()");
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
            'message' => 'Sistema resetado com sucesso! (Top 10 do Ranking + Spin + Check-in + MoniTag + Candy Levels)',
            'users_reset' => $users_reset,
            'top_10_ids' => $top_10_ids,
            'monetag_impressions' => $random_impressions,
            'candy_levels_reset' => $candy_reset,
            'note' => 'Apenas o top 10 teve pontos zerados. Demais usuários mantiveram seus pontos. Todos os levels do Candy foram resetados para 1.'
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

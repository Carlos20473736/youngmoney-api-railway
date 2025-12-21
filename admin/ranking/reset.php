<?php
require_once __DIR__ . '/../../admin/cors.php';
require_once __DIR__ . '/../../database.php';

header('Content-Type: application/json');

try {
    $conn = getDbConnection();
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // 1. Resetar daily_points (ranking)
        $stmt = $conn->prepare("UPDATE users SET daily_points = 0");
        $stmt->execute();
        
        // 2. Deletar registros de spin de hoje
        $stmt = $conn->prepare("DELETE FROM spin_history WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        
        // 3. Atualizar last_reset_datetime (para liberar check-in)
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
        
        // 4. Randomizar número de impressões necessárias (5 a 30)
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
        
        // 5. Deletar eventos de monetag de todos os usuários (resetar progresso)
        $stmt = $conn->prepare("DELETE FROM monetag_events");
        $stmt->execute();
        
        // Commit da transação
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Sistema resetado com sucesso! (Ranking + Spin + Check-in + MoniTag)',
            'monetag_impressions' => $random_impressions
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

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
        
        // Commit da transação
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Sistema resetado com sucesso! (Ranking + Spin + Check-in)'
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

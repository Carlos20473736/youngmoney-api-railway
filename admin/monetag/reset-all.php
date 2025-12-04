<?php
/**
 * Reset All Users Progress - MoniTag Missions
 * POST - Reseta contadores diários de TODOS os usuários
 */

require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../database.php';

try {
    $conn = getDbConnection();
    
    // Deletar TODOS os eventos de hoje
    $stmt = $conn->prepare("
        DELETE FROM monetag_events 
        WHERE DATE(created_at) = CURDATE()
    ");
    
    $stmt->execute();
    
    $deleted = $stmt->affected_rows;
    
    // Contar quantos usuários foram afetados
    $stmt2 = $conn->prepare("
        SELECT COUNT(DISTINCT user_id) as affected_users
        FROM (
            SELECT user_id FROM monetag_events WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY
        ) as yesterday_users
    ");
    
    $stmt2->execute();
    $result = $stmt2->get_result();
    $row = $result->fetch_assoc();
    $affectedUsers = $row['affected_users'] ?? 0;
    
    $stmt->close();
    $stmt2->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'events_deleted' => $deleted,
            'users_affected' => $affectedUsers,
            'message' => 'Progresso diário de todos os usuários resetado com sucesso'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

<?php
/**
 * Reset User Progress - MoniTag Missions
 * POST - Reseta contadores diários de um usuário específico
 */

require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? null;
    
    if (!$userId) {
        throw new Exception('user_id é obrigatório');
    }
    
    $conn = getDbConnection();
    
    // Deletar eventos de hoje do usuário
    $stmt = $conn->prepare("
        DELETE FROM monetag_events 
        WHERE user_id = ? 
        AND DATE(created_at) = CURDATE()
    ");
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    
    $deleted = $stmt->affected_rows;
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $userId,
            'events_deleted' => $deleted,
            'message' => 'Progresso diário resetado com sucesso'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

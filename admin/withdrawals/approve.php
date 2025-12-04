<?php
require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $withdrawalId = $input['withdrawal_id'] ?? null;
    
    if (!$withdrawalId) {
        throw new Exception('ID do saque é obrigatório');
    }
    
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("UPDATE withdrawals SET status = 'approved' WHERE id = ?");
    $stmt->bind_param('i', $withdrawalId);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

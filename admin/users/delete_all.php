<?php
require_once __DIR__ . '/../../database.php';
require_once __DIR__ . '/../cors.php';

header('Content-Type: application/json');

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $conn = getDbConnection();
    
    // Iniciar transação
    $conn->begin_transaction();
    
    // 1. Deletar check-ins diários
    $conn->query("DELETE FROM daily_checkin");
    
    // 2. Deletar histórico de spins
    $conn->query("DELETE FROM spin_history");
    
    // 3. Deletar histórico de pontos
    $conn->query("DELETE FROM points_history");
    
    // 4. Deletar notificações
    $conn->query("DELETE FROM notifications");
    
    // 5. Deletar saques
    $conn->query("DELETE FROM withdrawals");
    
    // 6. Deletar usuários
    $result = $conn->query("DELETE FROM users");
    $deletedCount = $conn->affected_rows;
    
    // Commit da transação
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Todos os usuários foram removidos com sucesso',
        'deleted_count' => $deletedCount
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao deletar usuários: ' . $e->getMessage()
    ]);
}
?>

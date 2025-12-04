<?php
require_once __DIR__ . '/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // Total de usuários
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $totalUsers = $stmt->get_result()->fetch_assoc()['total'];
    
    // Pontos distribuídos hoje
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(points), 0) as total 
        FROM points_history 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $pointsToday = $stmt->get_result()->fetch_assoc()['total'];
    
    // Notificações enviadas (total)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications");
    $stmt->execute();
    $notificationsSent = $stmt->get_result()->fetch_assoc()['total'];
    
    // Saques pendentes
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM withdrawals 
        WHERE status = 'pending'
    ");
    $stmt->execute();
    $pendingWithdrawals = $stmt->get_result()->fetch_assoc()['total'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'totalUsers' => (int)$totalUsers,
            'pointsToday' => (int)$pointsToday,
            'notificationsSent' => (int)$notificationsSent,
            'pendingWithdrawals' => (int)$pendingWithdrawals
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar estatísticas: ' . $e->getMessage()
    ]);
}
?>

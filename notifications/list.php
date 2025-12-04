<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/auth_helper.php';

try {
    $conn = getDbConnection();
    $user = getAuthenticatedUser($conn);
    if (!$user) { sendUnauthorizedError(); }
    
    $userId = $user['id'];
    
    // Buscar notificações do usuário
    $stmt = $conn->prepare("
        SELECT 
            id,
            title,
            message,
            type,
            is_read as `read`,
            UNIX_TIMESTAMP(created_at) as timestamp,
            created_at
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'type' => $row['type'] ?? 'info',
            'read' => (bool)$row['read'],
            'timestamp' => (int)$row['timestamp'],
            'created_at' => $row['created_at']
        ];
    }
    
    sendSuccess([
        'notifications' => $notifications,
        'total' => count($notifications),
        'unread' => array_reduce($notifications, function($carry, $item) {
            return $carry + ($item['read'] ? 0 : 1);
        }, 0)
    ]);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log("notifications/list.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

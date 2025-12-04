<?php
require_once __DIR__ . '/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = ($page - 1) * $limit;
    
    $conn = getDbConnection();
    
    // Contar total
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications");
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    
    // Buscar notificações
    $stmt = $conn->prepare("
        SELECT 
            n.id,
            n.user_id,
            u.name as user_name,
            n.title,
            n.message,
            n.created_at
        FROM notifications n
        LEFT JOIN users u ON n.user_id = u.id
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => (int)$row['id'],
            'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
            'user_name' => $row['user_name'],
            'title' => $row['title'],
            'message' => $row['message'],
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'notifications' => $notifications,
            'total' => (int)$total
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

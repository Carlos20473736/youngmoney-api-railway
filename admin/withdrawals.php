<?php
require_once __DIR__ . '/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    
    $conn = getDbConnection();
    
    $whereClause = '';
    if ($status) {
        $whereClause = " WHERE w.status = ?";
    }
    
    // Buscar saques
    $stmt = $conn->prepare("
        SELECT 
            w.id,
            w.user_id,
            u.name as user_name,
            w.amount,
            w.pix_key,
            w.status,
            w.created_at
        FROM withdrawals w
        LEFT JOIN users u ON w.user_id = u.id" . $whereClause . "
        ORDER BY w.created_at DESC
        LIMIT 100
    ");
    
    if ($status) {
        $stmt->bind_param('s', $status);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $withdrawals = [];
    while ($row = $result->fetch_assoc()) {
        $withdrawals[] = [
            'id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'user_name' => $row['user_name'],
            'amount' => (float)$row['amount'],
            'pix_key' => $row['pix_key'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'processed_at' => null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $withdrawals
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

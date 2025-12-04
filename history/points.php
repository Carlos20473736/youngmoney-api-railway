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
    
    $stmt = $conn->prepare("
        SELECT id, points, description, created_at,
               DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as formatted_date
        FROM points_history 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = [
            'id' => (int)$row['id'],
            'points' => (int)$row['points'],
            'description' => $row['description'],
            'created_at' => $row['created_at'],
            'formatted_date' => $row['formatted_date']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    sendSuccess(['history' => $transactions, 'total' => count($transactions)]);
    
} catch (Exception $e) {
    error_log("history/points.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

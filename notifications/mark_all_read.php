<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/auth_helper.php';

try {
    $conn = getDbConnection();
    $user = getAuthenticatedUser($conn);
    if (!$user) { sendUnauthorizedError(); }
    
    // Marcar TODAS as notificações do usuário como lidas
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    
    $affectedRows = $stmt->affected_rows;
    error_log("mark_all_read.php: User " . $user['id'] . " - Marked " . $affectedRows . " notifications as read");
    
    sendSuccess([
        'message' => 'Todas as notificações marcadas como lidas',
        'marked_count' => $affectedRows
    ]);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log("notifications/mark_all_read.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

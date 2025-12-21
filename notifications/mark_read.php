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
    
    // Obter dados do request
    $input = json_decode(file_get_contents('php://input'), true);
    $notificationId = $input['notification_id'] ?? null;
    
    if (!$notificationId) {
        sendError('ID da notificação é obrigatório', 400);
    }
    
    // Marcar notificação como lida no banco de dados
    $stmt = $conn->prepare("UPDATE notifications SET `read` = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notificationId, $user['id']);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        sendSuccess(['message' => 'Notificação marcada como lida']);
    } else {
        // Pode ser que já estava lida ou não existe
        sendSuccess(['message' => 'Notificação já estava lida ou não encontrada']);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log("notifications/mark_read.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

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
    
    // Log para debug
    error_log("mark_read.php: User ID = " . $user['id']);
    
    // Obter dados do request - verificar se veio pelo túnel seguro
    $rawInput = isset($GLOBALS['_SECURE_REQUEST_BODY']) ? $GLOBALS['_SECURE_REQUEST_BODY'] : file_get_contents('php://input');
    error_log("mark_read.php: Raw input = " . $rawInput);
    error_log("mark_read.php: Source = " . (isset($GLOBALS['_SECURE_REQUEST_BODY']) ? 'SECURE_TUNNEL' : 'php://input'));
    
    $input = json_decode($rawInput, true);
    error_log("mark_read.php: Parsed input = " . print_r($input, true));
    
    $notificationId = $input['notification_id'] ?? null;
    error_log("mark_read.php: Notification ID = " . ($notificationId ?? 'NULL'));
    
    if (!$notificationId) {
        sendError('ID da notificação é obrigatório', 400);
    }
    
    // Marcar notificação como lida no banco de dados
    // Usar is_read (nome correto da coluna)
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notificationId, $user['id']);
    $stmt->execute();
    
    error_log("mark_read.php: Affected rows = " . $stmt->affected_rows);
    error_log("mark_read.php: Query = UPDATE notifications SET read = 1 WHERE id = $notificationId AND user_id = " . $user['id']);
    
    if ($stmt->affected_rows > 0) {
        sendSuccess(['message' => 'Notificação marcada como lida', 'notification_id' => $notificationId]);
    } else {
        // Verificar se a notificação existe
        $checkStmt = $conn->prepare("SELECT id, user_id, is_read FROM notifications WHERE id = ?");
        $checkStmt->bind_param("i", $notificationId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $notif = $result->fetch_assoc();
        
        if ($notif) {
            error_log("mark_read.php: Notification found - user_id=" . $notif['user_id'] . ", is_read=" . $notif['is_read']);
            if ($notif['is_read'] == 1) {
                sendSuccess(['message' => 'Notificação já estava lida', 'notification_id' => $notificationId]);
            } else if ($notif['user_id'] != $user['id']) {
                sendError('Notificação não pertence a este usuário', 403);
            }
        } else {
            error_log("mark_read.php: Notification NOT found with id=$notificationId");
        }
        
        sendSuccess(['message' => 'Notificação não encontrada ou já estava lida', 'notification_id' => $notificationId]);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log("notifications/mark_read.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

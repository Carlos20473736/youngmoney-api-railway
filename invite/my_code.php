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
    
    // Contar convites aceitos
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE invited_by = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $totalInvites = (int)$row['total'];
    $stmt->close();
    
    $conn->close();
    
    // Determinar saudação baseada no horário (GMT-3)
    date_default_timezone_set('America/Sao_Paulo');
    $hour = (int)date('H');
    
    if ($hour >= 5 && $hour < 12) {
        $greeting = 'BOM DIA';
    } elseif ($hour >= 12 && $hour < 18) {
        $greeting = 'BOA TARDE';
    } else {
        $greeting = 'BOA NOITE';
    }
    
    // Gerar link de compartilhamento
    $inviteLink = "https://youngmoney.app/invite/" . $user['invite_code'];
    
    sendSuccess([
        'invite_code' => $user['invite_code'],
        'invite_link' => $inviteLink,
        'total_invites' => $totalInvites,
        'greeting' => $greeting,
        'user_name' => $user['name'],
        'user_picture' => $user['profile_picture'] ?: ''
    ]);
    
} catch (Exception $e) {
    error_log("invite/my_code.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

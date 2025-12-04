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
    
    // Calcular posição do usuário
    $stmt = $conn->prepare("
        SELECT COUNT(*) + 1 as position
        FROM users
        WHERE points > (SELECT points FROM users WHERE id = ?)
    ");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $position = (int)$row['position'];
    $stmt->close();
    
    // Total de usuários com pontos
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE points > 0");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total = (int)$row['total'];
    $stmt->close();
    
    $conn->close();
    
    sendSuccess([
        'position' => $position,
        'total_users' => $total,
        'points' => (int)$user['points']
    ]);
    
} catch (Exception $e) {
    error_log("ranking/user_position.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

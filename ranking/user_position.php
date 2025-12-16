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
    
    // Verificar se o usuário tem chave PIX
    $stmt = $conn->prepare("SELECT pix_key FROM users WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $pixData = $result->fetch_assoc();
    $stmt->close();
    
    $hasPixKey = !empty($pixData['pix_key']);
    
    if (!$hasPixKey) {
        // Usuário não tem chave PIX - não está no ranking
        $conn->close();
        sendSuccess([
            'position' => 0,
            'total_users' => 0,
            'points' => (int)$user['daily_points'],
            'in_ranking' => false,
            'message' => 'Cadastre sua chave PIX para participar do ranking!'
        ]);
        exit;
    }
    
    // Calcular posição do usuário (baseado em daily_points, apenas entre usuários com PIX)
    $stmt = $conn->prepare("
        SELECT COUNT(*) + 1 as position
        FROM users
        WHERE daily_points > (SELECT daily_points FROM users WHERE id = ?)
          AND pix_key IS NOT NULL 
          AND pix_key != ''
    ");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $position = (int)$row['position'];
    $stmt->close();
    
    // Total de usuários com pontos E chave PIX
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE daily_points > 0 AND pix_key IS NOT NULL AND pix_key != ''");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total = (int)$row['total'];
    $stmt->close();
    
    // Buscar daily_points do usuário
    $stmt = $conn->prepare("SELECT daily_points FROM users WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $dailyPoints = (int)$row['daily_points'];
    $stmt->close();
    
    $conn->close();
    
    sendSuccess([
        'position' => $position,
        'total_users' => $total,
        'points' => $dailyPoints,
        'in_ranking' => true
    ]);
    
} catch (Exception $e) {
    error_log("ranking/user_position.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

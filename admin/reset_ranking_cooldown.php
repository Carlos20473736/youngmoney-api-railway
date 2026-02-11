<?php
/**
 * Admin: Reset Ranking Cooldown
 * 
 * Endpoint: POST /admin/reset_ranking_cooldown.php
 * 
 * Body JSON:
 * {
 *   "email": "user@email.com",
 *   "token": "seu_token"
 * }
 */

header('Content-Type: application/json; charset=utf-8');

// Verificar token
$data = json_decode(file_get_contents('php://input'), true) ?? [];
$token = $data['token'] ?? $_GET['token'] ?? '';
$email = $data['email'] ?? $_GET['email'] ?? '';

$expectedToken = getenv('RESET_TOKEN') ?: 'ym_reset_roulette_scheduled_2024_secure';

if (empty($token) || $token !== $expectedToken) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Token inválido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($email)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Email é obrigatório'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // 1. Buscar usuário
    $stmt = $conn->prepare("SELECT id, email FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => "Usuário não encontrado: $email"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $userId = $user['id'];
    
    // 2. Verificar cooldown atual
    $stmt = $conn->prepare("
        SELECT user_id, cooldown_start, cooldown_end, reason 
        FROM ranking_cooldown 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $cooldown = $result->fetch_assoc();
    $stmt->close();
    
    if (!$cooldown) {
        echo json_encode([
            'success' => true,
            'message' => 'Usuário não tem cooldown ativo',
            'data' => [
                'user_id' => $userId,
                'email' => $email,
                'cooldown_active' => false
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 3. Deletar cooldown
    $stmt = $conn->prepare("DELETE FROM ranking_cooldown WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
    
    $conn->close();
    
    // Sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Countdown resetado com sucesso!',
        'data' => [
            'user_id' => $userId,
            'email' => $email,
            'previous_cooldown' => [
                'start' => $cooldown['cooldown_start'],
                'end' => $cooldown['cooldown_end'],
                'reason' => $cooldown['reason']
            ],
            'status' => 'cooldown_removed',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

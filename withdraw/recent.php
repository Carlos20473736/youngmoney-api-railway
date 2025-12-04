<?php
/**
 * Recent Withdrawals Endpoint
 * GET - Retorna saques recentes de todos os usuários (para marquee)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/auth_helper.php';

try {
    $conn = getDbConnection();
    
    // Autenticar usuário
    $user = getAuthenticatedUser($conn);
    
    if (!$user) {
        sendUnauthorizedError();
    }
    
    // Buscar saques recentes aprovados/completados
    $stmt = $conn->prepare("
        SELECT w.id, w.amount, w.created_at, u.name, u.profile_picture
        FROM withdrawals w
        INNER JOIN users u ON w.user_id = u.id
        WHERE w.status IN ('approved', 'completed')
        ORDER BY w.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $withdrawals = [];
    
    while ($row = $result->fetch_assoc()) {
        $withdrawals[] = [
            'id' => (int)$row['id'],
            'amount' => (float)$row['amount'],
            'user_name' => $row['name'],
            'user_picture' => $row['profile_picture'],
            'created_at' => $row['created_at']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    sendSuccess([
        'withdrawals' => $withdrawals,
        'total' => count($withdrawals)
    ]);
    
} catch (Exception $e) {
    error_log("withdraw/recent.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

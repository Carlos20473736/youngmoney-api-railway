<?php
/**
 * Withdraw History Endpoint
 * GET - Retorna histórico de saques do usuário autenticado
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
    
    // Buscar histórico de saques do usuário
    $stmt = $conn->prepare("
        SELECT id, amount, pix_type, pix_key, status, created_at, updated_at
        FROM withdrawals 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $withdrawals = [];
    
    while ($row = $result->fetch_assoc()) {
        $withdrawals[] = [
            'id' => (int)$row['id'],
            'amount' => (float)$row['amount'],
            'pix_type' => $row['pix_type'],
            'pix_key' => $row['pix_key'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    sendSuccess([
        'withdrawals' => $withdrawals,
        'total' => count($withdrawals)
    ]);
    
} catch (Exception $e) {
    error_log("withdraw/history.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

<?php
/**
 * Withdraw History Endpoint
 * GET - Retorna histórico de saques do usuário autenticado
 * Inclui saques via PIX, Binance e FaucetPay
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
    
    // Buscar histórico de saques do usuário (incluindo crypto)
    $stmt = $conn->prepare("
        SELECT id, amount, pix_type, pix_key, payment_method, 
               crypto_address, crypto_amount, crypto_currency, 
               points_debited, exchange_rate, status, created_at, updated_at
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
        $method = $row['payment_method'] ?? 'pix';
        
        $item = [
            'id' => (int)$row['id'],
            'amount' => (float)$row['amount'],
            'amount_formatted' => 'R$ ' . number_format((float)$row['amount'], 2, ',', '.'),
            'payment_method' => $method,
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
        
        if ($method === 'pix') {
            $item['pix_type'] = $row['pix_type'];
            $item['pix_key'] = $row['pix_key'];
        } else {
            $item['crypto_address'] = $row['crypto_address'];
            $item['crypto_amount'] = $row['crypto_amount'] ? (float)$row['crypto_amount'] : null;
            $item['crypto_amount_formatted'] = $row['crypto_amount'] 
                ? number_format((float)$row['crypto_amount'], 8, '.', '') . ' LTC'
                : null;
            $item['crypto_currency'] = $row['crypto_currency'] ?? 'LTC';
            $item['exchange_rate'] = $row['exchange_rate'] ? (float)$row['exchange_rate'] : null;
        }
        
        if ($row['points_debited']) {
            $item['points_debited'] = (int)$row['points_debited'];
            $item['points_debited_formatted'] = number_format((int)$row['points_debited'], 0, '', '.') . ' pontos';
        }
        
        $withdrawals[] = $item;
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

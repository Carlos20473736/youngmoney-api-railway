<?php
require_once __DIR__ . '/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    
    $conn = getDbConnection();
    
    $whereClause = '';
    if ($status) {
        $whereClause = " WHERE w.status = ?";
    }
    
    // Buscar saques (incluindo dados crypto)
    $stmt = $conn->prepare("
        SELECT 
            w.id,
            w.user_id,
            u.name as user_name,
            w.amount,
            w.pix_key,
            w.pix_key_type,
            w.payment_method,
            w.crypto_address,
            w.crypto_amount,
            w.crypto_currency,
            w.points_debited,
            w.exchange_rate,
            w.status,
            w.created_at
        FROM withdrawals w
        LEFT JOIN users u ON w.user_id = u.id" . $whereClause . "
        ORDER BY w.created_at DESC
        LIMIT 100
    ");
    
    if ($status) {
        $stmt->bind_param('s', $status);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $withdrawals = [];
    while ($row = $result->fetch_assoc()) {
        $item = [
            'id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'user_name' => $row['user_name'],
            'amount' => (float)$row['amount'],
            'amount_formatted' => 'R$ ' . number_format((float)$row['amount'], 2, ',', '.'),
            'payment_method' => $row['payment_method'] ?? 'pix',
            'pix_key' => $row['pix_key'],
            'pix_key_type' => $row['pix_key_type'] ?? null,
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'processed_at' => null,
        ];
        
        // Adicionar dados crypto se aplicável
        $method = $row['payment_method'] ?? 'pix';
        if ($method !== 'pix') {
            $item['crypto_address'] = $row['crypto_address'];
            $item['crypto_amount'] = $row['crypto_amount'] ? (float)$row['crypto_amount'] : null;
            $item['crypto_amount_formatted'] = $row['crypto_amount'] 
                ? number_format((float)$row['crypto_amount'], 8, '.', '') . ' ' . ($row['crypto_currency'] ?? 'LTC')
                : null;
            $item['crypto_currency'] = $row['crypto_currency'];
            $item['exchange_rate'] = $row['exchange_rate'] ? (float)$row['exchange_rate'] : null;
        }
        
        if ($row['points_debited']) {
            $item['points_debited'] = (int)$row['points_debited'];
            $item['points_debited_formatted'] = number_format((int)$row['points_debited'], 0, '', '.');
        }
        
        $withdrawals[] = $item;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $withdrawals
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../database.php';

try {
    if (!isset($_GET['user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'user_id não fornecido']);
        exit;
    }
    
    $userId = (int)$_GET['user_id'];
    
    $conn = getDbConnection();
    
    // Buscar histórico de saques
    $stmt = $conn->prepare("
        SELECT 
            id,
            amount,
            pix_key,
            status,
            created_at as date,
            updated_at
        FROM withdrawal_requests 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $withdrawals = [];
    while ($row = $result->fetch_assoc()) {
        $withdrawals[] = [
            'id' => (int)$row['id'],
            'amount' => (float)$row['amount'],
            'pix_key' => $row['pix_key'],
            'status' => $row['status'],
            'date' => $row['date'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'withdrawals' => $withdrawals
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>

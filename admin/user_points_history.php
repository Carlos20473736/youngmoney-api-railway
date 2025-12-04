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
    
    // Buscar histórico de pontos
    $stmt = $conn->prepare("
        SELECT 
            'add_points' as type,
            points as amount,
            description,
            created_at as date
        FROM points_history 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'type' => $row['type'],
            'amount' => (int)$row['amount'],
            'description' => $row['description'] ?? 'Pontos adicionados',
            'date' => $row['date']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'history' => $history
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>

<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // Contar usuários antes de deletar
    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    $row = $result->fetch_assoc();
    $totalUsers = $row['total'];
    
    // Deletar todos os usuários
    $conn->query("DELETE FROM users");
    
    echo json_encode([
        'success' => true,
        'data' => [
            'message' => 'Todos os usuários foram deletados',
            'deleted_count' => $totalUsers
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>

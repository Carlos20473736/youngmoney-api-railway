<?php
/**
 * MoniTag Stats Endpoint (v3 - APENAS IMPRESSÕES)
 * Retorna estatísticas de impressões de um usuário
 * 
 * Lógica de cliques removida completamente
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../database.php';

try {
    // Obter email do parâmetro GET
    $email = isset($_GET['email']) ? trim($_GET['email']) : null;
    
    if (!$email) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Email é obrigatório'
        ]);
        exit;
    }
    
    $conn = getDbConnection();
    
    // Buscar user_id pelo email
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Usuário não encontrado - retornar zeros
        echo json_encode([
            'success' => true,
            'data' => [
                'impressions' => 0,
                'clicks' => 0,
                'email' => $email
            ]
        ]);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $userId = $user['id'];
    
    // Buscar estatísticas de impressões
    $stmtImpressions = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM monetag_events 
        WHERE user_id = ? AND event_type = 'impression'
    ");
    $stmtImpressions->bind_param("i", $userId);
    $stmtImpressions->execute();
    $impressions = $stmtImpressions->get_result()->fetch_assoc()['total'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'impressions' => (int)$impressions,
            'clicks' => 0,
            'email' => $email,
            'user_id' => $userId
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar estatísticas: ' . $e->getMessage()
    ]);
}
?>

<?php
/**
 * Ranking List Endpoint
 * GET - Retorna lista do ranking de usuários por pontos
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
    
    // Obter limite (padrão: 100)
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 100;
    
    // Buscar ranking (pontos diários)
    $stmt = $conn->prepare("
        SELECT id, name, profile_picture, daily_points as points
        FROM users 
        WHERE daily_points > 0
        ORDER BY daily_points DESC, created_at ASC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ranking = [];
    $position = 1;
    
    while ($row = $result->fetch_assoc()) {
        $ranking[] = [
            'position' => $position++,
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'profile_picture' => $row['profile_picture'] ?: '',
            'points' => (int)$row['points']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    // Determinar saudação baseada no horário (GMT-3)
    date_default_timezone_set('America/Sao_Paulo');
    $hour = (int)date('H');
    
    if ($hour >= 5 && $hour < 12) {
        $greeting = 'BOM DIA';
    } elseif ($hour >= 12 && $hour < 18) {
        $greeting = 'BOA TARDE';
    } else {
        $greeting = 'BOA NOITE';
    }
    
    sendSuccess([
        'greeting' => $greeting,
        'ranking' => $ranking,
        'total' => count($ranking)
    ]);
    
} catch (Exception $e) {
    error_log("ranking/list.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

<?php
/**
 * Online Users API
 * 
 * Retorna a contagem de usuários online (ativos nos últimos X minutos)
 * baseado no campo updated_at da tabela users
 */

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
    $conn = getDbConnection();
    
    // Tempo em minutos para considerar um usuário como "online"
    // Padrão: 5 minutos
    $minutesThreshold = isset($_GET['minutes']) ? intval($_GET['minutes']) : 5;
    
    // Limitar entre 1 e 60 minutos
    $minutesThreshold = max(1, min(60, $minutesThreshold));
    
    // Contar usuários que tiveram atividade nos últimos X minutos
    // Usa updated_at pois é atualizado em cada ação do usuário
    $stmt = $conn->prepare("
        SELECT COUNT(*) as online_count 
        FROM users 
        WHERE updated_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $stmt->bind_param("i", $minutesThreshold);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $onlineCount = $row['online_count'] ?? 0;
    $stmt->close();
    
    // Também retornar usuários ativos hoje (para referência)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as active_today 
        FROM users 
        WHERE DATE(updated_at) = CURDATE()
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $activeToday = $row['active_today'] ?? 0;
    $stmt->close();
    
    // Opcionalmente, listar os usuários online (limitado a 100)
    $includeList = isset($_GET['include_list']) && $_GET['include_list'] === 'true';
    $onlineUsers = [];
    
    if ($includeList) {
        $stmt = $conn->prepare("
            SELECT id, name, email, username, photo_url, profile_picture, updated_at
            FROM users 
            WHERE updated_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ORDER BY updated_at DESC
            LIMIT 100
        ");
        $stmt->bind_param("i", $minutesThreshold);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($user = $result->fetch_assoc()) {
            $onlineUsers[] = [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'username' => $user['username'],
                'photo_url' => $user['photo_url'] ?? $user['profile_picture'],
                'last_activity' => $user['updated_at']
            ];
        }
        $stmt->close();
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'online_count' => (int)$onlineCount,
            'active_today' => (int)$activeToday,
            'threshold_minutes' => $minutesThreshold,
            'online_users' => $onlineUsers,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Online users error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar usuários online: ' . $e->getMessage()
    ]);
}
?>

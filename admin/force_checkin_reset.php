<?php
/**
 * Admin: Forçar Reset do Check-in de Usuário Específico
 * 
 * Endpoint: GET/POST /admin/force_checkin_reset.php
 * 
 * Parâmetros:
 * - user_id: ID do usuário (opcional)
 * - email: Email do usuário (opcional)
 * - name: Nome do usuário (opcional)
 * - all: Se "true", reseta todos os check-ins de hoje
 * 
 * Exemplo: /admin/force_checkin_reset.php?email=usuario@email.com
 * Exemplo: /admin/force_checkin_reset.php?all=true
 */

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

try {
    $conn = getDbConnection();
    
    // Configurar timezone no MySQL
    $conn->query("SET time_zone = '-03:00'");
    
    $todayDate = date('Y-m-d');
    $affectedUsers = [];
    
    // Verificar se é para resetar todos
    $resetAll = isset($_GET['all']) && $_GET['all'] === 'true';
    
    if ($resetAll) {
        // Buscar todos os check-ins de hoje
        $stmt = $conn->prepare("
            SELECT dc.id, dc.user_id, dc.checkin_date, u.name, u.email
            FROM daily_checkin dc
            JOIN users u ON dc.user_id = u.id
            WHERE dc.checkin_date = ?
        ");
        $stmt->bind_param("s", $todayDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $affectedUsers[] = [
                'id' => $row['user_id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'checkin_date' => $row['checkin_date']
            ];
        }
        $stmt->close();
        
        // Deletar todos os check-ins de hoje
        $stmt = $conn->prepare("DELETE FROM daily_checkin WHERE checkin_date = ?");
        $stmt->bind_param("s", $todayDate);
        $stmt->execute();
        $deletedCount = $stmt->affected_rows;
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => "Todos os check-ins de hoje ($todayDate) foram resetados",
            'deleted_count' => $deletedCount,
            'affected_users' => $affectedUsers,
            'server_date' => $todayDate,
            'server_time' => date('H:i:s')
        ]);
        exit;
    }
    
    // Buscar usuário específico
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $email = isset($_GET['email']) ? trim($_GET['email']) : null;
    $name = isset($_GET['name']) ? trim($_GET['name']) : null;
    
    $whereConditions = [];
    $params = [];
    $types = '';
    
    if ($userId) {
        $whereConditions[] = "id = ?";
        $params[] = $userId;
        $types .= 'i';
    }
    
    if ($email) {
        $whereConditions[] = "LOWER(email) = LOWER(?)";
        $params[] = $email;
        $types .= 's';
    }
    
    if ($name) {
        $whereConditions[] = "LOWER(name) LIKE LOWER(?)";
        $params[] = "%$name%";
        $types .= 's';
    }
    
    if (empty($whereConditions)) {
        echo json_encode([
            'success' => false,
            'message' => 'Forneça user_id, email, name ou all=true',
            'usage' => [
                'by_id' => '/admin/force_checkin_reset.php?user_id=123',
                'by_email' => '/admin/force_checkin_reset.php?email=usuario@email.com',
                'by_name' => '/admin/force_checkin_reset.php?name=João',
                'all_today' => '/admin/force_checkin_reset.php?all=true'
            ]
        ]);
        exit;
    }
    
    // Buscar usuários
    $whereClause = implode(' OR ', $whereConditions);
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE $whereClause");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
    
    if (empty($users)) {
        echo json_encode([
            'success' => false,
            'message' => 'Nenhum usuário encontrado com os critérios fornecidos'
        ]);
        exit;
    }
    
    // Deletar check-ins de hoje para os usuários encontrados
    $userIds = array_column($users, 'id');
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $deleteTypes = str_repeat('i', count($userIds)) . 's';
    
    $stmt = $conn->prepare("
        DELETE FROM daily_checkin 
        WHERE user_id IN ($placeholders) 
        AND checkin_date = ?
    ");
    
    $deleteParams = array_merge($userIds, [$todayDate]);
    $stmt->bind_param($deleteTypes, ...$deleteParams);
    $stmt->execute();
    $deletedCount = $stmt->affected_rows;
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Check-in resetado com sucesso',
        'deleted_count' => $deletedCount,
        'affected_users' => $users,
        'server_date' => $todayDate,
        'server_time' => date('H:i:s'),
        'note' => 'Os usuários agora podem fazer check-in novamente hoje'
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Force checkin reset error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao resetar check-in: ' . $e->getMessage()
    ]);
}
?>

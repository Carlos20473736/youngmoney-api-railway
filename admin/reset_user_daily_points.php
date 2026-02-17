<?php
/**
 * Admin: Zerar Pontos Diários de Usuários Específicos
 * 
 * Endpoint: POST /admin/reset_user_daily_points.php
 * 
 * Request Body:
 * {
 *   "users": ["nome1", "nome2"] ou
 *   "emails": ["email1@example.com", "email2@example.com"] ou
 *   "ids": [1, 2, 3]
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Pontos zerados com sucesso",
 *   "affected_users": [...]
 * }
 */

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $conn = getDbConnection();
    
    // Ler body da requisição
    $rawBody = file_get_contents('php://input');
    $input = json_decode($rawBody, true);
    
    $affectedUsers = [];
    
    // Buscar por nomes
    if (isset($input['users']) && is_array($input['users'])) {
        foreach ($input['users'] as $userName) {
            $stmt = $conn->prepare("
                SELECT id, name, email, daily_points 
                FROM users 
                WHERE LOWER(name) LIKE LOWER(?)
            ");
            $searchName = "%" . trim($userName) . "%";
            $stmt->bind_param("s", $searchName);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $affectedUsers[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'old_daily_points' => $row['daily_points']
                ];
            }
            $stmt->close();
        }
    }
    
    // Buscar por emails
    if (isset($input['emails']) && is_array($input['emails'])) {
        foreach ($input['emails'] as $email) {
            $stmt = $conn->prepare("
                SELECT id, name, email, daily_points 
                FROM users 
                WHERE LOWER(email) = LOWER(?)
            ");
            $stmt->bind_param("s", trim($email));
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $affectedUsers[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'old_daily_points' => $row['daily_points']
                ];
            }
            $stmt->close();
        }
    }
    
    // Buscar por IDs
    if (isset($input['ids']) && is_array($input['ids'])) {
        foreach ($input['ids'] as $userId) {
            $stmt = $conn->prepare("
                SELECT id, name, email, daily_points 
                FROM users 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $affectedUsers[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'old_daily_points' => $row['daily_points']
                ];
            }
            $stmt->close();
        }
    }
    
    // Remover duplicados
    $uniqueUsers = [];
    $seenIds = [];
    foreach ($affectedUsers as $user) {
        if (!in_array($user['id'], $seenIds)) {
            $seenIds[] = $user['id'];
            $uniqueUsers[] = $user;
        }
    }
    $affectedUsers = $uniqueUsers;
    
    if (empty($affectedUsers)) {
        echo json_encode([
            'success' => false,
            'message' => 'Nenhum usuário encontrado com os critérios fornecidos'
        ]);
        exit;
    }
    
    // Zerar pontos diários dos usuários encontrados
    $userIds = array_column($affectedUsers, 'id');
    
    // NOVA LÓGICA v3: NÃO resetar pontos de quem está em cooldown
    $cooldownUserIds = [];
    $cooldownCheckResult = $conn->query("SELECT user_id FROM ranking_cooldowns WHERE cooldown_until > NOW()");
    if ($cooldownCheckResult) {
        while ($row = $cooldownCheckResult->fetch_assoc()) {
            $cooldownUserIds[] = (int)$row['user_id'];
        }
    }
    $userIds = array_values(array_diff($userIds, $cooldownUserIds));
    
    $rowsAffected = 0;
    if (!empty($userIds)) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $types = str_repeat('i', count($userIds));
        
        $stmt = $conn->prepare("
            UPDATE users 
            SET daily_points = 0 
            WHERE id IN ($placeholders)
        ");
        $stmt->bind_param($types, ...$userIds);
        $stmt->execute();
        $rowsAffected = $stmt->affected_rows;
        $stmt->close();
    }
    
    // Log da operação
    error_log("[ADMIN] Pontos diários zerados para " . count($affectedUsers) . " usuários: " . json_encode(array_column($affectedUsers, 'name')));
    
    echo json_encode([
        'success' => true,
        'message' => 'Pontos diários zerados com sucesso',
        'rows_affected' => $rowsAffected,
        'affected_users' => $affectedUsers
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Reset user daily points error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao zerar pontos: ' . $e->getMessage()
    ]);
}
?>

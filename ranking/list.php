<?php
/**
 * Ranking List Endpoint
 * GET - Retorna lista do ranking de usuários por pontos
 * IMPORTANTE: Só mostra usuários que têm chave PIX cadastrada
 * IMPORTANTE: Exclui usuários em período de cooldown
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
    
    // Configurar timezone
    date_default_timezone_set('America/Sao_Paulo');
    $now = date('Y-m-d H:i:s');
    
    // Obter limite (padrão: 100)
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 100;
    
    // Criar tabela de cooldowns se não existir
    $conn->query("
        CREATE TABLE IF NOT EXISTS ranking_cooldowns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            position INT NOT NULL,
            prize_amount DECIMAL(10,2) NOT NULL,
            cooldown_days INT NOT NULL,
            cooldown_until DATETIME NOT NULL,
            reset_date DATE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_cooldown (user_id, cooldown_until),
            INDEX idx_cooldown_until (cooldown_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Buscar ranking (pontos diários) - APENAS usuários com chave PIX cadastrada
    // EXCLUI usuários em período de cooldown
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.profile_picture, u.daily_points as points
        FROM users u
        LEFT JOIN ranking_cooldowns rc ON u.id = rc.user_id AND rc.cooldown_until > ?
        WHERE u.daily_points > 0 
          AND u.pix_key IS NOT NULL 
          AND u.pix_key != ''
          AND rc.id IS NULL
        ORDER BY u.daily_points DESC, u.created_at ASC
        LIMIT ?
    ");
    $stmt->bind_param("si", $now, $limit);
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

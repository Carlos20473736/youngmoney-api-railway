<?php
/**
 * User Position Endpoint
 * GET - Retorna a posição do usuário no ranking
 * Inclui informação de cooldown se o usuário estiver bloqueado
 * 
 * NOVA LÓGICA v3:
 * - Usuários em cooldown PONTUAM normalmente
 * - Usuários em cooldown NÃO aparecem no ranking (posição = 0)
 * - Usuários em cooldown NÃO têm reset de pontos
 * - Mostra os pontos acumulados mesmo em cooldown
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/auth_helper.php';

try {
    $conn = getDbConnection();
    $user = getAuthenticatedUser($conn);
    if (!$user) { sendUnauthorizedError(); }
    
    $userId = $user['id'];
    
    // Configurar timezone
    date_default_timezone_set('America/Sao_Paulo');
    $now = date('Y-m-d H:i:s');
    
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
    
    // ============================================
    // VERIFICAR SE USUÁRIO ESTÁ EM COOLDOWN
    // ============================================
    $stmt = $conn->prepare("
        SELECT 
            id,
            position as last_position,
            prize_amount,
            cooldown_days,
            cooldown_until,
            reset_date
        FROM ranking_cooldowns 
        WHERE user_id = ? 
          AND cooldown_until > ?
        ORDER BY cooldown_until DESC
        LIMIT 1
    ");
    $stmt->bind_param("is", $userId, $now);
    $stmt->execute();
    $cooldownResult = $stmt->get_result();
    $cooldownData = $cooldownResult->fetch_assoc();
    $stmt->close();
    
    $inCooldown = false;
    $cooldownInfo = null;
    
    if ($cooldownData) {
        $inCooldown = true;
        
        $cooldownUntil = new DateTime($cooldownData['cooldown_until']);
        $nowDt = new DateTime($now);
        $diff = $nowDt->diff($cooldownUntil);
        
        $daysRemaining = $diff->days;
        $hoursRemaining = $diff->h;
        $minutesRemaining = $diff->i;
        
        // Formatar mensagem amigável
        if ($daysRemaining > 0) {
            $timeMessage = "{$daysRemaining} dia" . ($daysRemaining > 1 ? "s" : "");
            if ($hoursRemaining > 0) {
                $timeMessage .= " e {$hoursRemaining}h";
            }
        } else {
            $timeMessage = "{$hoursRemaining}h {$minutesRemaining}min";
        }
        
        $cooldownInfo = [
            'in_cooldown' => true,
            'last_position' => (int)$cooldownData['last_position'],
            'prize_amount' => (float)$cooldownData['prize_amount'],
            'cooldown_days' => (int)$cooldownData['cooldown_days'],
            'cooldown_until' => $cooldownData['cooldown_until'],
            'days_remaining' => $daysRemaining,
            'hours_remaining' => $hoursRemaining,
            'minutes_remaining' => $minutesRemaining,
            'time_message' => $timeMessage,
            'message' => "Parabéns! Você ficou em {$cooldownData['last_position']}º lugar e ganhou R$ " . number_format($cooldownData['prize_amount'], 2, ',', '.') . "! Você poderá participar do ranking novamente em {$timeMessage}."
        ];
    }
    
    // Verificar se o usuário tem chave PIX e buscar pontos
    $stmt = $conn->prepare("SELECT pix_key, daily_points FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $pixData = $result->fetch_assoc();
    $stmt->close();
    
    $hasPixKey = !empty($pixData['pix_key']);
    $dailyPoints = (int)($pixData['daily_points'] ?? 0);
    
    // Se usuário está em cooldown, retornar info de cooldown
    // NOTA v3: Mostra os pontos acumulados (não são resetados em cooldown)
    if ($inCooldown) {
        $conn->close();
        sendSuccess([
            'position' => 0,
            'total_users' => 0,
            'points' => $dailyPoints,
            'in_ranking' => false,
            'in_cooldown' => true,
            'cooldown' => $cooldownInfo
        ]);
        exit;
    }
    
    if (!$hasPixKey) {
        // Usuário não tem chave PIX - não está no ranking
        $conn->close();
        sendSuccess([
            'position' => 0,
            'total_users' => 0,
            'points' => $dailyPoints,
            'in_ranking' => false,
            'in_cooldown' => false,
            'cooldown' => null,
            'message' => 'Cadastre sua chave PIX para participar do ranking!'
        ]);
        exit;
    }
    
    // Calcular posição do usuário (baseado em daily_points, apenas entre usuários com PIX e sem cooldown)
    $stmt = $conn->prepare("
        SELECT COUNT(*) + 1 as position
        FROM users u
        LEFT JOIN ranking_cooldowns rc ON u.id = rc.user_id AND rc.cooldown_until > ?
        WHERE u.daily_points > (SELECT daily_points FROM users WHERE id = ?)
          AND u.pix_key IS NOT NULL 
          AND u.pix_key != ''
          AND rc.id IS NULL
    ");
    $stmt->bind_param("si", $now, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $position = (int)$row['position'];
    $stmt->close();
    
    // Total de usuários com pontos E chave PIX (sem cooldown)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM users u
        LEFT JOIN ranking_cooldowns rc ON u.id = rc.user_id AND rc.cooldown_until > ?
        WHERE u.daily_points > 0 
          AND u.pix_key IS NOT NULL 
          AND u.pix_key != ''
          AND rc.id IS NULL
    ");
    $stmt->bind_param("s", $now);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total = (int)$row['total'];
    $stmt->close();
    
    $conn->close();
    
    sendSuccess([
        'position' => $position,
        'total_users' => $total,
        'points' => $dailyPoints,
        'in_ranking' => true,
        'in_cooldown' => false,
        'cooldown' => null
    ]);
    
} catch (Exception $e) {
    error_log("ranking/user_position.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

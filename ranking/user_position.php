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
 * 
 * REGRA v4:
 * - Mínimo de 2.000.000 pontos para entrar no ranking diário
 * - Retorna progresso (pontos atuais / mínimo) e pontos faltantes
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/auth_helper.php';

// Pontos mínimos para entrar no ranking diário
define('MIN_RANKING_POINTS', 2000000);

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
    
    // Calcular progresso para o mínimo de pontos
    $minPoints = MIN_RANKING_POINTS;
    $pointsRemaining = max(0, $minPoints - $dailyPoints);
    $progressPercent = min(100, round(($dailyPoints / $minPoints) * 100, 1));
    $meetsMinimum = $dailyPoints >= $minPoints;
    
    // Se usuário está em cooldown, retornar info de cooldown
    if ($inCooldown) {
        $conn->close();
        sendSuccess([
            'position' => 0,
            'total_users' => 0,
            'points' => $dailyPoints,
            'min_points' => $minPoints,
            'meets_minimum' => $meetsMinimum,
            'points_remaining' => $pointsRemaining,
            'progress_percent' => $progressPercent,
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
            'min_points' => $minPoints,
            'meets_minimum' => $meetsMinimum,
            'points_remaining' => $pointsRemaining,
            'progress_percent' => $progressPercent,
            'in_ranking' => false,
            'in_cooldown' => false,
            'cooldown' => null,
            'message' => 'Cadastre sua chave PIX para participar do ranking!'
        ]);
        exit;
    }
    
    // Se não atingiu o mínimo de pontos
    if (!$meetsMinimum) {
        $conn->close();
        sendSuccess([
            'position' => 0,
            'total_users' => 0,
            'points' => $dailyPoints,
            'min_points' => $minPoints,
            'meets_minimum' => false,
            'points_remaining' => $pointsRemaining,
            'progress_percent' => $progressPercent,
            'in_ranking' => false,
            'in_cooldown' => false,
            'cooldown' => null,
            'message' => "Faltam " . number_format($pointsRemaining, 0, ',', '.') . " pontos para entrar no ranking!"
        ]);
        exit;
    }
    
    // Calcular posição do usuário (baseado em daily_points)
    // APENAS entre usuários com PIX, sem cooldown, e com >= 2M pontos
    $stmt = $conn->prepare("
        SELECT COUNT(*) + 1 as position
        FROM users u
        LEFT JOIN ranking_cooldowns rc ON u.id = rc.user_id AND rc.cooldown_until > ?
        WHERE u.daily_points > (SELECT daily_points FROM users WHERE id = ?)
          AND u.daily_points >= ?
          AND u.pix_key IS NOT NULL 
          AND u.pix_key != ''
          AND rc.id IS NULL
    ");
    $stmt->bind_param("sii", $now, $userId, $minPoints);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $position = (int)$row['position'];
    $stmt->close();
    
    // Total de usuários elegíveis (com PIX, sem cooldown, >= 2M pontos)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM users u
        LEFT JOIN ranking_cooldowns rc ON u.id = rc.user_id AND rc.cooldown_until > ?
        WHERE u.daily_points >= ?
          AND u.pix_key IS NOT NULL 
          AND u.pix_key != ''
          AND rc.id IS NULL
    ");
    $stmt->bind_param("si", $now, $minPoints);
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
        'min_points' => $minPoints,
        'meets_minimum' => true,
        'points_remaining' => 0,
        'progress_percent' => 100.0,
        'in_ranking' => true,
        'in_cooldown' => false,
        'cooldown' => null
    ]);
    
} catch (Exception $e) {
    error_log("ranking/user_position.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

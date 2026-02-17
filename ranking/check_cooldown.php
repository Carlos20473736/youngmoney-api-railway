<?php
/**
 * Check Cooldown Endpoint
 * GET - Verifica se o usuário está em período de cooldown do ranking
 * 
 * Retorna:
 * - in_cooldown: boolean
 * - cooldown_until: datetime (se em cooldown)
 * - last_position: posição que conquistou
 * - last_prize: valor do prêmio recebido
 * - days_remaining: dias restantes de cooldown
 * - hours_remaining: horas restantes
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
    
    $userId = $user['id'];
    
    // Configurar timezone
    date_default_timezone_set('America/Sao_Paulo');
    $now = date('Y-m-d H:i:s');
    
    // Verificar se usuário está em cooldown ativo
    $stmt = $conn->prepare("
        SELECT 
            id,
            position,
            prize_amount,
            cooldown_days,
            cooldown_until,
            reset_date,
            created_at
        FROM ranking_cooldowns 
        WHERE user_id = ? 
          AND cooldown_until > ?
        ORDER BY cooldown_until DESC
        LIMIT 1
    ");
    $stmt->bind_param("is", $userId, $now);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Usuário está em cooldown
        $cooldownUntil = new DateTime($row['cooldown_until']);
        $nowDt = new DateTime($now);
        $diff = $nowDt->diff($cooldownUntil);
        
        // Calcular tempo restante
        $totalHours = ($diff->days * 24) + $diff->h;
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
        
        sendSuccess([
            'in_cooldown' => true,
            'cooldown_until' => $row['cooldown_until'],
            'last_position' => (int)$row['position'],
            'last_prize' => (float)$row['prize_amount'],
            'cooldown_days' => (int)$row['cooldown_days'],
            'days_remaining' => $daysRemaining,
            'hours_remaining' => $hoursRemaining,
            'minutes_remaining' => $minutesRemaining,
            'total_hours_remaining' => $totalHours,
            'time_message' => $timeMessage,
            'can_earn_points' => true,
            'message' => "Parabéns! Você ficou em {$row['position']}º lugar e ganhou R$ " . number_format($row['prize_amount'], 2, ',', '.') . "! Você pode continuar pontuando normalmente, mas não aparecerá no ranking até o countdown terminar em {$timeMessage}."
        ]);
    } else {
        // Usuário não está em cooldown
        
        // Buscar último cooldown (histórico)
        $stmt = $conn->prepare("
            SELECT 
                position,
                prize_amount,
                cooldown_until,
                reset_date
            FROM ranking_cooldowns 
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $lastCooldown = $result->fetch_assoc();
        
        sendSuccess([
            'in_cooldown' => false,
            'cooldown_until' => null,
            'last_position' => $lastCooldown ? (int)$lastCooldown['position'] : null,
            'last_prize' => $lastCooldown ? (float)$lastCooldown['prize_amount'] : null,
            'last_cooldown_ended' => $lastCooldown ? $lastCooldown['cooldown_until'] : null,
            'message' => 'Você pode participar do ranking!'
        ]);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log("ranking/check_cooldown.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

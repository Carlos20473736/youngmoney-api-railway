<?php
/**
 * Add Points Endpoint
 * POST - Adiciona pontos ao usuário
 * 
 * NOVA LÓGICA v3:
 * - Usuários em cooldown PODEM pontuar normalmente (daily_points acumula)
 * - Usuários em cooldown NÃO aparecem no ranking (filtrado na listagem)
 * - Usuários em cooldown NÃO recebem pagamentos
 * - Usuários em cooldown NÃO têm reset de pontos
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/auth_helper.php';

try {
    $conn = getDbConnection();
    $user = getAuthenticatedUser($conn);
    if (!$user) { sendUnauthorizedError(); }
    
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    $points = isset($data['points']) ? (int)$data['points'] : 0;
    $description = $data['description'] ?? 'Pontos adicionados';
    
    if ($points <= 0) {
        sendError('Pontos inválidos', 400);
    }
    
    // Verificar se usuário tem chave PIX cadastrada
    $stmt = $conn->prepare("SELECT pix_key, pix_key_type FROM users WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $pixData = $result->fetch_assoc();
    $stmt->close();
    
    $hasPixKey = !empty($pixData['pix_key']);
    
    // Verificar se usuário está em cooldown (apenas para informar, NÃO para bloquear)
    date_default_timezone_set('America/Sao_Paulo');
    $now = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("
        SELECT id, position, cooldown_until 
        FROM ranking_cooldowns 
        WHERE user_id = ? AND cooldown_until > ?
        ORDER BY cooldown_until DESC 
        LIMIT 1
    ");
    $stmt->bind_param("is", $user['id'], $now);
    $stmt->execute();
    $cooldownResult = $stmt->get_result();
    $cooldownData = $cooldownResult->fetch_assoc();
    $stmt->close();
    
    $inCooldown = !empty($cooldownData);
    
    // NOVA LÓGICA v3: Sempre adicionar pontos (totais E daily_points)
    // Mesmo em cooldown, o usuário pontua normalmente
    // A diferença é que em cooldown ele NÃO aparece no ranking
    if ($hasPixKey) {
        // Usuário tem chave PIX - adiciona pontos totais E daily_points
        // Mesmo em cooldown, os pontos são adicionados normalmente
        $stmt = $conn->prepare("UPDATE users SET points = points + ?, daily_points = daily_points + ? WHERE id = ?");
        $stmt->bind_param("iii", $points, $points, $user['id']);
    } else {
        // Usuário NÃO tem chave PIX - adiciona apenas pontos totais
        $stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $stmt->bind_param("ii", $points, $user['id']);
    }
    $stmt->execute();
    $stmt->close();
    
    // Registrar transação
    $stmt = $conn->prepare("
        INSERT INTO point_transactions (user_id, points, type, description)
        VALUES (?, ?, 'credit', ?)
    ");
    $stmt->bind_param("iis", $user['id'], $points, $description);
    $stmt->execute();
    $stmt->close();
    
    // Buscar novo saldo e daily_points
    $stmt = $conn->prepare("SELECT points, daily_points FROM users WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $newBalance = (int)$row['points'];
    $dailyPoints = (int)$row['daily_points'];
    $stmt->close();
    
    $conn->close();
    
    $response = [
        'points_added' => $points,
        'new_balance' => $newBalance,
        'daily_points' => $dailyPoints,
        'total_points' => $newBalance,
        'message' => 'Pontos adicionados com sucesso!'
    ];
    
    // Adicionar aviso se não tem chave PIX
    if (!$hasPixKey) {
        $response['ranking_warning'] = 'Cadastre sua chave PIX para participar do ranking!';
        $response['ranking_eligible'] = false;
    } else {
        $response['ranking_eligible'] = true;
    }
    
    // Informar se está em cooldown (pontos foram adicionados, mas não aparece no ranking)
    if ($inCooldown) {
        $response['in_cooldown'] = true;
        $response['cooldown_message'] = 'Você está em countdown. Seus pontos foram adicionados normalmente, mas você não aparecerá no ranking até o countdown terminar.';
        $response['cooldown_until'] = $cooldownData['cooldown_until'];
    } else {
        $response['in_cooldown'] = false;
    }
    
    sendSuccess($response);
    
} catch (Exception $e) {
    error_log("ranking/add_points.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

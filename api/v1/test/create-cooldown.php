<?php
/**
 * Endpoint de teste para criar cooldown fictício
 * 
 * APENAS PARA TESTES - Remover em produção
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../../database.php';

$conn = getDbConnection();

// Parâmetros
$userId = $_GET['user_id'] ?? $_POST['user_id'] ?? null;
$position = $_GET['position'] ?? $_POST['position'] ?? 1;
$hours = $_GET['hours'] ?? $_POST['hours'] ?? 24;

if (!$userId) {
    echo json_encode(['error' => 'user_id required']);
    exit;
}

try {
    // Criar cooldown
    $cooldownUntil = date('Y-m-d H:i:s', time() + ($hours * 3600));
    
    $stmt = $conn->prepare("
        INSERT INTO ranking_cooldowns (user_id, position, cooldown_days, cooldown_until, created_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            cooldown_until = VALUES(cooldown_until),
            position = VALUES(position)
    ");
    
    $stmt->bind_param("iis", $userId, $position, $cooldownUntil);
    $stmt->execute();
    $stmt->close();
    
    // Criar pagamento pendente
    $stmt = $conn->prepare("
        INSERT INTO pending_payments (user_id, amount, status, created_at)
        VALUES (?, ?, 'pending', NOW())
    ");
    
    $amount = 1000.00;
    $stmt->bind_param("id", $userId, $amount);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $userId,
            'cooldown' => [
                'position' => $position,
                'hours' => $hours,
                'until' => $cooldownUntil
            ],
            'pending_payment' => [
                'amount' => $amount,
                'status' => 'pending'
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

?>

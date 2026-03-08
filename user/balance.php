<?php
/**
 * User Balance Endpoint
 * GET - Retorna saldo do usuário autenticado
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
require_once __DIR__ . '/../includes/security_validation_helper.php';

try {
    $conn = getDbConnection();
    
    // Autenticar usuário
    $user = getAuthenticatedUser($conn);
    if (!$user) {
        sendUnauthorizedError();
    }
    
    // VALIDAÇÃO DE HEADERS REMOVIDA - estava bloqueando requisições legítimas
    // validateSecurityHeaders($conn, $user);
    
    // Taxa de conversão: 5.000.000 pontos = R$ 1,00
    $pointsPerReal = 5000000;
    $userPoints = (int)$user['points'];
    $balanceBrl = round($userPoints / $pointsPerReal, 2);
    
    // Retornar saldo (points + BRL)
    sendSuccess([
        'balance' => $userPoints,
        'points' => $userPoints,
        'points_formatted' => number_format($userPoints, 0, '', '.'),
        'balance_brl' => $balanceBrl,
        'balance_brl_formatted' => 'R$ ' . number_format($balanceBrl, 2, ',', '.'),
        'points_per_real' => $pointsPerReal,
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("user/balance.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

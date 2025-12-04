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
    
    // Retornar saldo (points)
    sendSuccess([
        'balance' => (int)$user['points'],
        'points' => (int)$user['points']
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("user/balance.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

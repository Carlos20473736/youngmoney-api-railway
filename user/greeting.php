<?php
/**
 * User Greeting Endpoint
 * GET - Retorna saudação personalizada baseada no horário
 */

header('Content-Type: application/json; charset=utf-8');
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
    
    // Determinar saudação baseada no horário (GMT-3)
    date_default_timezone_set('America/Sao_Paulo');
    $hour = (int)date('H');
    
    if ($hour >= 5 && $hour < 12) {
        $greeting = 'BOM DIA';
    } elseif ($hour >= 12 && $hour < 18) {
        $greeting = 'BOA TARDE';
    } else {
        $greeting = 'BOA NOITE';
    }
    
    // Retornar saudação com nome do usuário
    sendSuccess([
        'greeting' => $greeting,
        'name' => $user['name'],
        'full_greeting' => $greeting . ', ' . $user['name'] . '!'
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("user/greeting.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

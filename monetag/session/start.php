<?php
/**
 * Endpoint: /monetag/session/start.php
 * Descrição: Registra o início de uma sessão de anúncio
 * Método: POST
 * 
 * Parâmetros esperados:
 * - userId: ID do usuário
 * - userEmail: Email do usuário
 * - zoneId: ID da zona de anúncios
 * - clickId: ID único da sessão de clique
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Lidar com preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(json_encode(['success' => true]));
}

// Apenas aceitar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'error' => 'Método não permitido']));
}

// Obter dados do corpo da requisição
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validar dados obrigatórios
if (!isset($data['userId']) || !isset($data['userEmail']) || !isset($data['zoneId'])) {
    http_response_code(400);
    exit(json_encode([
        'success' => false,
        'error' => 'Parâmetros obrigatórios faltando: userId, userEmail, zoneId'
    ]));
}

$userId = (int)$data['userId'];
$userEmail = filter_var($data['userEmail'], FILTER_SANITIZE_EMAIL);
$zoneId = (int)$data['zoneId'];
$clickId = $data['clickId'] ?? null;

// Log da sessão iniciada
error_log("[SESSION START] Sessão iniciada - User: $userId, Email: $userEmail, Zone: $zoneId, Click: $clickId");

// Retornar sucesso
http_response_code(200);
exit(json_encode([
    'success' => true,
    'message' => 'Sessão registrada com sucesso',
    'data' => [
        'sessionToken' => bin2hex(random_bytes(16)),
        'userId' => $userId,
        'zoneId' => $zoneId,
        'clickId' => $clickId,
        'timestamp' => date('Y-m-d H:i:s')
    ]
]));
?>

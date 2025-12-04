<?php
/**
 * Response Helper
 * Funções helper para padronizar respostas da API
 */

require_once __DIR__ . '/xreq_manager.php';

/**
 * Envia resposta JSON com novo x-req
 * 
 * @param mysqli $conn Conexão com banco
 * @param array $user Usuário autenticado
 * @param array $data Dados da resposta
 * @param int $statusCode Código HTTP (padrão: 200)
 */
function sendJsonResponse($conn, $user, $data, $statusCode = 200) {
    // Extrair User-Agent para geração do novo x-req
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'okhttp/4.12.0';
    
    // Gerar novo x-req para próxima requisição
    $newXReq = generateNewXReq($conn, $user, $userAgent);
    
    // Adicionar novo x-req no header da resposta
    header("X-New-Req: $newXReq");
    header("Content-Type: application/json");
    http_response_code($statusCode);
    
    // Adicionar novo x-req nos dados da resposta também (fallback)
    if (!isset($data['x_req'])) {
        $data['x_req'] = $newXReq;
    }
    
    echo json_encode($data);
    exit;
}

/**
 * Envia resposta de erro JSON com novo x-req
 * 
 * @param mysqli $conn Conexão com banco
 * @param array $user Usuário autenticado
 * @param string $message Mensagem de erro
 * @param int $statusCode Código HTTP (padrão: 400)
 * @param string $code Código do erro (opcional)
 */
function sendErrorResponse($conn, $user, $message, $statusCode = 400, $code = null) {
    $data = [
        'status' => 'error',
        'message' => $message
    ];
    
    if ($code !== null) {
        $data['code'] = $code;
    }
    
    sendJsonResponse($conn, $user, $data, $statusCode);
}

/**
 * Envia resposta de sucesso JSON com novo x-req
 * 
 * @param mysqli $conn Conexão com banco
 * @param array $user Usuário autenticado
 * @param mixed $data Dados de sucesso
 * @param string $message Mensagem de sucesso (opcional)
 */
function sendSuccessResponse($conn, $user, $data = null, $message = null) {
    $response = [
        'status' => 'success'
    ];
    
    if ($message !== null) {
        $response['message'] = $message;
    }
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    sendJsonResponse($conn, $user, $response, 200);
}
?>

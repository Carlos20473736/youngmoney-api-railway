<?php
/**
 * Auth Helper - Funções auxiliares para autenticação
 */

/**
 * Obtém o usuário autenticado a partir do token Bearer
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @return array|null Dados do usuário ou null se não autenticado
 */
function getAuthenticatedUser($conn) {
    // Obter o header Authorization
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    
    if (!$authHeader) {
        error_log("auth_helper: No Authorization header");
        return null;
    }
    
    // Extrair o token Bearer
    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        error_log("auth_helper: Invalid Authorization format");
        return null;
    }
    
    $token = $matches[1];
    error_log("auth_helper: Token received: " . substr($token, 0, 20) . "...");
    
    // Buscar usuário pelo token
    $stmt = $conn->prepare("
        SELECT id, google_id, email, name, profile_picture, points, 
               invite_code, created_at, updated_at
        FROM users 
        WHERE token = ?
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("auth_helper: Token not found in database");
        $stmt->close();
        return null;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    error_log("auth_helper: User authenticated: " . $user['id'] . " - " . $user['email']);
    return $user;
}

/**
 * Envia erro de não autenticado
 */
function sendUnauthorizedError() {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Não autenticado. Token inválido ou expirado.'
    ]);
    exit;
}

/**
 * Envia resposta de sucesso
 */
function sendSuccess($data) {
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);
    exit;
}

/**
 * Envia resposta de erro
 */
function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
    exit;
}
?>

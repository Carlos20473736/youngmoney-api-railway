<?php
/**
 * User PIX Data Endpoint
 * GET - Retorna dados PIX do usuário autenticado
 * PUT - Atualiza/Salva dados PIX do usuário
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Retornar dados PIX do usuário
        $stmt = $conn->prepare("SELECT pix_key_type, pix_key FROM users WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $pixData = $result->fetch_assoc();
        $stmt->close();
        
        sendSuccess([
            'pix_key_type' => $pixData['pix_key_type'] ?: '',
            'pix_key' => $pixData['pix_key'] ?: '',
            'has_pix' => !empty($pixData['pix_key'])
        ]);
        
    } elseif ($method === 'PUT' || $method === 'POST') {
        // Atualizar/Salvar dados PIX do usuário
        // Verificar se veio via secure.php (túnel criptografado)
        if (isset($GLOBALS['_SECURE_REQUEST_BODY']) && !empty($GLOBALS['_SECURE_REQUEST_BODY'])) {
            $rawInput = $GLOBALS['_SECURE_REQUEST_BODY'];
        } else {
            $rawInput = file_get_contents('php://input');
        }
        $data = json_decode($rawInput, true);
        
        if (!$data) {
            sendError('Dados inválidos', 400);
        }
        
        $pixKeyType = $data['pix_key_type'] ?? null;
        $pixKey = $data['pix_key'] ?? null;
        
        if (empty($pixKeyType) || empty($pixKey)) {
            sendError('Tipo de chave e chave PIX são obrigatórios', 400);
        }
        
        // Validar tipo de chave
        $validTypes = ['CPF', 'CNPJ', 'Email', 'Telefone', 'Chave Aleatória'];
        if (!in_array($pixKeyType, $validTypes)) {
            sendError('Tipo de chave inválido', 400);
        }
        
        // Atualizar dados PIX
        $stmt = $conn->prepare("UPDATE users SET pix_key_type = ?, pix_key = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $pixKeyType, $pixKey, $user['id']);
        $stmt->execute();
        $stmt->close();
        
        sendSuccess([
            'message' => 'Dados PIX salvos com sucesso',
            'pix_key_type' => $pixKeyType,
            'pix_key' => $pixKey
        ]);
        
    } else {
        sendError('Método não permitido', 405);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("user/pix.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

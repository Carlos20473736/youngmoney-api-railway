<?php
/**
 * Admin: Forçar Logout de Todos os Usuários
 * 
 * Endpoint: POST /admin/force_logout_all.php
 * 
 * Invalida todos os tokens de autenticação, forçando todos os usuários
 * a fazer login novamente.
 * 
 * Response:
 * {
 *   "success": true,
 *   "affected_users": 150,
 *   "message": "Todos os usuários foram deslogados"
 * }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido. Use POST.']);
    exit;
}

// Carregar configuração do banco
require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // Invalidar todos os tokens
    $stmt = $conn->prepare("UPDATE users SET token = NULL WHERE token IS NOT NULL");
    $stmt->execute();
    $affectedUsers = $stmt->affected_rows;
    $stmt->close();
    
    // Também limpar session_salt para forçar nova autenticação
    $stmt2 = $conn->prepare("UPDATE users SET session_salt = NULL");
    $stmt2->execute();
    $stmt2->close();
    
    error_log("[FORCE_LOGOUT_ALL] $affectedUsers usuários foram deslogados");
    
    echo json_encode([
        'success' => true,
        'affected_users' => $affectedUsers,
        'message' => "Todos os $affectedUsers usuários foram deslogados com sucesso"
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Force logout all error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao deslogar usuários: ' . $e->getMessage()]);
}

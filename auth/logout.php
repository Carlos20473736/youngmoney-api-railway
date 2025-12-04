<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/auth_helper.php');

try {
    $conn = getDbConnection();
    $user = getAuthenticatedUser($conn);
    
    if ($user) {
        // Limpar token do usuário
        $stmt = $conn->prepare("UPDATE users SET token = NULL WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->close();
    
    sendSuccess(['message' => 'Logout realizado com sucesso']);
    
} catch (Exception $e) {
    error_log("auth/logout.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

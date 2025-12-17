<?php
/**
 * Debug - Verificar se token existe no banco
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../database.php';

try {
    $conn = getDbConnection();
    
    // Obter token do header ou query string
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    
    $token = null;
    
    // Tentar do header
    if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
    }
    
    // Tentar da query string
    if (!$token && isset($_GET['token'])) {
        $token = $_GET['token'];
    }
    
    if (!$token) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Token não fornecido. Use header Authorization: Bearer {token} ou ?token={token}'
        ]);
        exit;
    }
    
    // Verificar no banco
    $stmt = $conn->prepare("SELECT id, email, name, token, created_at, updated_at FROM users WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo json_encode([
            'status' => 'success',
            'message' => 'Token VÁLIDO - encontrado no banco',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'token_prefix' => substr($user['token'], 0, 20) . '...',
                'updated_at' => $user['updated_at']
            ]
        ]);
    } else {
        // Verificar se existe algum usuário com token parecido
        $tokenPrefix = substr($token, 0, 10);
        $stmt2 = $conn->prepare("SELECT id, email, token FROM users WHERE token LIKE ? LIMIT 5");
        $likePattern = $tokenPrefix . '%';
        $stmt2->bind_param("s", $likePattern);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        $similar = [];
        while ($row = $result2->fetch_assoc()) {
            $similar[] = [
                'id' => $row['id'],
                'email' => $row['email'],
                'token_prefix' => substr($row['token'], 0, 20) . '...'
            ];
        }
        
        echo json_encode([
            'status' => 'error',
            'message' => 'Token NÃO encontrado no banco',
            'token_received' => substr($token, 0, 20) . '...',
            'token_length' => strlen($token),
            'similar_tokens' => $similar
        ]);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>

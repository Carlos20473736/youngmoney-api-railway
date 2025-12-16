<?php
/**
 * Recent Withdrawals Endpoint
 * GET - Retorna saques recentes de todos os usuários (para marquee)
 * 
 * Retorna:
 * - Email parcialmente oculto (ex: us***@example.com)
 * - Valor do saque
 * - Data/hora do saque
 * - Foto do usuário (se disponível)
 * - URL do comprovante PIX (se disponível)
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

/**
 * Mascara o email para privacidade
 * Ex: carlos@gmail.com -> ca***@gmail.com
 * Ex: teste@hotmail.com -> te***@hotmail.com
 */
function maskEmail($email) {
    if (empty($email) || strpos($email, '@') === false) {
        return 'us***@example.com';
    }
    
    $parts = explode('@', $email);
    $localPart = $parts[0];
    $domain = $parts[1];
    
    // Mostrar apenas os primeiros 2 caracteres do local part
    $visibleChars = min(2, strlen($localPart));
    $maskedLocal = substr($localPart, 0, $visibleChars) . '***';
    
    return $maskedLocal . '@' . $domain;
}

try {
    $conn = getDbConnection();
    
    // Autenticar usuário
    $user = getAuthenticatedUser($conn);
    
    if (!$user) {
        sendUnauthorizedError();
    }
    
    // Buscar saques recentes aprovados/completados
    // Inclui receipt_url se existir na tabela
    $stmt = $conn->prepare("
        SELECT w.id, w.amount, w.created_at, w.updated_at, w.receipt_url,
               u.email, u.profile_picture, u.photo_url
        FROM withdrawals w
        INNER JOIN users u ON w.user_id = u.id
        WHERE w.status IN ('approved', 'completed')
        ORDER BY w.updated_at DESC, w.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $withdrawals = [];
    
    while ($row = $result->fetch_assoc()) {
        // Usar updated_at se disponível, senão created_at
        $paymentDate = !empty($row['updated_at']) ? $row['updated_at'] : $row['created_at'];
        
        // Usar photo_url ou profile_picture
        $photoUrl = !empty($row['photo_url']) ? $row['photo_url'] : $row['profile_picture'];
        
        $withdrawals[] = [
            'id' => (int)$row['id'],
            'amount' => (float)$row['amount'],
            'email' => maskEmail($row['email']),
            'photo_url' => $photoUrl,
            'receipt_url' => $row['receipt_url'] ?? null,
            'created_at' => $paymentDate
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    sendSuccess([
        'withdrawals' => $withdrawals,
        'total' => count($withdrawals)
    ]);
    
} catch (Exception $e) {
    error_log("withdraw/recent.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

<?php
// Endpoint para APENAS validar código de convite (não adiciona pontos)


// Usado pelo app para validação em tempo real

header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../../database.php';
require_once __DIR__ . '/../../includes/HeadersValidator.php';

$conn = getDbConnection();

// Validar headers de segurança
$validator = validateRequestHeaders($conn, true);
if (!$validator) exit; // Já enviou resposta de erro


// POST /api/v1/invite/validate.php - Apenas validar se código existe
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['invite_code']) || empty($input['invite_code'])) {
        http_response_code(400);
        echo json_encode(['valid' => false, 'error' => 'invite_code é obrigatório']);
        exit;
    }
    
    $inviteCode = trim($input['invite_code']);
    
    // Buscar quem é o dono do código
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE invite_code = ?");
    $stmt->bind_param("s", $inviteCode);
    $stmt->execute();
    $result = $stmt->get_result();
    $inviter = $result->fetch_assoc();
    $stmt->close();
    
    if (!$inviter) {
        echo json_encode([
            'valid' => false,
            'error' => 'Código de convite inválido'
        ]);
        exit;
    }
    
    // Código válido
    echo json_encode([
        'valid' => true,
        'inviter_name' => $inviter['name'],
        'inviter_id' => $inviter['id']
    ]);
    
} else {
    http_response_code(405);
    echo json_encode(['valid' => false, 'error' => 'Método não permitido']);
}

$conn->close();
?>

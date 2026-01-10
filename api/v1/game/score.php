<?php
/**
 * Endpoint para pontuação do Candy Crush
 * 
 * NOVA LÓGICA:
 * - Qualquer pontuação é válida e adicionada aos pontos do usuário
 * - Os pontos são enviados quando o usuário passa de level
 * - Não há mais verificação de "pontuação maior que a anterior"
 * 
 * POST /api/v1/game/score.php
 * Body: { "score": 200 }
 * 
 * Resposta sucesso:
 * { "status": "success", "data": { "added": true, "score": 200, "daily_points": 350, "total_points": 1000 } }
 */

// Tratamento de erros
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => "PHP Error: $errstr in $errfile:$errline"]);
    exit;
});

try {
    // Includes necessários
    require_once __DIR__ . '/../../../db_config.php';
    require_once __DIR__ . '/../../../includes/auth_helper.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Include error: ' . $e->getMessage()]);
    exit;
}

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-ID');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Obter conexão
$conn = getMySQLiConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Autenticar usuário
$user = getAuthenticatedUser($conn);
if (!$user) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized - Invalid or missing token']);
    exit;
}

$userId = $user['id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Obter dados do body
// Ler body da variável global (quando passa pelo secure.php) ou do php://input
$rawBody = isset($GLOBALS['_SECURE_REQUEST_BODY']) ? $GLOBALS['_SECURE_REQUEST_BODY'] : file_get_contents('php://input');
$input = json_decode($rawBody, true);

if (!isset($input['score']) || !is_numeric($input['score'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Score is required and must be a number']);
    exit;
}

$newScore = intval($input['score']);

// Criar tabela de histórico de scores do Candy se não existir
$createTableSQL = "CREATE TABLE IF NOT EXISTS candy_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    score INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_score (score)
)";
$conn->query($createTableSQL);

try {
    // Registrar o score no histórico
    $stmt = $conn->prepare("INSERT INTO candy_scores (user_id, score) VALUES (?, ?)");
    $stmt->bind_param("ii", $userId, $newScore);
    $stmt->execute();
    $stmt->close();
    
    // NOVA LÓGICA: Qualquer pontuação é válida e adicionada diretamente
    // Não há mais verificação de recorde anterior
    $pointsToAdd = $newScore;
    
    // Adicionar pontos ao ranking do usuário (daily_points para o ranking diário)
    $stmt = $conn->prepare("UPDATE users SET daily_points = daily_points + ?, points = points + ? WHERE id = ?");
    $stmt->bind_param("iii", $pointsToAdd, $pointsToAdd, $userId);
    $stmt->execute();
    $stmt->close();
    
    // Registrar no histórico de pontos
    $description = "Candy Crush - Level completado: " . $newScore . " pontos";
    $stmt = $conn->prepare("INSERT INTO points_history (user_id, points, description, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $userId, $pointsToAdd, $description);
    $stmt->execute();
    $stmt->close();
    
    // Buscar pontos atualizados do usuário
    $stmt = $conn->prepare("SELECT points, daily_points FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userPoints = 0;
    $dailyPoints = 0;
    if ($row = $result->fetch_assoc()) {
        $userPoints = intval($row['points']);
        $dailyPoints = intval($row['daily_points']);
    }
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'added' => true,
            'score' => $newScore,
            'points_added' => $pointsToAdd,
            'daily_points' => $dailyPoints,
            'total_points' => $userPoints,
            'message' => 'Level completado! Pontuação adicionada.'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error processing score: ' . $e->getMessage()
    ]);
}

$conn->close();
?>

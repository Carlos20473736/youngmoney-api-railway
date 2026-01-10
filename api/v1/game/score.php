<?php
/**
 * Endpoint para pontuação do Candy Crush
 * 
 * A pontuação só é adicionada ao ranking se for MAIOR que a pontuação anterior do jogador.
 * 
 * POST /api/v1/game/score.php
 * Body: { "score": 200 }
 * 
 * Resposta sucesso (pontuação adicionada):
 * { "status": "success", "data": { "added": true, "score": 200, "previous_best": 150, "daily_points": 350, "total_points": 1000 } }
 * 
 * Resposta sucesso (pontuação não adicionada - menor que anterior):
 * { "status": "success", "data": { "added": false, "score": 180, "previous_best": 200, "message": "Pontuação menor que a anterior" } }
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

// Criar tabela de melhor score do usuário se não existir
$createBestScoreSQL = "CREATE TABLE IF NOT EXISTS candy_best_scores (
    user_id INT PRIMARY KEY,
    best_score INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($createBestScoreSQL);

try {
    // Buscar melhor score anterior do usuário
    $stmt = $conn->prepare("SELECT best_score FROM candy_best_scores WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $previousBest = 0;
    
    if ($row = $result->fetch_assoc()) {
        $previousBest = intval($row['best_score']);
    }
    $stmt->close();
    
    // Registrar o score no histórico (sempre)
    $stmt = $conn->prepare("INSERT INTO candy_scores (user_id, score) VALUES (?, ?)");
    $stmt->bind_param("ii", $userId, $newScore);
    $stmt->execute();
    $stmt->close();
    
    // Verificar se o novo score é maior que o anterior
    if ($newScore > $previousBest) {
        // Atualizar ou inserir o melhor score
        $stmt = $conn->prepare("INSERT INTO candy_best_scores (user_id, best_score) VALUES (?, ?) 
                                ON DUPLICATE KEY UPDATE best_score = ?");
        $stmt->bind_param("iii", $userId, $newScore, $newScore);
        $stmt->execute();
        $stmt->close();
        
        // Calcular pontos a adicionar (diferença entre novo e anterior)
        $pointsToAdd = $newScore - $previousBest;
        
        // Adicionar pontos ao ranking do usuário (daily_points para o ranking diário)
        $stmt = $conn->prepare("UPDATE users SET daily_points = daily_points + ?, points = points + ? WHERE id = ?");
        $stmt->bind_param("iii", $pointsToAdd, $pointsToAdd, $userId);
        $stmt->execute();
        $stmt->close();
        
        // Registrar no histórico de pontos
        $description = "Candy Crush - Novo recorde: " . $newScore . " (anterior: " . $previousBest . ")";
        $stmt = $conn->prepare("INSERT INTO points_history (user_id, points, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $userId, $pointsToAdd, $description);
        $stmt->execute();
        $stmt->close();
        
        // Buscar pontos atualizados do usuário
        $stmt = $conn->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $userPoints = 0;
        if ($row = $result->fetch_assoc()) {
            $userPoints = intval($row['points']);
        }
        $stmt->close();
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'added' => true,
                'score' => $newScore,
                'previous_best' => $previousBest,
                'points_added' => $pointsToAdd,
                'total_points' => $userPoints,
                'message' => 'Novo recorde! Pontuação adicionada ao ranking.'
            ]
        ]);
        
    } else {
        // Score menor ou igual ao anterior - não adiciona pontos
        echo json_encode([
            'status' => 'success',
            'data' => [
                'added' => false,
                'score' => $newScore,
                'previous_best' => $previousBest,
                'points_added' => 0,
                'message' => 'Pontuação não adicionada. Você precisa fazer mais de ' . $previousBest . ' pontos.'
            ]
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error processing score: ' . $e->getMessage()
    ]);
}

$conn->close();
?>

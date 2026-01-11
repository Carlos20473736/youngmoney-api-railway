<?php
/**
 * Endpoint para pontuação do Candy Crush
 * 
 * LÓGICA CORRIGIDA:
 * - Este endpoint APENAS registra o score da partida
 * - NÃO adiciona pontos ao ranking (isso é feito no level.php quando passa de level)
 * - Evita duplicação de pontos
 * 
 * POST /api/v1/game/score.php
 * Body: { "score": 200, "level": 1 }
 */

// Tratamento de erros
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("[SCORE.PHP] PHP Error: $errstr in $errfile:$errline");
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

error_log("[SCORE.PHP] User ID: $userId, Method: $method");

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Obter dados do body
$rawBody = isset($GLOBALS['_SECURE_REQUEST_BODY']) ? $GLOBALS['_SECURE_REQUEST_BODY'] : file_get_contents('php://input');
$input = json_decode($rawBody, true);

error_log("[SCORE.PHP] Raw body: $rawBody");
error_log("[SCORE.PHP] Input: " . json_encode($input));

if (!isset($input['score']) || !is_numeric($input['score'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Score is required and must be a number', 'debug_raw_body' => $rawBody]);
    exit;
}

$newScore = intval($input['score']);
$currentLevel = isset($input['level']) ? intval($input['level']) : 1;

// Validar score - não pode ser negativo ou absurdamente alto
if ($newScore < 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Score cannot be negative']);
    exit;
}

// Limite máximo de pontos por jogada (anti-cheat)
$maxScorePerMove = 5000; // Máximo razoável por uma única jogada
if ($newScore > $maxScorePerMove) {
    error_log("[SCORE.PHP] WARNING: Score too high ($newScore), capping to $maxScorePerMove");
    $newScore = $maxScorePerMove;
}

error_log("[SCORE.PHP] New score received: $newScore, Level: $currentLevel");

/**
 * Função para calcular a meta de pontuação do level
 */
function getTargetScoreForLevel($level) {
    $baseTargetScore = 1000;
    $targetScore = $baseTargetScore + (($level - 1) * 500);
    
    if ($level > 10) {
        $targetScore += (($level - 10) * 300);
    }
    if ($level > 20) {
        $targetScore += (($level - 20) * 500);
    }
    if ($level > 50) {
        $targetScore += (($level - 50) * 1000);
    }
    
    return $targetScore;
}

// Criar tabela de histórico de scores do Candy se não existir
$createTableSQL = "CREATE TABLE IF NOT EXISTS candy_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    level INT NOT NULL DEFAULT 1,
    score INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_score (score)
)";
$conn->query($createTableSQL);

// Criar tabela de progresso do level se não existir
$createProgressSQL = "CREATE TABLE IF NOT EXISTS candy_level_progress (
    user_id INT PRIMARY KEY,
    current_level INT NOT NULL DEFAULT 1,
    level_score INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($createProgressSQL);

try {
    // Buscar progresso atual do level
    $stmt = $conn->prepare("SELECT current_level, level_score FROM candy_level_progress WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $levelProgress = 0;
    $savedLevel = 1;
    
    if ($row = $result->fetch_assoc()) {
        $savedLevel = intval($row['current_level']);
        $levelProgress = intval($row['level_score']);
    }
    $stmt->close();
    
    // Se o level enviado é diferente do salvo, resetar o progresso do level
    if ($currentLevel != $savedLevel) {
        $levelProgress = 0;
    }
    
    // Calcular meta do level atual
    $targetScore = getTargetScoreForLevel($currentLevel);
    
    // Acumular pontos do level
    $newLevelProgress = $levelProgress + $newScore;
    
    // Verificar se pode avançar de level
    $canAdvance = ($newLevelProgress >= $targetScore);
    
    error_log("[SCORE.PHP] Level progress: $levelProgress + $newScore = $newLevelProgress, Target: $targetScore, Can advance: " . ($canAdvance ? 'YES' : 'NO'));
    
    // Registrar o score no histórico (sempre)
    $stmt = $conn->prepare("INSERT INTO candy_scores (user_id, level, score) VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $userId, $currentLevel, $newScore);
    $stmt->execute();
    $stmt->close();
    
    // Atualizar progresso do level
    $stmt = $conn->prepare("INSERT INTO candy_level_progress (user_id, current_level, level_score) 
                            VALUES (?, ?, ?) 
                            ON DUPLICATE KEY UPDATE current_level = ?, level_score = ?");
    $stmt->bind_param("iiiii", $userId, $currentLevel, $newLevelProgress, $currentLevel, $newLevelProgress);
    $stmt->execute();
    $stmt->close();
    
    // NÃO adicionar pontos ao ranking aqui!
    // Os pontos são adicionados APENAS quando o usuário passa de level (no level.php)
    // Isso evita duplicação de pontos
    
    // Buscar pontos atuais do usuário (apenas para exibição)
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
    
    error_log("[SCORE.PHP] Final - Total points: $userPoints, Daily points: $dailyPoints (no points added here)");
    
    // Mensagem baseada no progresso
    $message = "Score registrado!";
    if ($canAdvance) {
        $message = "Parabéns! Você atingiu a meta e pode avançar para o próximo level!";
    } else {
        $remaining = $targetScore - $newLevelProgress;
        $message = "Faltam $remaining pontos para passar de level. Continue jogando!";
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'added' => true,
            'score' => $newScore,
            'points_added' => 0, // Pontos são adicionados no level.php, não aqui
            'level' => $currentLevel,
            'level_progress' => $newLevelProgress,
            'target_score' => $targetScore,
            'can_advance' => $canAdvance,
            'total_points' => $userPoints,
            'daily_points' => $dailyPoints,
            'message' => $message
        ]
    ]);
    
} catch (Exception $e) {
    error_log("[SCORE.PHP] Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error processing score: ' . $e->getMessage()
    ]);
}

$conn->close();
?>

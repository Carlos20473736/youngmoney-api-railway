<?php
/**
 * Game Level API Endpoint
 * 
 * Gerencia os levels e pontos dos usuários no jogo Candy
 * 
 * GET: Busca o level atual e pontos do level anterior (last_level_score)
 *      Também retorna os parâmetros de dificuldade do level atual
 * POST: Atualiza o level, salva os pontos do level que passou E ADICIONA OS PONTOS À CONTA DO USUÁRIO
 * 
 * NOVA LÓGICA:
 * - last_level_score = pontos que o usuário fez no ÚLTIMO level completado
 * - Ao passar de level, os pontos são AUTOMATICAMENTE adicionados à conta do usuário
 * - Progress bar começa zerado em cada level
 * - Dificuldade aumenta progressivamente a cada level
 * 
 * CORREÇÃO DE SEGURANÇA:
 * - Usuário só pode avançar 1 level por vez
 * - Validação server-side para prevenir manipulação de levels
 */

/**
 * Função para calcular dificuldade baseada no level
 * A dificuldade aumenta progressivamente
 */
function getDifficultyForLevel($level) {
    // CONFIGURAÇÕES BASE (Level 1)
    $baseTimeLimit = 60;        // 60 segundos no level 1
    $baseTargetScore = 1000;    // 1000 pontos para passar no level 1
    $baseCandyTypes = 5;        // 5 tipos de candy no level 1
    $baseSpecialChance = 15;    // 15% de chance de candy especial
    $baseBombFrequency = 0;     // Sem bombas no level 1
    
    // Tempo diminui a cada 5 levels (mínimo 20 segundos)
    $timeReduction = floor(($level - 1) / 5) * 5;
    $timeLimit = max(20, $baseTimeLimit - $timeReduction);
    
    // Meta de pontos aumenta 500 a cada level
    $targetScore = $baseTargetScore + (($level - 1) * 500);
    
    // A cada 10 levels, aumenta mais rápido
    if ($level > 10) {
        $targetScore += (($level - 10) * 300);
    }
    if ($level > 20) {
        $targetScore += (($level - 20) * 500);
    }
    if ($level > 50) {
        $targetScore += (($level - 50) * 1000);
    }
    
    // Tipos de candy aumentam a cada 10 levels (máximo 8)
    $candyTypes = min(8, $baseCandyTypes + floor(($level - 1) / 10));
    
    // Chance de candy especial diminui levemente (mínimo 5%)
    $specialChance = max(5, $baseSpecialChance - floor(($level - 1) / 10) * 2);
    
    // Bombas/obstáculos aparecem a partir do level 5
    $bombFrequency = 0;
    if ($level >= 5) {
        $bombFrequency = min(30, ($level - 4) * 2);
    }
    
    // Multiplicador de pontos aumenta em levels altos
    $pointsMultiplier = 1.0;
    if ($level >= 10) $pointsMultiplier = 1.1;
    if ($level >= 20) $pointsMultiplier = 1.2;
    if ($level >= 30) $pointsMultiplier = 1.3;
    if ($level >= 50) $pointsMultiplier = 1.5;
    if ($level >= 100) $pointsMultiplier = 2.0;
    
    // Determinar tier de dificuldade
    $difficultyTier = 'easy';
    if ($level >= 10) $difficultyTier = 'normal';
    if ($level >= 25) $difficultyTier = 'hard';
    if ($level >= 50) $difficultyTier = 'expert';
    if ($level >= 100) $difficultyTier = 'master';
    
    return [
        'time_limit' => $timeLimit,
        'target_score' => $targetScore,
        'candy_types' => $candyTypes,
        'special_candy_chance' => $specialChance,
        'bomb_frequency' => $bombFrequency,
        'points_multiplier' => $pointsMultiplier,
        'difficulty_tier' => $difficultyTier
    ];
}

// Tratamento de erros
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "PHP Error: $errstr in $errfile:$errline"]);
    exit;
});

try {
    // Incluir configurações do banco de dados
    require_once __DIR__ . '/../../../db_config.php';
    require_once __DIR__ . '/../../../includes/auth_helper.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Include error: ' . $e->getMessage()]);
    exit;
}

// Headers CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-ID');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Conectar ao banco de dados usando a função helper correta
$conn = getMySQLiConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Criar tabela se não existir
$createTableSQL = "CREATE TABLE IF NOT EXISTS game_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    level INT NOT NULL DEFAULT 1,
    highest_level INT NOT NULL DEFAULT 1,
    last_level_score INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$conn->query($createTableSQL);

// Obter usuário autenticado
$user = getAuthenticatedUser($conn);

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Invalid or missing token']);
    exit;
}

$userId = $user['id'];

// Processar requisição
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Buscar level e pontos do usuário
    $stmt = $conn->prepare("SELECT level, highest_level, last_level_score, updated_at FROM game_levels WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $currentLevel = (int)$row['level'];
        $difficulty = getDifficultyForLevel($currentLevel);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'level' => $currentLevel,
                'highest_level' => (int)$row['highest_level'],
                'last_level_score' => (int)$row['last_level_score'],
                'updated_at' => $row['updated_at'],
                'difficulty' => $difficulty
            ]
        ]);
    } else {
        // Usuário não tem registro, retorna level 1 com 0 pontos
        $difficulty = getDifficultyForLevel(1);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'level' => 1,
                'highest_level' => 1,
                'last_level_score' => 0,
                'updated_at' => null,
                'difficulty' => $difficulty
            ]
        ]);
    }
    $stmt->close();
    
} elseif ($method === 'POST') {
    // Atualizar level e pontos do usuário
    // Ler body da variável global (quando passa pelo secure.php) ou do php://input
    $rawBody = isset($GLOBALS['_SECURE_REQUEST_BODY']) ? $GLOBALS['_SECURE_REQUEST_BODY'] : file_get_contents('php://input');
    $input = json_decode($rawBody, true);
    
    if (!isset($input['level']) || !is_numeric($input['level'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Level is required and must be a number']);
        exit;
    }
    
    $newLevel = (int)$input['level'];
    $lastLevelScore = isset($input['last_level_score']) ? (int)$input['last_level_score'] : 0;
    
    if ($newLevel < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Level must be at least 1']);
        exit;
    }
    
    // ========================================
    // CORREÇÃO DE SEGURANÇA: Validar progressão de level
    // ========================================
    
    // Buscar level atual do usuário
    $stmt = $conn->prepare("SELECT level, highest_level FROM game_levels WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $currentLevel = 1;
    $currentHighest = 1;
    $hasRecord = false;
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $currentLevel = (int)$row['level'];
        $currentHighest = (int)$row['highest_level'];
        $hasRecord = true;
    }
    $stmt->close();
    
    // ========================================
    // VALIDAÇÃO: Usuário só pode avançar 1 level por vez
    // ========================================
    if ($newLevel > $currentLevel + 1) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid level progression. You can only advance one level at a time.',
            'current_level' => $currentLevel,
            'requested_level' => $newLevel,
            'max_allowed_level' => $currentLevel + 1
        ]);
        exit;
    }
    
    // Permitir também resetar para level 1 (reiniciar jogo)
    if ($newLevel < $currentLevel && $newLevel != 1) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid level. Cannot go back to a previous level (except level 1 to restart).',
            'current_level' => $currentLevel,
            'requested_level' => $newLevel
        ]);
        exit;
    }
    
    $pointsAdded = 0;
    $newHighest = max($currentHighest, $newLevel);
    
    if ($hasRecord) {
        // Atualizar registro existente
        $stmt = $conn->prepare("UPDATE game_levels SET level = ?, highest_level = ?, last_level_score = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("iiii", $newLevel, $newHighest, $lastLevelScore, $userId);
        $stmt->execute();
        $stmt->close();
        
    } else {
        // Inserir novo registro
        $stmt = $conn->prepare("INSERT INTO game_levels (user_id, level, highest_level, last_level_score) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiii", $userId, $newLevel, $newLevel, $lastLevelScore);
        $stmt->execute();
        $stmt->close();
    }
    
    // ========================================
    // ADICIONAR PONTOS À CONTA DO USUÁRIO
    // Só adiciona pontos se avançou de level (não se resetou para level 1)
    // ========================================
    if ($lastLevelScore > 0 && $newLevel > $currentLevel) {
        $pointsAdded = $lastLevelScore;
        
        // Adicionar pontos ao ranking do usuário (daily_points para o ranking diário)
        $stmt = $conn->prepare("UPDATE users SET daily_points = daily_points + ?, points = points + ? WHERE id = ?");
        $stmt->bind_param("iii", $pointsAdded, $pointsAdded, $userId);
        $stmt->execute();
        $stmt->close();
        
        // Registrar no histórico de pontos
        $description = "Candy Crush - Level " . ($newLevel - 1) . " completado: " . $pointsAdded . " pontos";
        $stmt = $conn->prepare("INSERT INTO points_history (user_id, points, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $userId, $pointsAdded, $description);
        $stmt->execute();
        $stmt->close();
    }
    
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
    
    // Obter dificuldade do novo level
    $difficulty = getDifficultyForLevel($newLevel);
    
    echo json_encode([
        'success' => true,
        'message' => 'Level updated successfully',
        'data' => [
            'user_id' => $userId,
            'level' => $newLevel,
            'highest_level' => $newHighest,
            'last_level_score' => $lastLevelScore,
            'points_added' => $pointsAdded,
            'daily_points' => $dailyPoints,
            'total_points' => $userPoints,
            'difficulty' => $difficulty
        ]
    ]);
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

$conn->close();
?>

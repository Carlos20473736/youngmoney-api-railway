<?php
/**
 * Game Level API Endpoint
 * 
 * Gerencia os levels e pontos dos usuários no jogo Candy
 * 
 * GET: Busca o level atual e pontos do level anterior (last_level_score)
 * POST: Atualiza o level e salva os pontos do level que passou
 * 
 * NOVA LÓGICA:
 * - last_level_score = pontos que o usuário fez no ÚLTIMO level completado
 * - Ao iniciar um novo level, mostra os pontos do level anterior
 * - Progress bar começa zerado em cada level
 * - CORREÇÃO: Agora credita os pontos ao usuário quando o level termina
 * - CORREÇÃO: Lê o body corretamente quando passa pelo secure.php
 */

// Tratamento de erros
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("[LEVEL.PHP] PHP Error: $errstr in $errfile:$errline");
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

// Obter conexão usando a função do db_config
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
    total_score INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$conn->query($createTableSQL);

// Adicionar coluna total_score se não existir
$conn->query("ALTER TABLE game_levels ADD COLUMN IF NOT EXISTS total_score INT NOT NULL DEFAULT 0");

// Obter usuário autenticado
$user = getAuthenticatedUser($conn);

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Invalid or missing token']);
    exit;
}

$userId = $user['id'];

error_log("[LEVEL.PHP] User ID: $userId, Method: " . $_SERVER['REQUEST_METHOD']);

// Processar requisição
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Buscar level e pontos do usuário
    $stmt = $conn->prepare("SELECT level, highest_level, last_level_score, total_score, updated_at FROM game_levels WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'level' => (int)$row['level'],
                'highest_level' => (int)$row['highest_level'],
                'last_level_score' => (int)$row['last_level_score'],
                'total_score' => (int)($row['total_score'] ?? 0),
                'updated_at' => $row['updated_at']
            ]
        ]);
    } else {
        // Usuário não tem registro, retorna level 1 com 0 pontos
        echo json_encode([
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'level' => 1,
                'highest_level' => 1,
                'last_level_score' => 0,
                'total_score' => 0,
                'updated_at' => null
            ]
        ]);
    }
    $stmt->close();
    
} elseif ($method === 'POST') {
    // CORREÇÃO: Ler body da variável global (quando passa pelo secure.php) ou do php://input
    $rawBody = isset($GLOBALS['_SECURE_REQUEST_BODY']) ? $GLOBALS['_SECURE_REQUEST_BODY'] : file_get_contents('php://input');
    $input = json_decode($rawBody, true);
    
    // Log para debug
    error_log("[LEVEL.PHP] Raw body: " . $rawBody);
    error_log("[LEVEL.PHP] Input parsed: " . json_encode($input));
    
    if (!isset($input['level']) || !is_numeric($input['level'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Level is required and must be a number', 'debug_raw_body' => $rawBody]);
        exit;
    }
    
    $newLevel = (int)$input['level'];
    $lastLevelScore = isset($input['last_level_score']) ? (int)$input['last_level_score'] : 0;
    
    // Log para debug
    error_log("[LEVEL.PHP] New level: $newLevel, Last level score: $lastLevelScore");
    
    if ($newLevel < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Level must be at least 1']);
        exit;
    }
    
    // Verificar se já existe registro
    $stmt = $conn->prepare("SELECT level, highest_level, total_score FROM game_levels WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pointsAdded = 0;
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $currentHighest = (int)$row['highest_level'];
        $currentTotalScore = (int)($row['total_score'] ?? 0);
        $newHighest = max($currentHighest, $newLevel);
        $newTotalScore = $currentTotalScore + $lastLevelScore;
        
        // Atualizar registro existente
        $stmt->close();
        $stmt = $conn->prepare("UPDATE game_levels SET level = ?, highest_level = ?, last_level_score = ?, total_score = ? WHERE user_id = ?");
        $stmt->bind_param("iiiii", $newLevel, $newHighest, $lastLevelScore, $newTotalScore, $userId);
        
        if ($stmt->execute()) {
            // CORREÇÃO: Creditar os pontos ao usuário quando o level termina
            if ($lastLevelScore > 0) {
                $pointsAdded = $lastLevelScore;
                
                // Adicionar pontos ao ranking do usuário (daily_points para o ranking diário)
                $stmtPoints = $conn->prepare("UPDATE users SET daily_points = daily_points + ?, points = points + ? WHERE id = ?");
                $stmtPoints->bind_param("iii", $pointsAdded, $pointsAdded, $userId);
                $stmtPoints->execute();
                $stmtPoints->close();
                
                // Registrar no histórico de pontos
                $description = "Candy Crush - Level " . ($newLevel - 1) . " completado: " . $lastLevelScore . " pontos";
                $stmtHistory = $conn->prepare("INSERT INTO points_history (user_id, points, description, created_at) VALUES (?, ?, ?, NOW())");
                $stmtHistory->bind_param("iis", $userId, $pointsAdded, $description);
                $stmtHistory->execute();
                $stmtHistory->close();
                
                error_log("[LEVEL.PHP] Points added to user $userId: $pointsAdded");
            }
            
            // Buscar pontos atualizados do usuário
            $stmtUser = $conn->prepare("SELECT points, daily_points FROM users WHERE id = ?");
            $stmtUser->bind_param("i", $userId);
            $stmtUser->execute();
            $userResult = $stmtUser->get_result();
            $userPoints = 0;
            $dailyPoints = 0;
            if ($userRow = $userResult->fetch_assoc()) {
                $userPoints = (int)$userRow['points'];
                $dailyPoints = (int)$userRow['daily_points'];
            }
            $stmtUser->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'Level updated successfully',
                'data' => [
                    'user_id' => $userId,
                    'level' => $newLevel,
                    'highest_level' => $newHighest,
                    'last_level_score' => $lastLevelScore,
                    'total_score' => $newTotalScore,
                    'points_added' => $pointsAdded,
                    'total_points' => $userPoints,
                    'daily_points' => $dailyPoints
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update level']);
        }
    } else {
        // Inserir novo registro
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO game_levels (user_id, level, highest_level, last_level_score, total_score) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiii", $userId, $newLevel, $newLevel, $lastLevelScore, $lastLevelScore);
        
        if ($stmt->execute()) {
            // CORREÇÃO: Creditar os pontos ao usuário quando o level termina (primeiro level)
            if ($lastLevelScore > 0) {
                $pointsAdded = $lastLevelScore;
                
                // Adicionar pontos ao ranking do usuário
                $stmtPoints = $conn->prepare("UPDATE users SET daily_points = daily_points + ?, points = points + ? WHERE id = ?");
                $stmtPoints->bind_param("iii", $pointsAdded, $pointsAdded, $userId);
                $stmtPoints->execute();
                $stmtPoints->close();
                
                // Registrar no histórico de pontos
                $description = "Candy Crush - Level " . ($newLevel - 1) . " completado: " . $lastLevelScore . " pontos";
                $stmtHistory = $conn->prepare("INSERT INTO points_history (user_id, points, description, created_at) VALUES (?, ?, ?, NOW())");
                $stmtHistory->bind_param("iis", $userId, $pointsAdded, $description);
                $stmtHistory->execute();
                $stmtHistory->close();
                
                error_log("[LEVEL.PHP] Points added to new user $userId: $pointsAdded");
            }
            
            // Buscar pontos atualizados do usuário
            $stmtUser = $conn->prepare("SELECT points, daily_points FROM users WHERE id = ?");
            $stmtUser->bind_param("i", $userId);
            $stmtUser->execute();
            $userResult = $stmtUser->get_result();
            $userPoints = 0;
            $dailyPoints = 0;
            if ($userRow = $userResult->fetch_assoc()) {
                $userPoints = (int)$userRow['points'];
                $dailyPoints = (int)$userRow['daily_points'];
            }
            $stmtUser->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'Level created successfully',
                'data' => [
                    'user_id' => $userId,
                    'level' => $newLevel,
                    'highest_level' => $newLevel,
                    'last_level_score' => $lastLevelScore,
                    'total_score' => $lastLevelScore,
                    'points_added' => $pointsAdded,
                    'total_points' => $userPoints,
                    'daily_points' => $dailyPoints
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create level record']);
        }
    }
    $stmt->close();
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

$conn->close();
?>

<?php
/**
 * Game Level API Endpoint
 * 
 * Gerencia os levels dos usuários no jogo Candy
 * 
 * GET: Busca o level atual do usuário
 * POST: Atualiza o level do usuário quando passa de fase
 */

// Incluir configurações do banco de dados
require_once __DIR__ . '/../../../db_config.php';
require_once __DIR__ . '/../../../includes/auth_helper.php';

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

// Conectar ao banco de dados
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
    // Buscar level do usuário
    $stmt = $conn->prepare("SELECT level, highest_level, updated_at FROM game_levels WHERE user_id = ?");
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
                'updated_at' => $row['updated_at']
            ]
        ]);
    } else {
        // Usuário não tem registro, retorna level 1
        echo json_encode([
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'level' => 1,
                'highest_level' => 1,
                'updated_at' => null
            ]
        ]);
    }
    $stmt->close();
    
} elseif ($method === 'POST') {
    // Atualizar level do usuário
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['level']) || !is_numeric($input['level'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Level is required and must be a number']);
        exit;
    }
    
    $newLevel = (int)$input['level'];
    
    if ($newLevel < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Level must be at least 1']);
        exit;
    }
    
    // Verificar se já existe registro
    $stmt = $conn->prepare("SELECT level, highest_level FROM game_levels WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $currentHighest = (int)$row['highest_level'];
        $newHighest = max($currentHighest, $newLevel);
        
        // Atualizar registro existente
        $stmt->close();
        $stmt = $conn->prepare("UPDATE game_levels SET level = ?, highest_level = ? WHERE user_id = ?");
        $stmt->bind_param("iii", $newLevel, $newHighest, $userId);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Level updated successfully',
                'data' => [
                    'user_id' => $userId,
                    'level' => $newLevel,
                    'highest_level' => $newHighest
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update level']);
        }
    } else {
        // Inserir novo registro
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO game_levels (user_id, level, highest_level) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $userId, $newLevel, $newLevel);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Level created successfully',
                'data' => [
                    'user_id' => $userId,
                    'level' => $newLevel,
                    'highest_level' => $newLevel
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

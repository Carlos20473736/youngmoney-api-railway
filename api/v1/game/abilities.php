<?php
/**
 * Game Abilities API Endpoint
 * 
 * Gerencia as habilidades dos usuários no jogo Candy
 * 
 * GET: Busca as habilidades atuais do usuário
 * POST: Atualiza as habilidades (ao usar ou ao assistir vídeo)
 * 
 * HABILIDADES:
 * - cascade: Cascata (inicial: 10)
 * - time_freeze: Pausa Tempo/Gelo (inicial: 6)
 * - double_points: Pontos x2 (inicial: 2)
 * 
 * Level 1: Começa com valores iniciais
 * Outros levels: Carrega do servidor (pode estar zerado)
 * Assistir vídeo: Restaura todas as habilidades
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

// Valores iniciais das habilidades (começam em 0, só ganham assistindo vídeo)
define('INITIAL_CASCADE', 0);
define('INITIAL_TIME_FREEZE', 0);
define('INITIAL_DOUBLE_POINTS', 0);

// Valores ao restaurar (após assistir vídeo)
define('RESTORE_CASCADE', 10);
define('RESTORE_TIME_FREEZE', 6);
define('RESTORE_DOUBLE_POINTS', 2);

// Conectar ao banco de dados usando a função helper
try {
    $conn = getMySQLiConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Criar tabela se não existir
$createTableSQL = "CREATE TABLE IF NOT EXISTS game_abilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    cascade_charges INT NOT NULL DEFAULT 0,
    time_freeze_charges INT NOT NULL DEFAULT 0,
    double_points_charges INT NOT NULL DEFAULT 0,
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
    // Buscar habilidades do usuário
    $stmt = $conn->prepare("SELECT cascade_charges, time_freeze_charges, double_points_charges, updated_at FROM game_abilities WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'cascade' => (int)$row['cascade_charges'],
                'time_freeze' => (int)$row['time_freeze_charges'],
                'double_points' => (int)$row['double_points_charges'],
                'updated_at' => $row['updated_at']
            ]
        ]);
    } else {
        // Usuário não tem registro, criar com valores iniciais
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO game_abilities (user_id, cascade_charges, time_freeze_charges, double_points_charges) VALUES (?, ?, ?, ?)");
        $cascade = INITIAL_CASCADE;
        $timeFreeze = INITIAL_TIME_FREEZE;
        $doublePoints = INITIAL_DOUBLE_POINTS;
        $stmt->bind_param("iiii", $userId, $cascade, $timeFreeze, $doublePoints);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'cascade' => INITIAL_CASCADE,
                'time_freeze' => INITIAL_TIME_FREEZE,
                'double_points' => INITIAL_DOUBLE_POINTS,
                'updated_at' => null,
                'is_new' => true
            ]
        ]);
    }
    $stmt->close();
    
} elseif ($method === 'POST') {
    // Atualizar habilidades do usuário
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Verificar se é para restaurar todas as habilidades (assistiu vídeo)
    $restoreAll = isset($input['restore_all']) && $input['restore_all'] === true;
    
    if ($restoreAll) {
        // Restaurar todas as habilidades para valores de recompensa (após assistir vídeo)
        $cascade = RESTORE_CASCADE;
        $timeFreeze = RESTORE_TIME_FREEZE;
        $doublePoints = RESTORE_DOUBLE_POINTS;
    } else {
        // Atualizar valores específicos
        if (!isset($input['cascade']) && !isset($input['time_freeze']) && !isset($input['double_points'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'At least one ability value is required']);
            exit;
        }
        
        $cascade = isset($input['cascade']) ? max(0, (int)$input['cascade']) : null;
        $timeFreeze = isset($input['time_freeze']) ? max(0, (int)$input['time_freeze']) : null;
        $doublePoints = isset($input['double_points']) ? max(0, (int)$input['double_points']) : null;
    }
    
    // Verificar se já existe registro
    $stmt = $conn->prepare("SELECT id FROM game_abilities WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        
        if ($restoreAll) {
            // Restaurar todos
            $stmt = $conn->prepare("UPDATE game_abilities SET cascade_charges = ?, time_freeze_charges = ?, double_points_charges = ? WHERE user_id = ?");
            $stmt->bind_param("iiii", $cascade, $timeFreeze, $doublePoints, $userId);
        } else {
            // Construir query dinâmica para atualizar apenas os campos fornecidos
            $updates = [];
            $params = [];
            $types = "";
            
            if ($cascade !== null) {
                $updates[] = "cascade_charges = ?";
                $params[] = $cascade;
                $types .= "i";
            }
            if ($timeFreeze !== null) {
                $updates[] = "time_freeze_charges = ?";
                $params[] = $timeFreeze;
                $types .= "i";
            }
            if ($doublePoints !== null) {
                $updates[] = "double_points_charges = ?";
                $params[] = $doublePoints;
                $types .= "i";
            }
            
            $params[] = $userId;
            $types .= "i";
            
            $sql = "UPDATE game_abilities SET " . implode(", ", $updates) . " WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
        }
        
        if ($stmt->execute()) {
            // Buscar valores atualizados
            $stmt->close();
            $stmt = $conn->prepare("SELECT cascade_charges, time_freeze_charges, double_points_charges FROM game_abilities WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            echo json_encode([
                'success' => true,
                'message' => $restoreAll ? 'Abilities restored successfully' : 'Abilities updated successfully',
                'data' => [
                    'user_id' => $userId,
                    'cascade' => (int)$row['cascade_charges'],
                    'time_freeze' => (int)$row['time_freeze_charges'],
                    'double_points' => (int)$row['double_points_charges']
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update abilities']);
        }
    } else {
        // Inserir novo registro
        $stmt->close();
        
        // Se não forneceu valores, usar iniciais
        $cascade = $cascade ?? INITIAL_CASCADE;
        $timeFreeze = $timeFreeze ?? INITIAL_TIME_FREEZE;
        $doublePoints = $doublePoints ?? INITIAL_DOUBLE_POINTS;
        
        $stmt = $conn->prepare("INSERT INTO game_abilities (user_id, cascade_charges, time_freeze_charges, double_points_charges) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiii", $userId, $cascade, $timeFreeze, $doublePoints);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Abilities created successfully',
                'data' => [
                    'user_id' => $userId,
                    'cascade' => $cascade,
                    'time_freeze' => $timeFreeze,
                    'double_points' => $doublePoints
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create abilities record']);
        }
    }
    $stmt->close();
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

$conn->close();
?>

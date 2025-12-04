<?php
// Endpoint da API para Usuários (v2 - Ultra Security)

header("Content-Type: application/json");
require_once '../../database.php';
require_once __DIR__ . '/../../includes/UltraSecuritySystem.php';
require_once '../../middleware/auto_reset.php';

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDbConnection();

// ✅ VALIDAÇÃO ULTRA SEGURA
$userData = validateSecureRequest($conn, true);
// Se chegou aqui, requisição é 100% válida!

// Verificar e fazer reset automático se necessário
checkAndResetRanking($conn);

switch ($method) {
    case 'GET':
        // Lógica para obter dados de usuários
        if (isset($_GET['id'])) {
            $userId = intval($_GET['id']);
            
            $stmt = $conn->prepare("SELECT id, email, google_id, created_at FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                echo json_encode($user);
            } else {
                http_response_code(404);
                echo json_encode(["message" => "User not found"]);
            }
        } else {
            // Listar todos os usuários (paginado)
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            $stmt = $conn->prepare("SELECT id, email, google_id, created_at FROM users LIMIT ? OFFSET ?");
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            
            echo json_encode([
                "page" => $page,
                "users" => $users
            ]);
        }
        break;
        
    case 'POST':
        // Criar novo usuário (apenas admin)
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($input['email']) || !isset($input['google_id'])) {
            http_response_code(400);
            echo json_encode(["message" => "Missing required fields"]);
            break;
        }
        
        $email = $input['email'];
        $googleId = $input['google_id'];
        
        $stmt = $conn->prepare("INSERT INTO users (email, google_id) VALUES (?, ?)");
        $stmt->bind_param("ss", $email, $googleId);
        
        if ($stmt->execute()) {
            echo json_encode([
                "message" => "User created successfully",
                "id" => $stmt->insert_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to create user"]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(["message" => "Method not allowed"]);
        break;
}

$conn->close();
?>

<?php
// Endpoint da API para Usuários (v1)



header("Content-Type: application/json");
require_once '../../database.php';
require_once __DIR__ . '/../../includes/HeadersValidator.php';
require_once '../../middleware/auto_reset.php';

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDbConnection();

// Validar headers de segurança
$validator = validateRequestHeaders($conn, true);
if (!$validator) exit; // Já enviou resposta de erro

// Verificar e fazer reset automático se necessário
checkAndResetRanking($conn);

switch ($method) {
    case 'GET':
        // Lógica para obter dados de usuários (ex: perfil)
        // Exemplo: /api/v1/users.php?id=1
        if (isset($_GET['id'])) {
            $userId = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT id, username, email, profile_picture, points, created_at FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                echo json_encode($result->fetch_assoc());
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'User not found']);
            }
            $stmt->close();
        } else {
            // Lógica para listar todos os usuários (cuidado com a privacidade)
            http_response_code(400);
            echo json_encode(['message' => 'User ID is required']);
        }
        break;

    case 'POST':
        // Lógica para criar um novo usuário (registro)
        $data = json_decode(file_get_contents('php://input'), true);

        if (isset($data['username']) && isset($data['password']) && isset($data['email'])) {
            $username = $data['username'];
            $password = password_hash($data['password'], PASSWORD_DEFAULT);
            $email = $data['email'];

            $stmt = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $password, $email);

            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(['message' => 'User created successfully', 'user_id' => $conn->insert_id]);
            } else {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to create user', 'error' => $stmt->error]);
            }
            $stmt->close();
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Username, password, and email are required']);
        }
        break;

    // Implementar PUT para atualizar e DELETE para excluir usuários conforme necessário

    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method Not Allowed']);
        break;
}

$conn->close();
?>

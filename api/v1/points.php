<?php
// Endpoint da API para Pontos (v1 )



header("Content-Type: application/json");
require_once '../../database.php';
require_once __DIR__ . '/../../includes/HeadersValidator.php';

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDbConnection();

// Validar headers de segurança
$validator = validateRequestHeaders($conn, true);
if (!$validator) exit; // Já enviou resposta de erro


switch ($method) {
    case 'GET':
        // Lógica para obter o histórico de pontos de um usuário
        // Exemplo: /api/v1/points.php?user_id=1
        if (isset($_GET['user_id'])) {
            $userId = intval($_GET['user_id']);
            $stmt = $conn->prepare("SELECT id, points, description, created_at FROM points_history WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $history = [];
            while ($row = $result->fetch_assoc()) {
                $history[] = $row;
            }
            echo json_encode($history);
            $stmt->close();
        } else {
            http_response_code(400 );
            echo json_encode(['message' => 'User ID is required']);
        }
        break;

    case 'POST':
        // Lógica para adicionar pontos a um usuário
        $data = json_decode(file_get_contents('php://input'), true);

        if (isset($data['user_id']) && isset($data['points_earned']) && isset($data['activity_type'])) {
            $userId = intval($data['user_id']);
            $pointsEarned = intval($data['points_earned']);
            $activityType = $data['activity_type'];

            $conn->begin_transaction();

            try {
                // Inserir no histórico de pontos
                $stmt1 = $conn->prepare("INSERT INTO points_history (user_id, points, description, created_at) VALUES (?, ?, ?, NOW())");
                $stmt1->bind_param("iis", $userId, $pointsEarned, $activityType);
                $stmt1->execute();
                $stmt1->close();

                // Atualizar o total de pontos do usuário
                $stmt2 = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                $stmt2->bind_param("ii", $pointsEarned, $userId);
                $stmt2->execute();
                $stmt2->close();

                $conn->commit();
                http_response_code(200 );
                echo json_encode(['message' => 'Points added successfully']);

            } catch (Exception $e) {
                $conn->rollback();
                http_response_code(500 );
                echo json_encode(['message' => 'Failed to add points', 'error' => $e->getMessage()]);
            }
        } else {
            http_response_code(400 );
            echo json_encode(['message' => 'User ID, points earned, and activity type are required']);
        }
        break;

    default:
        http_response_code(405 );
        echo json_encode(['message' => 'Method Not Allowed']);
        break;
}

$conn->close();
?>

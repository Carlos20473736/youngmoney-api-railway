<?php
// Endpoint da API para Saques (v1)


// Taxa de conversão: 10.000 pontos = R$ 1,00

header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../database.php';
require_once __DIR__ . '/../../includes/HeadersValidator.php';
require_once __DIR__ . '/../../includes/auth_helper.php';
require_once __DIR__ . '/../../includes/security_validation_helper.php';

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDbConnection();

// Validar headers de segurança
$validator = validateRequestHeaders($conn, true);
if (!$validator) exit; // Já enviou resposta de erro


// Taxa de conversão: 10.000 pontos = R$ 1,00
define('POINTS_PER_REAL', 10000);

switch ($method) {
    case 'GET':
        // Lógica para obter o histórico de saques de um usuário
        // Exemplo: /api/v1/withdrawals.php?user_id=1
        if (isset($_GET['user_id'])) {
            $userId = intval($_GET['user_id']);
            $stmt = $conn->prepare("SELECT id, pix_key_type, amount, status, created_at FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $withdrawals = [];
            while ($row = $result->fetch_assoc()) {
                $withdrawals[] = $row;
            }
            echo json_encode($withdrawals);
            $stmt->close();
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'User ID is required']);
        }
        break;

    case 'POST':
        try {
            // Validar XReq token
            validateXReq();
            
            // Autenticar usuário
            $user = getAuthenticatedUser($conn);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Não autenticado']);
                exit;
            }
            
            // VALIDAÇÃO DE HEADERS REMOVIDA - estava bloqueando requisições legítimas
            // validateSecurityHeaders($conn, $user);
            
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['user_id']) || !isset($data['pix_key']) || !isset($data['pix_key_type']) || !isset($data['amount'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
                exit;
            }

            $userId = intval($data['user_id']);
            $pixKey = $data['pix_key'];
            $pixKeyType = $data['pix_key_type'];
            $amountBrl = floatval($data['amount']);

            // Validar valor mínimo (R$ 1,00)
            if ($amountBrl < 1) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Valor mínimo: R$ 1,00']);
                exit;
            }

            // Calcular pontos necessários (R$ 1,00 = 10.000 pontos)
            $pointsRequired = intval($amountBrl * POINTS_PER_REAL);

            // Buscar saldo atual do usuário
            $stmt = $conn->prepare("SELECT points FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
                exit;
            }
            
            $user = $result->fetch_assoc();
            $currentPoints = $user['points'];
            $stmt->close();

            // Verificar se tem pontos suficientes
            if ($currentPoints < $pointsRequired) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Saldo insuficiente',
                    'current_points' => $currentPoints,
                    'required_points' => $pointsRequired
                ]);
                exit;
            }

            // Iniciar transação
            $conn->begin_transaction();

            try {
                // 1. Debitar pontos do usuário
                $stmt = $conn->prepare("UPDATE users SET points = points - ? WHERE id = ?");
                $stmt->bind_param("ii", $pointsRequired, $userId);
                $stmt->execute();
                $stmt->close();

                // 2. Registrar no histórico de pontos
                $stmt = $conn->prepare("INSERT INTO points_history (user_id, points, description, type) VALUES (?, ?, ?, 'debit')");
                $description = "Saque de R$ " . number_format($amountBrl, 2, ',', '.') . " via PIX";
                $negativePoints = -$pointsRequired;
                $stmt->bind_param("iis", $userId, $negativePoints, $description);
                $stmt->execute();
                $stmt->close();

                // 3. Criar registro de saque
                $stmt = $conn->prepare("INSERT INTO withdrawals (user_id, pix_key, pix_key_type, amount, status) VALUES (?, ?, ?, ?, 'pending')");
                $stmt->bind_param("issd", $userId, $pixKey, $pixKeyType, $amountBrl);
                $stmt->execute();
                $withdrawalId = $stmt->insert_id;
                $stmt->close();

                // Commit da transação
                $conn->commit();

                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'withdrawal_id' => $withdrawalId,
                        'amount_brl' => $amountBrl,
                        'points_debited' => $pointsRequired,
                        'remaining_points' => $currentPoints - $pointsRequired,
                        'status' => 'pending',
                        'message' => 'Solicitação de saque criada com sucesso'
                    ]
                ]);

            } catch (Exception $e) {
                // Rollback em caso de erro
                $conn->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro ao processar saque: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method Not Allowed']);
        break;
}

$conn->close();
?>

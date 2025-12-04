<?php
// Endpoint para configurações de pontos de convite

header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../database.php';

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDbConnection();

switch ($method) {
    case 'GET':
        // GET /admin/invite_settings.php - Obter configurações de pontos de convite
        try {
            $stmt = $conn->prepare("
                SELECT setting_key, setting_value 
                FROM system_settings 
                WHERE setting_key IN ('invite_points_inviter', 'invite_points_invited')
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $points = [
                'points_inviter' => 500,  // Padrão
                'points_invited' => 500   // Padrão
            ];
            
            while ($row = $result->fetch_assoc()) {
                if ($row['setting_key'] === 'invite_points_inviter') {
                    $points['points_inviter'] = intval($row['setting_value']);
                } elseif ($row['setting_key'] === 'invite_points_invited') {
                    $points['points_invited'] = intval($row['setting_value']);
                }
            }
            
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'data' => $points
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao buscar configurações: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'POST':
        // POST /admin/invite_settings.php - Atualizar configurações de pontos de convite
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['points_inviter']) || !isset($data['points_invited'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Parâmetros inválidos'
                ]);
                exit;
            }
            
            $pointsInviter = intval($data['points_inviter']);
            $pointsInvited = intval($data['points_invited']);
            
            // Validar valores
            if ($pointsInviter < 0 || $pointsInvited < 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Pontos não podem ser negativos'
                ]);
                exit;
            }
            
            if ($pointsInviter > 100000 || $pointsInvited > 100000) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Pontos não podem exceder 100.000'
                ]);
                exit;
            }
            
            // Atualizar ou inserir configurações
            $stmt = $conn->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES ('invite_points_inviter', ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->bind_param("ss", $pointsInviter, $pointsInviter);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $conn->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES ('invite_points_invited', ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->bind_param("ss", $pointsInvited, $pointsInvited);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'Configurações atualizadas com sucesso'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao atualizar configurações: ' . $e->getMessage()
            ]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método não permitido'
        ]);
        break;
}

$conn->close();

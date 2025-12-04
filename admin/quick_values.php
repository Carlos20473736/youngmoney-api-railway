<?php
/**
 * Endpoint de Gerenciamento de Valores Rápidos
 * 
 * GET    - Listar todos os valores
 * POST   - Criar novo valor
 * PUT    - Atualizar valor existente
 * DELETE - Remover valor
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // Listar valores
            $result = $conn->query("
                SELECT id, value_amount, display_order, is_active 
                FROM withdrawal_quick_values 
                ORDER BY display_order ASC
            ");
            
            $values = [];
            while ($row = $result->fetch_assoc()) {
                $values[] = [
                    'id' => (int)$row['id'],
                    'value' => (float)$row['value_amount'],
                    'order' => (int)$row['display_order'],
                    'active' => (bool)$row['is_active']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'data' => $values
            ]);
            break;
            
        case 'POST':
            // Criar novo valor
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['value']) || ($input['value'] <= 0 && $input['value'] !== -1)) {
                throw new Exception('Valor inválido');
            }
            
            // Obter próxima ordem
            $result = $conn->query("SELECT MAX(display_order) as max_order FROM withdrawal_quick_values");
            $row = $result->fetch_assoc();
            $nextOrder = ($row['max_order'] ?? 0) + 1;
            
            $stmt = $conn->prepare("
                INSERT INTO withdrawal_quick_values (value_amount, display_order) 
                VALUES (?, ?)
            ");
            $stmt->bind_param("di", $input['value'], $nextOrder);
            $stmt->execute();
            
            $newId = $conn->insert_id;
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'Valor adicionado com sucesso',
                'id' => $newId
            ]);
            break;
            
        case 'PUT':
            // Atualizar valor
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id']) || !isset($input['value'])) {
                throw new Exception('ID e valor são obrigatórios');
            }
            
            $stmt = $conn->prepare("
                UPDATE withdrawal_quick_values 
                SET value_amount = ?, display_order = ? 
                WHERE id = ?
            ");
            $stmt->bind_param("dii", $input['value'], $input['order'], $input['id']);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'Valor atualizado com sucesso'
            ]);
            break;
            
        case 'DELETE':
            // Remover valor
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id'])) {
                throw new Exception('ID é obrigatório');
            }
            
            $stmt = $conn->prepare("DELETE FROM withdrawal_quick_values WHERE id = ?");
            $stmt->bind_param("i", $input['id']);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'Valor removido com sucesso'
            ]);
            break;
            
        default:
            throw new Exception('Método não suportado');
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

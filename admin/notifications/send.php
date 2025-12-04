<?php
error_reporting(E_ERROR | E_PARSE); // Suprimir warnings
require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $userId = $input['user_id'] ?? null;
    $title = $input['title'] ?? '';
    $message = $input['message'] ?? '';
    $points = $input['points'] ?? null; // Novo campo para pontos
    
    if (empty($title) || empty($message)) {
        throw new Exception('Título e mensagem são obrigatórios');
    }
    
    $conn = getDbConnection();
    
    if ($userId === null) {
        // Enviar para todos os usuários
        $stmt = $conn->prepare("SELECT id FROM users");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $insertStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, created_at) VALUES (?, ?, ?, NOW())");
        
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $insertStmt->bind_param('iss', $row['id'], $title, $message);
            $insertStmt->execute();
            
            // Se pontos foram especificados, adicionar aos usuários
            if ($points !== null && $points > 0) {
                addPointsToUser($conn, $row['id'], (int)$points, $title);
            }
            
            $count++;
        }
        
        echo json_encode(['success' => true, 'data' => ['count' => $count, 'points_added' => $points !== null]]);
    } else {
        // Enviar para um usuário específico
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param('iss', $userId, $title, $message);
        $stmt->execute();
        
        // Se pontos foram especificados, adicionar ao usuário
        if ($points !== null && $points > 0) {
            addPointsToUser($conn, $userId, (int)$points, $title);
        }
        
        echo json_encode(['success' => true, 'data' => ['id' => $conn->insert_id, 'points_added' => $points !== null]]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Adiciona pontos a um usuário específico
 */
function addPointsToUser($conn, $userId, $points, $description) {
    try {
        // Iniciar transação
        $conn->begin_transaction();
        
        // 1. Atualizar pontos totais E pontos diários do usuário
        $stmt = $conn->prepare("UPDATE users SET points = points + ?, daily_points = daily_points + ? WHERE id = ?");
        $stmt->bind_param("iii", $points, $points, $userId);
        $stmt->execute();
        
        // 2. Salvar no histórico
        $stmt = $conn->prepare("INSERT INTO points_history (user_id, points, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $userId, $points, $description);
        $stmt->execute();
        
        // 3. Obter período ativo (diário por padrão)
        $periodType = 'daily';
        
        // Limpar resultados pendentes antes de multi_query
        while ($conn->more_results()) {
            $conn->next_result();
            if ($res = $conn->store_result()) {
                $res->free();
            }
        }
        
        // Chamar stored procedure e pegar resultado
        $periodId = null;
        $safeType = $conn->real_escape_string($periodType);
        
        if ($conn->multi_query("CALL get_active_period('$safeType')")) {
            do {
                if ($result = $conn->store_result()) {
                    if ($periodRow = $result->fetch_assoc()) {
                        $periodId = isset($periodRow['period_id']) ? $periodRow['period_id'] : null;
                    }
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
        }
        
        if ($periodId) {
            // 4. Atualizar pontos do ranking do período
            $stmt = $conn->prepare("
                INSERT INTO ranking_points (user_id, period_id, points)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE points = points + VALUES(points)
            ");
            $stmt->bind_param("iii", $userId, $periodId, $points);
            $stmt->execute();
        }
        
        // Commit da transação
        $conn->commit();
        
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        throw new Exception('Erro ao adicionar pontos: ' . $e->getMessage());
    }
}
?>

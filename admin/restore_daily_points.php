<?php
/**
 * Admin: Restaurar Pontos Diários dos Usuários do 11º lugar em diante
 * 
 * Este script restaura os daily_points dos usuários que NÃO estavam no top 10
 * usando o histórico de pontos (points_history) do dia atual.
 * 
 * O bug no reset zerou TODOS os usuários ao invés de apenas o top 10.
 * Este script corrige isso restaurando os pontos dos usuários do 11º em diante.
 * 
 * Endpoint: GET /admin/restore_daily_points.php
 */

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

try {
    $conn = getDbConnection();
    
    // Configurar timezone no MySQL
    $conn->query("SET time_zone = '-03:00'");
    
    $todayDate = date('Y-m-d');
    
    // IDs do Top 10 que foram resetados corretamente (do arquivo de log)
    $top10Ids = [468, 139, 177, 473, 251, 230, 360, 451, 382, 287];
    
    // Buscar todos os pontos ganhos HOJE do points_history
    // Excluindo os usuários do top 10 (que devem permanecer zerados)
    $placeholders = implode(',', array_fill(0, count($top10Ids), '?'));
    $types = str_repeat('i', count($top10Ids));
    
    $stmt = $conn->prepare("
        SELECT 
            user_id,
            SUM(points) as total_points,
            GROUP_CONCAT(description SEPARATOR ' | ') as descriptions
        FROM points_history 
        WHERE DATE(created_at) = ?
        AND user_id NOT IN ($placeholders)
        AND points > 0
        GROUP BY user_id
        ORDER BY total_points DESC
    ");
    
    $params = array_merge([$todayDate], $top10Ids);
    $fullTypes = 's' . $types;
    $stmt->bind_param($fullTypes, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $usersToRestore = [];
    while ($row = $result->fetch_assoc()) {
        $usersToRestore[] = [
            'user_id' => (int)$row['user_id'],
            'total_points' => (int)$row['total_points'],
            'descriptions' => $row['descriptions']
        ];
    }
    $stmt->close();
    
    if (empty($usersToRestore)) {
        echo json_encode([
            'success' => false,
            'message' => 'Nenhum usuário encontrado para restaurar pontos',
            'date' => $todayDate,
            'top10_excluded' => $top10Ids
        ]);
        exit;
    }
    
    // Restaurar os pontos diários de cada usuário
    $restoredUsers = [];
    $totalRestored = 0;
    
    $conn->begin_transaction();
    
    try {
        foreach ($usersToRestore as $userData) {
            $userId = $userData['user_id'];
            $points = $userData['total_points'];
            
            // Buscar nome do usuário
            $stmt = $conn->prepare("SELECT name, email, daily_points FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $userResult = $stmt->get_result();
            $user = $userResult->fetch_assoc();
            $stmt->close();
            
            if ($user) {
                // Atualizar daily_points
                $stmt = $conn->prepare("UPDATE users SET daily_points = ? WHERE id = ?");
                $stmt->bind_param("ii", $points, $userId);
                $stmt->execute();
                $stmt->close();
                
                $restoredUsers[] = [
                    'user_id' => $userId,
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'old_daily_points' => (int)$user['daily_points'],
                    'restored_points' => $points
                ];
                
                $totalRestored++;
            }
        }
        
        $conn->commit();
        
        // Log da operação
        error_log("[ADMIN] RESTAURAÇÃO DE PONTOS: $totalRestored usuários restaurados");
        
        echo json_encode([
            'success' => true,
            'message' => "Pontos diários restaurados com sucesso para $totalRestored usuários",
            'date' => $todayDate,
            'total_restored' => $totalRestored,
            'top10_excluded' => $top10Ids,
            'restored_users' => $restoredUsers,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Restore daily points error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao restaurar pontos: ' . $e->getMessage()
    ]);
}
?>

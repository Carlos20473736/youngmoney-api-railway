<?php
/**
 * Admin: Restaurar Pontos Gerais (total points) de TODOS os Usuários
 * 
 * Este script restaura os pontos gerais (coluna 'points') de todos os usuários
 * usando o histórico completo de pontos (points_history).
 * 
 * O reset zerou os pontos gerais de todos os usuários à meia-noite.
 * Este script recalcula e restaura os pontos baseado no histórico.
 * 
 * Endpoint: GET /admin/restore_total_points.php
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
    
    // Buscar TODOS os pontos do points_history agrupados por usuário
    // Isso inclui TODO o histórico, não apenas de hoje
    $result = $conn->query("
        SELECT 
            user_id,
            SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as total_earned,
            SUM(CASE WHEN points < 0 THEN ABS(points) ELSE 0 END) as total_spent,
            SUM(points) as net_points,
            COUNT(*) as transactions
        FROM points_history 
        GROUP BY user_id
        ORDER BY net_points DESC
    ");
    
    $usersToRestore = [];
    while ($row = $result->fetch_assoc()) {
        $usersToRestore[] = [
            'user_id' => (int)$row['user_id'],
            'total_earned' => (int)$row['total_earned'],
            'total_spent' => (int)$row['total_spent'],
            'net_points' => (int)$row['net_points'],
            'transactions' => (int)$row['transactions']
        ];
    }
    
    if (empty($usersToRestore)) {
        echo json_encode([
            'success' => false,
            'message' => 'Nenhum usuário encontrado no histórico de pontos',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    // Restaurar os pontos de cada usuário
    $restoredUsers = [];
    $totalRestored = 0;
    $totalPointsRestored = 0;
    
    $conn->begin_transaction();
    
    try {
        foreach ($usersToRestore as $userData) {
            $userId = $userData['user_id'];
            $netPoints = max(0, $userData['net_points']); // Não permitir pontos negativos
            
            // Buscar dados atuais do usuário
            $stmt = $conn->prepare("SELECT name, email, points FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $userResult = $stmt->get_result();
            $user = $userResult->fetch_assoc();
            $stmt->close();
            
            if ($user) {
                $oldPoints = (int)$user['points'];
                
                // Atualizar pontos gerais
                $stmt = $conn->prepare("UPDATE users SET points = ? WHERE id = ?");
                $stmt->bind_param("ii", $netPoints, $userId);
                $stmt->execute();
                $stmt->close();
                
                $restoredUsers[] = [
                    'user_id' => $userId,
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'old_points' => $oldPoints,
                    'restored_points' => $netPoints,
                    'total_earned' => $userData['total_earned'],
                    'total_spent' => $userData['total_spent'],
                    'transactions' => $userData['transactions']
                ];
                
                $totalRestored++;
                $totalPointsRestored += $netPoints;
            }
        }
        
        $conn->commit();
        
        // Log da operação
        error_log("[ADMIN] RESTAURAÇÃO DE PONTOS GERAIS: $totalRestored usuários, $totalPointsRestored pontos totais");
        
        echo json_encode([
            'success' => true,
            'message' => "Pontos gerais restaurados com sucesso para $totalRestored usuários",
            'summary' => [
                'total_users_restored' => $totalRestored,
                'total_points_restored' => $totalPointsRestored,
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'restored_users' => array_slice($restoredUsers, 0, 50) // Mostrar apenas os primeiros 50
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Restore total points error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao restaurar pontos: ' . $e->getMessage()
    ]);
}
?>

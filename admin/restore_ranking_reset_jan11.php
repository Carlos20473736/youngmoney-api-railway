<?php
/**
 * Admin: Restaurar Pontos do Reset de 11/01 à Meia-Noite
 * 
 * Este script restaura os pontos diários (daily_points) de TODOS os usuários
 * que foram resetados à meia-noite de 11/01/2026.
 * 
 * Lógica:
 * - Busca todos os pontos ganhos no dia 10/01/2026 (dia anterior ao reset)
 * - Restaura o daily_points de cada usuário baseado no histórico
 * - Inclui TODOS os usuários que tinham pontos, não apenas o top 10
 * 
 * Endpoint: GET /admin/restore_ranking_reset_jan11.php
 * 
 * Parâmetros:
 * - ?confirm=yes - Executa a restauração
 * - Sem parâmetro - Mostra prévia das mudanças
 */

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

$isConfirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

try {
    $conn = getDbConnection();
    
    // Configurar timezone no MySQL
    $conn->query("SET time_zone = '-03:00'");
    
    $currentDatetime = date('Y-m-d H:i:s');
    
    // Data do dia ANTERIOR ao reset (10/01/2026)
    // O reset aconteceu à meia-noite de 11/01, então precisamos dos pontos do dia 10/01
    $resetDate = '2026-01-10';
    
    // ============================================
    // PASSO 1: Buscar todos os pontos ganhos no dia 10/01 (antes do reset)
    // ============================================
    
    $dailyPointsQuery = $conn->query("
        SELECT 
            user_id,
            SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as daily_earned,
            COUNT(*) as transactions
        FROM points_history 
        WHERE DATE(created_at) = '$resetDate'
        GROUP BY user_id
        ORDER BY daily_earned DESC
    ");
    
    $dailyPointsFromHistory = [];
    while ($row = $dailyPointsQuery->fetch_assoc()) {
        $dailyPointsFromHistory[$row['user_id']] = [
            'points' => (int)$row['daily_earned'],
            'transactions' => (int)$row['transactions']
        ];
    }
    
    // ============================================
    // PASSO 2: Buscar estado atual de todos os usuários
    // ============================================
    
    $usersQuery = $conn->query("
        SELECT id, name, email, points, daily_points 
        FROM users 
        ORDER BY id
    ");
    
    $usersToUpdate = [];
    $totalDailyPointsSum = 0;
    
    while ($user = $usersQuery->fetch_assoc()) {
        $userId = (int)$user['id'];
        $currentDailyPoints = (int)$user['daily_points'];
        
        // Verificar se o usuário tinha pontos no dia 10/01
        if (isset($dailyPointsFromHistory[$userId])) {
            $pointsFromJan10 = $dailyPointsFromHistory[$userId]['points'];
            $transactions = $dailyPointsFromHistory[$userId]['transactions'];
            
            // Se o usuário tinha pontos no dia 10/01 e agora tem menos ou zero
            // (foi resetado), restaurar os pontos
            if ($pointsFromJan10 > 0 && $currentDailyPoints < $pointsFromJan10) {
                $usersToUpdate[] = [
                    'user_id' => $userId,
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'current_daily_points' => $currentDailyPoints,
                    'points_from_jan10' => $pointsFromJan10,
                    'diff' => $pointsFromJan10 - $currentDailyPoints,
                    'transactions' => $transactions
                ];
                
                $totalDailyPointsSum += $pointsFromJan10;
            }
        }
    }
    
    // Ordenar por pontos (maior primeiro)
    usort($usersToUpdate, function($a, $b) {
        return $b['points_from_jan10'] - $a['points_from_jan10'];
    });
    
    // ============================================
    // MODO PRÉVIA (sem confirm=yes)
    // ============================================
    
    if (!$isConfirmed) {
        echo json_encode([
            'success' => true,
            'mode' => 'preview',
            'message' => 'Prévia da restauração do reset de 11/01. Adicione ?confirm=yes para executar.',
            'reset_info' => [
                'reset_date' => '2026-01-11 00:00:00',
                'data_source' => 'points_history do dia 2026-01-10',
                'description' => 'Restaura daily_points de TODOS os usuários que foram resetados à meia-noite'
            ],
            'summary' => [
                'users_to_restore' => count($usersToUpdate),
                'total_daily_points' => $totalDailyPointsSum,
                'timestamp' => $currentDatetime
            ],
            'users' => $usersToUpdate,
            'how_to_execute' => '/admin/restore_ranking_reset_jan11.php?confirm=yes'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // ============================================
    // MODO EXECUÇÃO (com confirm=yes)
    // ============================================
    
    if (empty($usersToUpdate)) {
        echo json_encode([
            'success' => true,
            'message' => 'Nenhum usuário precisa de restauração. Todos os pontos já estão corretos.',
            'timestamp' => $currentDatetime
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        $restored = [];
        
        foreach ($usersToUpdate as $userData) {
            $userId = $userData['user_id'];
            $newDailyPoints = $userData['points_from_jan10'];
            
            $stmt = $conn->prepare("
                UPDATE users 
                SET daily_points = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $newDailyPoints, $userId);
            $stmt->execute();
            $stmt->close();
            
            $restored[] = [
                'user_id' => $userId,
                'name' => $userData['name'],
                'email' => $userData['email'],
                'old_daily_points' => $userData['current_daily_points'],
                'restored_daily_points' => $newDailyPoints,
                'diff' => $userData['diff']
            ];
        }
        
        $conn->commit();
        
        // Log da operação
        $count = count($restored);
        error_log("[ADMIN] RESTAURAÇÃO RESET 11/01: $count usuários restaurados em $currentDatetime");
        
        echo json_encode([
            'success' => true,
            'mode' => 'executed',
            'message' => "Restauração do reset de 11/01 concluída! $count usuários tiveram seus pontos diários restaurados.",
            'reset_info' => [
                'reset_date' => '2026-01-11 00:00:00',
                'data_source' => 'points_history do dia 2026-01-10'
            ],
            'summary' => [
                'users_restored' => $count,
                'total_daily_points_restored' => $totalDailyPointsSum,
                'timestamp' => $currentDatetime
            ],
            'restored_users' => $restored
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Restore ranking reset jan11 error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao restaurar pontos: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

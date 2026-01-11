<?php
/**
 * Admin: Restaurar Pontos de TODOS os Usuários
 * 
 * Este script restaura os pontos de TODOS os usuários usando o histórico completo
 * da tabela points_history. Ideal para reverter um reset acidental.
 * 
 * Restaura:
 * - daily_points: Soma de pontos positivos ganhos HOJE
 * - points (total): Soma líquida de TODOS os pontos do histórico
 * 
 * Endpoint: GET /admin/restore_all_users_points.php
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
    
    $todayDate = date('Y-m-d');
    $currentDatetime = date('Y-m-d H:i:s');
    
    // ============================================
    // PASSO 1: Calcular pontos diários (de HOJE) para cada usuário
    // ============================================
    
    $dailyPointsQuery = $conn->query("
        SELECT 
            user_id,
            SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as daily_earned
        FROM points_history 
        WHERE DATE(created_at) = '$todayDate'
        GROUP BY user_id
    ");
    
    $dailyPoints = [];
    while ($row = $dailyPointsQuery->fetch_assoc()) {
        $dailyPoints[$row['user_id']] = (int)$row['daily_earned'];
    }
    
    // ============================================
    // PASSO 2: Calcular pontos totais (histórico completo) para cada usuário
    // ============================================
    
    $totalPointsQuery = $conn->query("
        SELECT 
            user_id,
            SUM(points) as net_points
        FROM points_history 
        GROUP BY user_id
    ");
    
    $totalPoints = [];
    while ($row = $totalPointsQuery->fetch_assoc()) {
        // Não permitir pontos negativos
        $totalPoints[$row['user_id']] = max(0, (int)$row['net_points']);
    }
    
    // ============================================
    // PASSO 3: Buscar estado atual de todos os usuários
    // ============================================
    
    $usersQuery = $conn->query("
        SELECT id, name, email, points, daily_points 
        FROM users 
        ORDER BY id
    ");
    
    $usersToUpdate = [];
    $totalDailyPointsSum = 0;
    $totalPointsSum = 0;
    
    while ($user = $usersQuery->fetch_assoc()) {
        $userId = (int)$user['id'];
        $currentPoints = (int)$user['points'];
        $currentDailyPoints = (int)$user['daily_points'];
        
        $newDailyPoints = $dailyPoints[$userId] ?? 0;
        $newTotalPoints = $totalPoints[$userId] ?? 0;
        
        // Verificar se há mudança
        if ($newDailyPoints != $currentDailyPoints || $newTotalPoints != $currentPoints) {
            $usersToUpdate[] = [
                'user_id' => $userId,
                'name' => $user['name'],
                'email' => $user['email'],
                'daily_points' => [
                    'current' => $currentDailyPoints,
                    'restored' => $newDailyPoints,
                    'diff' => $newDailyPoints - $currentDailyPoints
                ],
                'total_points' => [
                    'current' => $currentPoints,
                    'restored' => $newTotalPoints,
                    'diff' => $newTotalPoints - $currentPoints
                ]
            ];
            
            $totalDailyPointsSum += $newDailyPoints;
            $totalPointsSum += $newTotalPoints;
        }
    }
    
    // ============================================
    // MODO PRÉVIA (sem confirm=yes)
    // ============================================
    
    if (!$isConfirmed) {
        echo json_encode([
            'success' => true,
            'mode' => 'preview',
            'message' => 'Prévia da restauração. Adicione ?confirm=yes para executar.',
            'summary' => [
                'users_to_update' => count($usersToUpdate),
                'total_daily_points' => $totalDailyPointsSum,
                'total_points' => $totalPointsSum,
                'date' => $todayDate,
                'timestamp' => $currentDatetime
            ],
            'users' => array_slice($usersToUpdate, 0, 100),
            'how_to_execute' => '/admin/restore_all_users_points.php?confirm=yes'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // ============================================
    // MODO EXECUÇÃO (com confirm=yes)
    // ============================================
    
    if (empty($usersToUpdate)) {
        echo json_encode([
            'success' => true,
            'message' => 'Nenhum usuário precisa de restauração. Pontos já estão corretos.',
            'timestamp' => $currentDatetime
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        $restored = [];
        
        foreach ($usersToUpdate as $userData) {
            $userId = $userData['user_id'];
            $newDaily = $userData['daily_points']['restored'];
            $newTotal = $userData['total_points']['restored'];
            
            $stmt = $conn->prepare("
                UPDATE users 
                SET daily_points = ?, points = ?
                WHERE id = ?
            ");
            $stmt->bind_param("iii", $newDaily, $newTotal, $userId);
            $stmt->execute();
            $stmt->close();
            
            $restored[] = [
                'user_id' => $userId,
                'name' => $userData['name'],
                'daily_points_restored' => $newDaily,
                'total_points_restored' => $newTotal
            ];
        }
        
        $conn->commit();
        
        // Log da operação
        $count = count($restored);
        error_log("[ADMIN] RESTAURAÇÃO COMPLETA: $count usuários restaurados em $currentDatetime");
        
        echo json_encode([
            'success' => true,
            'mode' => 'executed',
            'message' => "Restauração concluída! $count usuários tiveram seus pontos restaurados.",
            'summary' => [
                'users_restored' => $count,
                'total_daily_points' => $totalDailyPointsSum,
                'total_points' => $totalPointsSum,
                'timestamp' => $currentDatetime
            ],
            'restored_users' => array_slice($restored, 0, 50)
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Restore all users points error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao restaurar pontos: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

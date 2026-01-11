<?php
/**
 * Admin: Reverter Último Reset do Ranking
 * 
 * Este script reverte o último reset do ranking, restaurando:
 * 1. Pontos diários (daily_points) de TODOS os usuários
 * 2. Pontos gerais (points) de TODOS os usuários
 * 
 * Utiliza a tabela points_history para recalcular os pontos de cada usuário.
 * 
 * Endpoint: GET /admin/revert_last_ranking_reset.php
 * 
 * Parâmetros opcionais:
 * - ?confirm=yes - Confirma a execução da reversão
 * - ?preview=yes - Mostra prévia sem executar
 */

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// Verificar se é preview ou execução
$isPreview = isset($_GET['preview']) && $_GET['preview'] === 'yes';
$isConfirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

try {
    $conn = getDbConnection();
    
    // Configurar timezone no MySQL
    $conn->query("SET time_zone = '-03:00'");
    
    $todayDate = date('Y-m-d');
    $currentDatetime = date('Y-m-d H:i:s');
    
    // ============================================
    // PASSO 1: Buscar dados do points_history para restaurar daily_points
    // ============================================
    
    // Buscar todos os pontos ganhos HOJE do points_history
    $dailyPointsResult = $conn->query("
        SELECT 
            user_id,
            SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as points_earned_today,
            COUNT(*) as transactions_today
        FROM points_history 
        WHERE DATE(created_at) = '$todayDate'
        GROUP BY user_id
        ORDER BY points_earned_today DESC
    ");
    
    $dailyPointsToRestore = [];
    while ($row = $dailyPointsResult->fetch_assoc()) {
        $dailyPointsToRestore[$row['user_id']] = [
            'points' => (int)$row['points_earned_today'],
            'transactions' => (int)$row['transactions_today']
        ];
    }
    
    // ============================================
    // PASSO 2: Buscar dados do points_history para restaurar total points
    // ============================================
    
    // Buscar TODOS os pontos do points_history agrupados por usuário (histórico completo)
    $totalPointsResult = $conn->query("
        SELECT 
            user_id,
            SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as total_earned,
            SUM(CASE WHEN points < 0 THEN ABS(points) ELSE 0 END) as total_spent,
            SUM(points) as net_points,
            COUNT(*) as total_transactions
        FROM points_history 
        GROUP BY user_id
        ORDER BY net_points DESC
    ");
    
    $totalPointsToRestore = [];
    while ($row = $totalPointsResult->fetch_assoc()) {
        $totalPointsToRestore[$row['user_id']] = [
            'total_earned' => (int)$row['total_earned'],
            'total_spent' => (int)$row['total_spent'],
            'net_points' => max(0, (int)$row['net_points']), // Não permitir pontos negativos
            'transactions' => (int)$row['total_transactions']
        ];
    }
    
    // ============================================
    // PASSO 3: Buscar estado atual dos usuários
    // ============================================
    
    $usersResult = $conn->query("
        SELECT id, name, email, points, daily_points 
        FROM users 
        ORDER BY id
    ");
    
    $usersData = [];
    while ($row = $usersResult->fetch_assoc()) {
        $usersData[$row['id']] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'current_points' => (int)$row['points'],
            'current_daily_points' => (int)$row['daily_points']
        ];
    }
    
    // ============================================
    // PASSO 4: Calcular mudanças a serem feitas
    // ============================================
    
    $usersToUpdate = [];
    $totalDailyPointsToRestore = 0;
    $totalPointsToRestoreSum = 0;
    
    foreach ($usersData as $userId => $userData) {
        $newDailyPoints = $dailyPointsToRestore[$userId]['points'] ?? 0;
        $newTotalPoints = $totalPointsToRestore[$userId]['net_points'] ?? 0;
        
        // Verificar se há mudança a ser feita
        $dailyPointsChanged = $newDailyPoints != $userData['current_daily_points'];
        $totalPointsChanged = $newTotalPoints != $userData['current_points'];
        
        if ($dailyPointsChanged || $totalPointsChanged) {
            $usersToUpdate[] = [
                'user_id' => $userId,
                'name' => $userData['name'],
                'email' => $userData['email'],
                'old_daily_points' => $userData['current_daily_points'],
                'new_daily_points' => $newDailyPoints,
                'daily_points_diff' => $newDailyPoints - $userData['current_daily_points'],
                'old_total_points' => $userData['current_points'],
                'new_total_points' => $newTotalPoints,
                'total_points_diff' => $newTotalPoints - $userData['current_points'],
                'transactions_today' => $dailyPointsToRestore[$userId]['transactions'] ?? 0,
                'total_transactions' => $totalPointsToRestore[$userId]['transactions'] ?? 0
            ];
            
            $totalDailyPointsToRestore += $newDailyPoints;
            $totalPointsToRestoreSum += $newTotalPoints;
        }
    }
    
    // ============================================
    // MODO PREVIEW - Apenas mostrar o que seria feito
    // ============================================
    
    if ($isPreview || !$isConfirmed) {
        echo json_encode([
            'success' => true,
            'mode' => 'preview',
            'message' => 'Prévia da reversão do reset. Use ?confirm=yes para executar.',
            'summary' => [
                'users_to_update' => count($usersToUpdate),
                'total_daily_points_to_restore' => $totalDailyPointsToRestore,
                'total_points_to_restore' => $totalPointsToRestoreSum,
                'date' => $todayDate,
                'timestamp' => $currentDatetime
            ],
            'users_affected' => array_slice($usersToUpdate, 0, 100), // Mostrar primeiros 100
            'instructions' => [
                'to_execute' => 'Adicione ?confirm=yes à URL para executar a reversão',
                'example' => '/admin/revert_last_ranking_reset.php?confirm=yes'
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // ============================================
    // MODO EXECUÇÃO - Reverter o reset
    // ============================================
    
    if (empty($usersToUpdate)) {
        echo json_encode([
            'success' => false,
            'message' => 'Nenhum usuário encontrado para restaurar pontos',
            'date' => $todayDate,
            'timestamp' => $currentDatetime
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        $restoredUsers = [];
        $totalRestored = 0;
        
        foreach ($usersToUpdate as $userData) {
            $userId = $userData['user_id'];
            $newDailyPoints = $userData['new_daily_points'];
            $newTotalPoints = $userData['new_total_points'];
            
            // Atualizar pontos diários e totais
            $stmt = $conn->prepare("
                UPDATE users 
                SET daily_points = ?, points = ?
                WHERE id = ?
            ");
            $stmt->bind_param("iii", $newDailyPoints, $newTotalPoints, $userId);
            $stmt->execute();
            $stmt->close();
            
            $restoredUsers[] = [
                'user_id' => $userId,
                'name' => $userData['name'],
                'email' => $userData['email'],
                'daily_points' => [
                    'old' => $userData['old_daily_points'],
                    'new' => $newDailyPoints,
                    'diff' => $userData['daily_points_diff']
                ],
                'total_points' => [
                    'old' => $userData['old_total_points'],
                    'new' => $newTotalPoints,
                    'diff' => $userData['total_points_diff']
                ]
            ];
            
            $totalRestored++;
        }
        
        // Commit da transação
        $conn->commit();
        
        // Log da operação
        error_log("[ADMIN] REVERSÃO DO RESET: $totalRestored usuários restaurados");
        
        echo json_encode([
            'success' => true,
            'mode' => 'executed',
            'message' => "Reversão do reset executada com sucesso! $totalRestored usuários restaurados.",
            'summary' => [
                'total_users_restored' => $totalRestored,
                'total_daily_points_restored' => $totalDailyPointsToRestore,
                'total_points_restored' => $totalPointsToRestoreSum,
                'date' => $todayDate,
                'timestamp' => $currentDatetime
            ],
            'restored_users' => array_slice($restoredUsers, 0, 50) // Mostrar primeiros 50
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Revert ranking reset error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao reverter reset: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

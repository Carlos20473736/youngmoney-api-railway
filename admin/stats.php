<?php
/**
 * Admin Stats Endpoint
 * 
 * CORREÇÃO DE TIMEZONE APLICADA:
 * - Define timezone de Brasília
 * - Usa CONVERT_TZ para converter datas UTC para Brasília
 */

// DEFINIR TIMEZONE NO INÍCIO DO ARQUIVO
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // Data de hoje no timezone de Brasília
    $today = date('Y-m-d');
    
    // Total de usuários
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $totalUsers = $stmt->get_result()->fetch_assoc()['total'];
    
    // Pontos distribuídos hoje
    // CORREÇÃO: Usar DATE(CONVERT_TZ()) para converter UTC para Brasília
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(points), 0) as total 
        FROM points_history 
        WHERE DATE(CONVERT_TZ(created_at, '+00:00', '-03:00')) = ?
    ");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $pointsToday = $stmt->get_result()->fetch_assoc()['total'];
    
    // Notificações enviadas (total)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications");
    $stmt->execute();
    $notificationsSent = $stmt->get_result()->fetch_assoc()['total'];
    
    // Saques pendentes
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM withdrawals 
        WHERE status = 'pending'
    ");
    $stmt->execute();
    $pendingWithdrawals = $stmt->get_result()->fetch_assoc()['total'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'totalUsers' => (int)$totalUsers,
            'pointsToday' => (int)$pointsToday,
            'notificationsSent' => (int)$notificationsSent,
            'pendingWithdrawals' => (int)$pendingWithdrawals,
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => 'America/Sao_Paulo'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar estatísticas: ' . $e->getMessage()
    ]);
}
?>

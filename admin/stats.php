<?php
/**
 * Admin Stats Endpoint
 * 
 * CORREÇÃO DE TIMEZONE v2:
 * - Define timezone de Brasília
 * - Removido CONVERT_TZ pois MySQL já está configurado para Brasília (-03:00)
 * - NOW() já insere em horário de Brasília, então DATE(created_at) já é correto
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
    // CORREÇÃO v2: Removido CONVERT_TZ - MySQL já está em Brasília (-03:00)
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(points), 0) as total 
        FROM points_history 
        WHERE DATE(created_at) = ?
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

<?php
/**
 * Reset All Users Progress - MoniTag Missions
 * POST - Reseta contadores diários de TODOS os usuários
 * 
 * CORREÇÃO DE TIMEZONE APLICADA:
 * - Usa CONVERT_TZ para converter datas UTC para Brasília
 */

// DEFINIR TIMEZONE NO INÍCIO DO ARQUIVO
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../database.php';

try {
    $conn = getDbConnection();
    
    // Data de hoje no timezone de Brasília
    $today = date('Y-m-d');
    
    // Deletar TODOS os eventos de hoje
    // CORREÇÃO: Usar DATE(CONVERT_TZ()) para converter UTC para Brasília
    $stmt = $conn->prepare("
        DELETE FROM monetag_events 
        WHERE DATE(CONVERT_TZ(created_at, '+00:00', '-03:00')) = ?
    ");
    
    $stmt->bind_param("s", $today);
    $stmt->execute();
    
    $deleted = $stmt->affected_rows;
    
    // Contar quantos usuários foram afetados (ontem)
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $stmt2 = $conn->prepare("
        SELECT COUNT(DISTINCT user_id) as affected_users
        FROM (
            SELECT user_id FROM monetag_events WHERE DATE(CONVERT_TZ(created_at, '+00:00', '-03:00')) = ?
        ) as yesterday_users
    ");
    
    $stmt2->bind_param("s", $yesterday);
    $stmt2->execute();
    $result = $stmt2->get_result();
    $row = $result->fetch_assoc();
    $affectedUsers = $row['affected_users'] ?? 0;
    
    $stmt->close();
    $stmt2->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'events_deleted' => $deleted,
            'users_affected' => $affectedUsers,
            'message' => 'Progresso diário de todos os usuários resetado com sucesso',
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => 'America/Sao_Paulo'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

<?php
/**
 * Reset All Users Progress - MoniTag Missions
 * POST - Reseta contadores diários de TODOS os usuários
 * 
 * CORREÇÃO DE TIMEZONE v2:
 * - Removido CONVERT_TZ pois MySQL já está configurado para Brasília (-03:00)
 * - NOW() já insere em horário de Brasília, então DATE(created_at) já é correto
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
    // CORREÇÃO v2: Removido CONVERT_TZ - MySQL já está em Brasília (-03:00)
    $stmt = $conn->prepare("
        DELETE FROM monetag_events 
        WHERE DATE(created_at) = ?
    ");
    
    $stmt->bind_param("s", $today);
    $stmt->execute();
    
    $deleted = $stmt->affected_rows;
    
    // Contar quantos usuários foram afetados (ontem)
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $stmt2 = $conn->prepare("
        SELECT COUNT(DISTINCT user_id) as affected_users
        FROM (
            SELECT user_id FROM monetag_events WHERE DATE(created_at) = ?
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

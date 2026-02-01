<?php
/**
 * Reset User Progress - MoniTag Missions
 * POST - Reseta contadores diários de um usuário específico
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
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? null;
    
    if (!$userId) {
        throw new Exception('user_id é obrigatório');
    }
    
    $conn = getDbConnection();
    
    // Data de hoje no timezone de Brasília
    $today = date('Y-m-d');
    
    // Deletar eventos de hoje do usuário
    // CORREÇÃO v2: Removido CONVERT_TZ - MySQL já está em Brasília (-03:00)
    $stmt = $conn->prepare("
        DELETE FROM monetag_events 
        WHERE user_id = ? 
        AND DATE(created_at) = ?
    ");
    
    $stmt->bind_param('is', $userId, $today);
    $stmt->execute();
    
    $deleted = $stmt->affected_rows;
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $userId,
            'events_deleted' => $deleted,
            'message' => 'Progresso diário resetado com sucesso',
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => 'America/Sao_Paulo'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

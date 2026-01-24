<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/auth_helper.php';

// Configurar timezone para Brasília
date_default_timezone_set('America/Sao_Paulo');

try {
    $conn = getDbConnection();
    
    // Configurar timezone no MySQL para Brasília (GMT-3)
    $conn->query("SET time_zone = '-03:00'");
    
    $user = getAuthenticatedUser($conn);
    if (!$user) { sendUnauthorizedError(); }
    
    // Converter de UTC para Brasília adicionando 3 horas
    // O banco está em UTC, então usamos DATE_ADD para adicionar 3 horas
    $stmt = $conn->prepare("
        SELECT id, points, description, created_at,
               DATE_FORMAT(DATE_ADD(created_at, INTERVAL 3 HOUR), '%d/%m/%Y %H:%i') as formatted_date,
               DATE_FORMAT(DATE_ADD(created_at, INTERVAL 3 HOUR), '%H:%i') as time_only,
               DATE_ADD(created_at, INTERVAL 3 HOUR) as date
        FROM points_history 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = [
            'id' => (int)$row['id'],
            'points' => (int)$row['points'],
            'description' => $row['description'],
            'created_at' => $row['date'],
            'date' => $row['date'],
            'formatted_date' => $row['formatted_date'],
            'time_only' => $row['time_only']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    sendSuccess(['history' => $transactions, 'total' => count($transactions)]);
    
} catch (Exception $e) {
    error_log("history/points.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

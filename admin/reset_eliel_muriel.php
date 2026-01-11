<?php
/**
 * Script de Execução Única: Zerar Pontos Diários de Eliel Natividade e Muriel Herrera
 * 
 * Endpoint: GET /admin/reset_eliel_muriel.php
 * 
 * Este script zera os pontos diários (daily_points) dos usuários:
 * - Eliel Natividade
 * - Muriel Herrera
 */

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

try {
    $conn = getDbConnection();
    
    // Usuários a serem zerados
    $usersToReset = [
        'Eliel Natividade',
        'muriel herrera'
    ];
    
    $affectedUsers = [];
    
    // Buscar e zerar pontos de cada usuário
    foreach ($usersToReset as $userName) {
        // Buscar usuário pelo nome (case insensitive)
        $stmt = $conn->prepare("
            SELECT id, name, email, daily_points 
            FROM users 
            WHERE LOWER(name) LIKE LOWER(?)
        ");
        $searchName = "%" . trim($userName) . "%";
        $stmt->bind_param("s", $searchName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $affectedUsers[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'old_daily_points' => $row['daily_points']
            ];
        }
        $stmt->close();
    }
    
    if (empty($affectedUsers)) {
        echo json_encode([
            'success' => false,
            'message' => 'Nenhum usuário encontrado com os nomes: ' . implode(', ', $usersToReset)
        ]);
        exit;
    }
    
    // Zerar pontos diários dos usuários encontrados
    $userIds = array_column($affectedUsers, 'id');
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $types = str_repeat('i', count($userIds));
    
    $stmt = $conn->prepare("
        UPDATE users 
        SET daily_points = 0 
        WHERE id IN ($placeholders)
    ");
    $stmt->bind_param($types, ...$userIds);
    $stmt->execute();
    $rowsAffected = $stmt->affected_rows;
    $stmt->close();
    
    // Log da operação
    error_log("[ADMIN] RESET MANUAL: Pontos diários zerados para Eliel Natividade e Muriel Herrera");
    error_log("[ADMIN] Usuários afetados: " . json_encode(array_column($affectedUsers, 'name')));
    
    echo json_encode([
        'success' => true,
        'message' => 'Pontos diários zerados com sucesso para Eliel Natividade e Muriel Herrera',
        'rows_affected' => $rowsAffected,
        'affected_users' => $affectedUsers,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Reset Eliel/Muriel error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao zerar pontos: ' . $e->getMessage()
    ]);
}
?>

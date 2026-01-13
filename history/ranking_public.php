<?php
/**
 * Ranking History Public Endpoint
 * GET - Salva o ranking atual e retorna o histórico completo
 * ENDPOINT PÚBLICO - Não requer autenticação
 * 
 * Sempre que executado:
 * 1. Salva um snapshot do ranking atual (do primeiro ao último)
 * 2. Retorna a lista completa do histórico
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // Configurar timezone
    date_default_timezone_set('America/Sao_Paulo');
    $now = date('Y-m-d H:i:s');
    
    // Criar tabela de histórico se não existir
    $conn->query("
        CREATE TABLE IF NOT EXISTS ranking_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            snapshot_date DATETIME NOT NULL,
            position INT NOT NULL,
            user_id INT NOT NULL,
            user_name VARCHAR(255) NOT NULL,
            profile_picture VARCHAR(500) DEFAULT '',
            points INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_snapshot_date (snapshot_date),
            INDEX idx_position (position),
            INDEX idx_user_id (user_id),
            INDEX idx_snapshot_position (snapshot_date, position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Criar tabela de cooldowns se não existir (necessária para a query)
    $conn->query("
        CREATE TABLE IF NOT EXISTS ranking_cooldowns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            position INT NOT NULL,
            prize_amount DECIMAL(10,2) NOT NULL,
            cooldown_days INT NOT NULL,
            cooldown_until DATETIME NOT NULL,
            reset_date DATE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_cooldown (user_id, cooldown_until),
            INDEX idx_cooldown_until (cooldown_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // ========================================
    // PASSO 1: Buscar ranking atual COMPLETO (sem limite)
    // ========================================
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.profile_picture, u.daily_points as points
        FROM users u
        LEFT JOIN ranking_cooldowns rc ON u.id = rc.user_id AND rc.cooldown_until > ?
        WHERE u.daily_points > 0 
          AND u.pix_key IS NOT NULL 
          AND u.pix_key != ''
          AND rc.id IS NULL
        ORDER BY u.daily_points DESC, u.created_at ASC
    ");
    $stmt->bind_param("s", $now);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $currentRanking = [];
    $position = 1;
    
    while ($row = $result->fetch_assoc()) {
        $currentRanking[] = [
            'position' => $position++,
            'user_id' => (int)$row['id'],
            'user_name' => $row['name'],
            'profile_picture' => $row['profile_picture'] ?: '',
            'points' => (int)$row['points']
        ];
    }
    $stmt->close();
    
    // ========================================
    // PASSO 2: Salvar snapshot do ranking atual
    // ========================================
    if (count($currentRanking) > 0) {
        $snapshotDate = $now;
        
        $insertStmt = $conn->prepare("
            INSERT INTO ranking_history 
            (snapshot_date, position, user_id, user_name, profile_picture, points)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($currentRanking as $entry) {
            $insertStmt->bind_param(
                "siisis",
                $snapshotDate,
                $entry['position'],
                $entry['user_id'],
                $entry['user_name'],
                $entry['profile_picture'],
                $entry['points']
            );
            $insertStmt->execute();
        }
        $insertStmt->close();
    }
    
    // ========================================
    // PASSO 3: Buscar histórico completo
    // ========================================
    $historyStmt = $conn->prepare("
        SELECT 
            id,
            snapshot_date,
            position,
            user_id,
            user_name,
            profile_picture,
            points,
            DATE_FORMAT(snapshot_date, '%d/%m/%Y %H:%i:%s') as formatted_date
        FROM ranking_history
        ORDER BY snapshot_date DESC, position ASC
    ");
    $historyStmt->execute();
    $historyResult = $historyStmt->get_result();
    
    $history = [];
    $snapshots = [];
    
    while ($row = $historyResult->fetch_assoc()) {
        $snapshotKey = $row['snapshot_date'];
        
        if (!isset($snapshots[$snapshotKey])) {
            $snapshots[$snapshotKey] = [
                'snapshot_date' => $row['snapshot_date'],
                'formatted_date' => $row['formatted_date'],
                'ranking' => []
            ];
        }
        
        $snapshots[$snapshotKey]['ranking'][] = [
            'id' => (int)$row['id'],
            'position' => (int)$row['position'],
            'user_id' => (int)$row['user_id'],
            'user_name' => $row['user_name'],
            'profile_picture' => $row['profile_picture'],
            'points' => (int)$row['points']
        ];
    }
    $historyStmt->close();
    
    // Converter para array indexado
    $history = array_values($snapshots);
    
    $conn->close();
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'message' => 'Ranking salvo e histórico recuperado com sucesso',
            'current_snapshot' => [
                'date' => $now,
                'total_users' => count($currentRanking)
            ],
            'history' => $history,
            'total_snapshots' => count($history)
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("history/ranking_public.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>

<?php
require_once __DIR__ . '/../admin/cors.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../middleware/auto_reset.php';

header('Content-Type: application/json');

// Pontos mínimos para entrar no ranking diário
define('MIN_RANKING_POINTS', 2000000);

try {
    $conn = getDbConnection();
    
    // Verificar e fazer reset automático se necessário
    checkAndResetRanking($conn);
    
    // Buscar ranking ordenado por daily_points (pontos diários)
    // FILTRO: Apenas usuários com >= 2.000.000 pontos aparecem
    $minPoints = MIN_RANKING_POINTS;
    $stmt = $conn->prepare("
        SELECT id, name, daily_points as points
        FROM users 
        WHERE daily_points >= ?
        ORDER BY daily_points DESC 
        LIMIT 100
    ");
    
    $stmt->bind_param("i", $minPoints);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ranking = [];
    while ($row = $result->fetch_assoc()) {
        $ranking[] = $row;
    }
    
    // Adicionar timestamp do servidor para o app
    date_default_timezone_set('America/Sao_Paulo');
    $serverTimestamp = time();
    
    echo json_encode([
        'success' => true,
        'data' => $ranking,
        'server_timestamp' => $serverTimestamp,
        'min_points' => MIN_RANKING_POINTS
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>

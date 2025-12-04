<?php
require_once __DIR__ . '/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
    
    $conn = getDbConnection();
    
    $chartData = [];
    
    // Buscar dados dos últimos N dias
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dateFormatted = date('d/m', strtotime("-$i days"));
        
        // Contar usuários criados até essa data
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM users 
            WHERE DATE(created_at) <= ?
        ");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $users = $stmt->get_result()->fetch_assoc()['total'];
        
        // Somar pontos distribuídos nessa data
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(points), 0) as total 
            FROM points_history 
            WHERE DATE(created_at) = ?
        ");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $points = $stmt->get_result()->fetch_assoc()['total'];
        
        $chartData[] = [
            'date' => $dateFormatted,
            'users' => (int)$users,
            'points' => (int)$points
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $chartData
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar dados do gráfico: ' . $e->getMessage()
    ]);
}
?>

<?php
/**
 * Endpoint Admin para recuperar pontos do Candy não creditados
 * 
 * Este endpoint:
 * 1. Busca todos os registros na tabela game_levels (usuários que jogaram)
 * 2. Verifica quais pontos não foram creditados na tabela points_history
 * 3. Adiciona os pontos faltantes aos usuários
 * 
 * GET /api/v1/admin/fix_candy_points.php - Mostra preview dos pontos a recuperar
 * POST /api/v1/admin/fix_candy_points.php - Executa a recuperação
 */

// Tratamento de erros
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => "PHP Error: $errstr in $errfile:$errline"]);
    exit;
});

try {
    require_once __DIR__ . '/../../../db_config.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Include error: ' . $e->getMessage()]);
    exit;
}

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Conectar ao banco
$conn = getMySQLiConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Buscar usuários que jogaram Candy mas não têm pontos creditados no histórico
// Estratégia: Verificar game_levels e comparar com points_history
$query = "
    SELECT 
        gl.user_id,
        gl.level,
        gl.highest_level,
        gl.last_level_score,
        gl.updated_at,
        u.email,
        u.name,
        u.points as current_points,
        (SELECT COUNT(*) FROM points_history ph WHERE ph.user_id = gl.user_id AND ph.description LIKE '%Candy%') as candy_history_count,
        (SELECT COALESCE(SUM(ph.points), 0) FROM points_history ph WHERE ph.user_id = gl.user_id AND ph.description LIKE '%Candy%') as candy_points_credited
    FROM game_levels gl
    LEFT JOIN users u ON gl.user_id = u.id
    WHERE gl.highest_level > 1 OR gl.last_level_score > 0
    ORDER BY gl.updated_at DESC
";

$result = $conn->query($query);

if (!$result) {
    // Tentar criar a tabela se não existir
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Query failed: ' . $conn->error,
        'hint' => 'A tabela game_levels pode não existir ainda'
    ]);
    exit;
}

$usuariosParaRecuperar = [];
$totalPontosRecuperar = 0;

while ($row = $result->fetch_assoc()) {
    $userId = $row['user_id'];
    $highestLevel = (int)$row['highest_level'];
    $lastScore = (int)$row['last_level_score'];
    $candyPointsCredited = (int)$row['candy_points_credited'];
    
    // Estimar pontos que deveriam ter sido creditados
    // Cada level completado deveria dar pontos
    // Se highest_level > 1, significa que passou de level pelo menos (highest_level - 1) vezes
    $levelsCompletados = max(0, $highestLevel - 1);
    
    // Pontos estimados: último score * número de levels (estimativa conservadora)
    // Ou usar o last_level_score como base
    $pontosEstimados = $lastScore > 0 ? $lastScore : ($levelsCompletados * 50); // 50 pontos por level como fallback
    
    // Se não tem nenhum registro de Candy no histórico mas jogou, precisa recuperar
    if ($candyPointsCredited == 0 && ($highestLevel > 1 || $lastScore > 0)) {
        $pontosRecuperar = $pontosEstimados > 0 ? $pontosEstimados : 100; // Mínimo 100 pontos
        
        $usuariosParaRecuperar[] = [
            'user_id' => $userId,
            'name' => $row['name'] ?? 'N/A',
            'email' => $row['email'] ?? 'N/A',
            'highest_level' => $highestLevel,
            'last_level_score' => $lastScore,
            'candy_points_credited' => $candyPointsCredited,
            'points_to_recover' => $pontosRecuperar,
            'current_points' => (int)$row['current_points'],
            'updated_at' => $row['updated_at']
        ];
        
        $totalPontosRecuperar += $pontosRecuperar;
    }
}

if ($method === 'GET') {
    // Apenas mostrar preview
    echo json_encode([
        'status' => 'success',
        'action' => 'preview',
        'message' => 'Preview dos pontos a recuperar. Use POST para executar.',
        'total_users' => count($usuariosParaRecuperar),
        'total_points_to_recover' => $totalPontosRecuperar,
        'users' => $usuariosParaRecuperar
    ], JSON_PRETTY_PRINT);
    
} elseif ($method === 'POST') {
    // Executar recuperação
    $recuperados = [];
    $erros = [];
    
    foreach ($usuariosParaRecuperar as $usuario) {
        $userId = $usuario['user_id'];
        $pontosRecuperar = $usuario['points_to_recover'];
        
        try {
            // Adicionar pontos ao usuário
            $stmt = $conn->prepare("UPDATE users SET daily_points = daily_points + ?, points = points + ? WHERE id = ?");
            $stmt->bind_param("iii", $pontosRecuperar, $pontosRecuperar, $userId);
            $stmt->execute();
            $stmt->close();
            
            // Registrar no histórico
            $description = "Candy Crush - Pontos recuperados (bug fix): " . $pontosRecuperar . " pontos";
            $stmt = $conn->prepare("INSERT INTO points_history (user_id, points, description, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iis", $userId, $pontosRecuperar, $description);
            $stmt->execute();
            $stmt->close();
            
            $recuperados[] = [
                'user_id' => $userId,
                'name' => $usuario['name'],
                'email' => $usuario['email'],
                'points_recovered' => $pontosRecuperar
            ];
            
        } catch (Exception $e) {
            $erros[] = [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ];
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'action' => 'executed',
        'message' => 'Recuperação de pontos executada!',
        'total_users_recovered' => count($recuperados),
        'total_points_recovered' => $totalPontosRecuperar,
        'recovered_users' => $recuperados,
        'errors' => $erros
    ], JSON_PRETTY_PRINT);
    
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}

$conn->close();
?>

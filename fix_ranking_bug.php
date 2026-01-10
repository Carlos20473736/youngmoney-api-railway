<?php
/**
 * Script para corrigir os pontos dos usuários afetados pelo bug de level
 * 
 * Usuários a corrigir:
 * - marcosfernandes1058@gmail.com -> 302.040 pontos
 * - palmeirinhas12@gmail.com -> 117.998 pontos
 * 
 * Executar via: GET /fix_ranking_bug.php?token=fix_ranking_bug_2026
 */

header('Content-Type: application/json');

// Verificar token de segurança
$token = $_GET['token'] ?? '';
if ($token !== 'fix_ranking_bug_2026') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token inválido']);
    exit;
}

require_once __DIR__ . '/db_config.php';

$conn = getMySQLiConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Lista de usuários a corrigir
$usersToFix = [
    ['email' => 'marcosfernandes1058@gmail.com', 'new_points' => 302040],
    ['email' => 'palmeirinhas12@gmail.com', 'new_points' => 117998]
];

$results = [];

foreach ($usersToFix as $userFix) {
    $email = $userFix['email'];
    $newPoints = $userFix['new_points'];
    
    // Buscar usuário
    $stmt = $conn->prepare("SELECT id, name, email, points, daily_points FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $results[] = [
            'email' => $email,
            'success' => false,
            'error' => 'Usuário não encontrado'
        ];
        continue;
    }
    
    $user = $result->fetch_assoc();
    $userId = $user['id'];
    $oldPoints = $user['points'];
    $oldDailyPoints = $user['daily_points'];
    $stmt->close();
    
    // Atualizar pontos (tanto points quanto daily_points)
    $stmt = $conn->prepare("UPDATE users SET points = ?, daily_points = ? WHERE id = ?");
    $stmt->bind_param("iii", $newPoints, $newPoints, $userId);
    $stmt->execute();
    $stmt->close();
    
    // Registrar no histórico
    $description = "Correção de bug de level - Ajuste de " . number_format($oldDailyPoints, 0, ',', '.') . " para " . number_format($newPoints, 0, ',', '.') . " pontos";
    $pointsDiff = $newPoints - $oldPoints;
    $stmt = $conn->prepare("INSERT INTO points_history (user_id, points, description, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $userId, $pointsDiff, $description);
    $stmt->execute();
    $stmt->close();
    
    $results[] = [
        'email' => $email,
        'name' => $user['name'],
        'success' => true,
        'old_points' => $oldPoints,
        'old_daily_points' => $oldDailyPoints,
        'new_points' => $newPoints,
        'new_daily_points' => $newPoints
    ];
}

echo json_encode([
    'success' => true,
    'message' => 'Correção de pontos executada!',
    'timestamp' => date('Y-m-d H:i:s'),
    'users_fixed' => $results
], JSON_PRETTY_PRINT);

$conn->close();
?>

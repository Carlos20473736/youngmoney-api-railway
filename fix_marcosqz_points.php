<?php
/**
 * Script para corrigir os pontos do usuário marcosqz
 * 
 * O bug de progressão de level causou pontos inflados.
 * Este script ajusta os pontos para o valor correto: 317.200
 * 
 * Executar via: GET /fix_marcosqz_points.php?token=fix_ranking_bug_2026
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

// Buscar usuário marcosqz
$stmt = $conn->prepare("SELECT id, name, email, points, daily_points FROM users WHERE name LIKE '%marcosqz%' OR email LIKE '%marcosqz%'");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Usuário marcosqz não encontrado']);
    exit;
}

$user = $result->fetch_assoc();
$userId = $user['id'];
$oldPoints = $user['points'];
$oldDailyPoints = $user['daily_points'];

// Novo valor de pontos
$newPoints = 317200;
$newDailyPoints = 317200;

// Atualizar pontos
$stmt = $conn->prepare("UPDATE users SET points = ?, daily_points = ? WHERE id = ?");
$stmt->bind_param("iii", $newPoints, $newDailyPoints, $userId);
$stmt->execute();

// Registrar no histórico
$description = "Correção de bug - Ajuste de pontos de " . number_format($oldDailyPoints, 0, ',', '.') . " para " . number_format($newDailyPoints, 0, ',', '.');
$pointsDiff = $newPoints - $oldPoints;
$stmt = $conn->prepare("INSERT INTO points_history (user_id, points, description, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iis", $userId, $pointsDiff, $description);
$stmt->execute();

echo json_encode([
    'success' => true,
    'message' => 'Pontos corrigidos com sucesso!',
    'data' => [
        'user_id' => $userId,
        'name' => $user['name'],
        'email' => $user['email'],
        'old_points' => $oldPoints,
        'old_daily_points' => $oldDailyPoints,
        'new_points' => $newPoints,
        'new_daily_points' => $newDailyPoints
    ]
]);

$conn->close();
?>

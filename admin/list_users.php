<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    // Conectar ao banco
    global $pdo;
    if (!isset($pdo)) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASSWORD,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    
    // Buscar primeiros 10 usuários
    $stmt = $pdo->query("
        SELECT id, name, email, referral_code, created_at
        FROM users
        ORDER BY id ASC
        LIMIT 10
    ");
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => count($users)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

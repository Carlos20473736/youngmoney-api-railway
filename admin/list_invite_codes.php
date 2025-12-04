<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Conectar ao banco
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Buscar todos os códigos de convite com contagem de convidados
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.referral_code,
            u.created_at,
            COUNT(DISTINCT r.referred_user_id) as invited_count
        FROM users u
        LEFT JOIN referrals r ON u.id = r.referrer_user_id
        WHERE u.referral_code IS NOT NULL
        GROUP BY u.id, u.name, u.email, u.referral_code, u.created_at
        ORDER BY invited_count DESC, u.created_at DESC
    ");
    
    $codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'codes' => $codes,
        'total' => count($codes)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar códigos de convite',
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

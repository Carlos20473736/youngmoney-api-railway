<?php
error_reporting(0);


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Conectar ao banco de dados usando PDO
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME') ?: 'defaultdb';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_SSL_CA => true,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ]);
    
    $userId = null;
    
    // Pegar user_id da URL ou POST body
    if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
        $userId = (int)$_GET['user_id'];
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['user_id']) && !empty($input['user_id'])) {
            $userId = (int)$input['user_id'];
        }
    }
    
    // Se não conseguiu pegar user_id
    if (!$userId) {
        echo json_encode([
            'status' => 'error',
            'message' => 'user_id não fornecido. Use ?user_id=123 na URL'
        ]);
        exit;
    }
    
    // Verificar se usuário existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userExists) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Usuário não encontrado'
        ]);
        exit;
    }
    
    // Buscar último reset datetime
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'last_reset_datetime'");
    $stmt->execute();
    $lastResetRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $lastResetDatetime = $lastResetRow ? $lastResetRow['setting_value'] : '1970-01-01 00:00:00';
    
    // GET - Verificar status do check-in
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Buscar último check-in do usuário
        $stmt = $pdo->prepare("
            SELECT created_at 
            FROM daily_checkin 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $lastCheckin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Contar total de check-ins (histórico completo)
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM daily_checkin WHERE user_id = ?");
        $stmt->execute([$userId]);
        $totalRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalCheckins = (int)$totalRow['total'];
        
        // Verificar se pode fazer check-in
        // Pode se: não tem check-in HOJE (CURDATE())
        $canCheckin = true;
        $lastCheckinDate = null;
        
        if ($lastCheckin) {
            $lastCheckinDate = date('Y-m-d', strtotime($lastCheckin['created_at']));
            $today = date('Y-m-d');
            
            // Se o último check-in foi HOJE, não pode fazer check-in
            if ($lastCheckinDate === $today) {
                $canCheckin = false;
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'can_checkin' => $canCheckin,
            'last_checkin' => $lastCheckinDate,
            'total_checkins' => $totalCheckins,
            'last_reset_datetime' => $lastResetDatetime,
            'message' => $canCheckin ? 'Você pode fazer check-in!' : 'Você já fez check-in hoje!'
        ]);
        exit;
    }
    
    // POST - Fazer check-in
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verificar se já fez check-in HOJE
        $stmt = $pdo->prepare("
            SELECT id 
            FROM daily_checkin 
            WHERE user_id = ? 
            AND DATE(created_at) = CURDATE()
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $recentCheckin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($recentCheckin) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Você já fez check-in hoje! Volte amanhã.'
            ]);
            exit;
        }
        
        // Fazer check-in
        $pointsEarned = rand(500, 5000); // Pontos aleatórios entre 500 e 5000
        
        // Inserir registro de check-in (ou atualizar se já existe)
        $stmt = $pdo->prepare("
            INSERT INTO daily_checkin (user_id, points_reward, checkin_date, created_at) 
            VALUES (?, ?, CURDATE(), NOW())
            ON DUPLICATE KEY UPDATE 
                points_reward = VALUES(points_reward),
                created_at = NOW()
        ");
        $stmt->execute([$userId, $pointsEarned]);
        
        // Atualizar pontos do usuário (total E daily_points para ranking)
        $stmt = $pdo->prepare("UPDATE users SET points = points + ?, daily_points = daily_points + ? WHERE id = ?");
        $stmt->execute([$pointsEarned, $pointsEarned, $userId]);
        
        // Registrar no histórico de pontos
        $description = "Check-in Diário - Ganhou {$pointsEarned} pontos";
        $stmt = $pdo->prepare("
            INSERT INTO points_history (user_id, points, description, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $pointsEarned, $description]);
        
        // Contar total de check-ins
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM daily_checkin WHERE user_id = ?");
        $stmt->execute([$userId]);
        $totalRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalCheckins = (int)$totalRow['total'];
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Check-in realizado com sucesso!',
            'points_earned' => $pointsEarned,
            'total_checkins' => $totalCheckins
        ]);
        exit;
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Método não permitido'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao processar check-in: ' . $e->getMessage()
    ]);
}

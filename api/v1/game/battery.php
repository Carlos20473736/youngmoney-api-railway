<?php
/**
 * Battery/Energy System API
 * Gerencia a bateria do usuário para o jogo Candy Crush
 * 
 * Funcionalidades:
 * - GET: Retorna a bateria atual do usuário
 * - POST action=use: Desconta 1% da bateria
 * - A bateria reseta para 100% no dia seguinte
 */

// Capturar erros
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("[BATTERY] PHP Error: $errstr in $errfile:$errline");
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

error_log("[BATTERY] Request started - Method: " . $_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../database.php';

try {
    $conn = getDbConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Obter token de autenticação
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($authHeader)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authorization header required']);
    exit;
}

// Extrair token
$token = str_replace('Bearer ', '', $authHeader);

// Buscar usuário pelo token
$stmt = $conn->prepare("SELECT id, email FROM users WHERE token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
    exit;
}

$userId = $user['id'];
error_log("[BATTERY] User ID: $userId");

// Criar tabela de bateria se não existir
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS user_battery (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            battery_percent INT DEFAULT 100,
            last_reset_date DATE NOT NULL,
            last_used_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    error_log("[BATTERY] Table check/create OK");
} catch (Exception $e) {
    error_log("[BATTERY] Table creation error: " . $e->getMessage());
    // Tabela pode já existir, continuar
}

// Obter data atual (timezone Brasil)
date_default_timezone_set('America/Sao_Paulo');
$today = date('Y-m-d');

// Verificar se usuário tem registro de bateria
$stmt = $conn->prepare("SELECT * FROM user_battery WHERE user_id = ?");
$stmt->execute([$userId]);
$batteryRecord = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$batteryRecord) {
    // Criar registro inicial com 100%
    $stmt = $conn->prepare("INSERT INTO user_battery (user_id, battery_percent, last_reset_date) VALUES (?, 100, ?)");
    $stmt->execute([$userId, $today]);
    
    $batteryRecord = [
        'battery_percent' => 100,
        'last_reset_date' => $today
    ];
}

// Verificar se precisa resetar a bateria (novo dia)
if ($batteryRecord['last_reset_date'] !== $today) {
    // Resetar bateria para 100%
    $stmt = $conn->prepare("UPDATE user_battery SET battery_percent = 100, last_reset_date = ? WHERE user_id = ?");
    $stmt->execute([$today, $userId]);
    
    $batteryRecord['battery_percent'] = 100;
    $batteryRecord['last_reset_date'] = $today;
    
    error_log("[BATTERY] Reset battery for user $userId - new day");
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Retornar bateria atual
    echo json_encode([
        'status' => 'success',
        'battery' => (int)$batteryRecord['battery_percent'],
        'can_play' => $batteryRecord['battery_percent'] > 0,
        'reset_date' => $batteryRecord['last_reset_date'],
        'next_reset' => date('Y-m-d', strtotime('+1 day'))
    ]);
    exit;
}

if ($method === 'POST') {
    // Obter body da requisição
    $rawBody = $GLOBALS['_SECURE_REQUEST_BODY'] ?? file_get_contents('php://input');
    $data = json_decode($rawBody, true);
    
    $action = $data['action'] ?? '';
    
    if ($action === 'use') {
        // Descontar 1% da bateria
        $currentBattery = (int)$batteryRecord['battery_percent'];
        
        if ($currentBattery <= 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Battery depleted',
                'battery' => 0,
                'can_play' => false,
                'next_reset' => date('Y-m-d', strtotime('+1 day'))
            ]);
            exit;
        }
        
        $newBattery = max(0, $currentBattery - 1);
        
        $stmt = $conn->prepare("UPDATE user_battery SET battery_percent = ?, last_used_at = NOW() WHERE user_id = ?");
        $stmt->execute([$newBattery, $userId]);
        
        error_log("[BATTERY] User $userId: $currentBattery% -> $newBattery%");
        
        echo json_encode([
            'status' => 'success',
            'battery' => $newBattery,
            'can_play' => $newBattery > 0,
            'message' => $newBattery > 0 ? 'Battery used' : 'Battery depleted'
        ]);
        exit;
    }
    
    if ($action === 'reset') {
        // Reset manual (apenas para admin/teste)
        $stmt = $conn->prepare("UPDATE user_battery SET battery_percent = 100, last_reset_date = ? WHERE user_id = ?");
        $stmt->execute([$today, $userId]);
        
        echo json_encode([
            'status' => 'success',
            'battery' => 100,
            'can_play' => true,
            'message' => 'Battery reset to 100%'
        ]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action. Use: use, reset']);
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);

} catch (Exception $e) {
    error_log("[BATTERY] Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal error: ' . $e->getMessage()]);
}

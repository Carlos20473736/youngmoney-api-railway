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

// Incluir configurações do banco de dados (mesmo que abilities.php)
require_once __DIR__ . '/../../../db_config.php';
require_once __DIR__ . '/../../../includes/auth_helper.php';
require_once __DIR__ . '/../middleware/MaintenanceCheck.php';

// Headers CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-ID');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

error_log("[BATTERY] Request started - Method: " . $_SERVER['REQUEST_METHOD']);

// Conectar ao banco de dados usando a função helper (mesmo que abilities.php)
try {
    $conn = getMySQLiConnection();
    error_log("[BATTERY] Database connection OK");
} catch (Exception $e) {
    error_log("[BATTERY] Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Criar tabela se não existir
$createTableSQL = "CREATE TABLE IF NOT EXISTS user_battery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    battery_percent INT NOT NULL DEFAULT 100,
    last_reset_date DATE NOT NULL,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($createTableSQL)) {
    error_log("[BATTERY] Table creation error: " . $conn->error);
    // Continuar mesmo se falhar (tabela pode já existir)
}

// Obter usuário autenticado (mesmo que abilities.php)
$user = getAuthenticatedUser($conn);

if (!$user) {
    error_log("[BATTERY] User not authenticated");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized - Invalid or missing token']);
    exit;
}

$userId = $user['id'];
error_log("[BATTERY] User ID: $userId");

// ========================================
// VERIFICAÇÃO DE MANUTENÇÃO E VERSÃO
// ========================================
$method = $_SERVER['REQUEST_METHOD'];
$requestData = ($method === 'POST') 
    ? json_decode(file_get_contents('php://input'), true) ?? []
    : $_GET;
$userEmail = $user['email'] ?? $requestData['email'] ?? null;
$appVersion = $requestData['app_version'] ?? $_SERVER['HTTP_X_APP_VERSION'] ?? null;
checkMaintenanceAndVersion($conn, $userEmail, $appVersion);
// ========================================

// Obter data atual (timezone Brasil)
date_default_timezone_set('America/Sao_Paulo');
$today = date('Y-m-d');

// Verificar se usuário tem registro de bateria
$stmt = $conn->prepare("SELECT * FROM user_battery WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$batteryRecord = $result->fetch_assoc();
$stmt->close();

if (!$batteryRecord) {
    // Criar registro inicial com 100%
    $stmt = $conn->prepare("INSERT INTO user_battery (user_id, battery_percent, last_reset_date) VALUES (?, 100, ?)");
    $stmt->bind_param("is", $userId, $today);
    $stmt->execute();
    $stmt->close();
    
    $batteryRecord = [
        'battery_percent' => 100,
        'last_reset_date' => $today
    ];
    error_log("[BATTERY] Created new battery record for user $userId");
}

// Verificar se precisa resetar a bateria (novo dia)
if ($batteryRecord['last_reset_date'] !== $today) {
    // Resetar bateria para 100%
    $stmt = $conn->prepare("UPDATE user_battery SET battery_percent = 100, last_reset_date = ? WHERE user_id = ?");
    $stmt->bind_param("si", $today, $userId);
    $stmt->execute();
    $stmt->close();
    
    $batteryRecord['battery_percent'] = 100;
    $batteryRecord['last_reset_date'] = $today;
    
    error_log("[BATTERY] Reset battery for user $userId - new day");
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Retornar bateria atual
    error_log("[BATTERY] GET - Returning battery: " . $batteryRecord['battery_percent'] . "%");
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
    
    error_log("[BATTERY] POST - Raw body: " . substr($rawBody, 0, 200));
    
    $action = $data['action'] ?? '';
    
    if ($action === 'use') {
        // Descontar 1% da bateria
        $currentBattery = (int)$batteryRecord['battery_percent'];
        
        if ($currentBattery <= 0) {
            error_log("[BATTERY] Battery depleted for user $userId");
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
        $stmt->bind_param("ii", $newBattery, $userId);
        $stmt->execute();
        $stmt->close();
        
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
        $stmt = $conn->prepare("UPDATE user_battery SET battery_percent = 300, last_reset_date = ? WHERE user_id = ?");
        $stmt->bind_param("si", $today, $userId);
        $stmt->execute();
        $stmt->close();
        
        error_log("[BATTERY] Battery reset for user $userId");
        
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

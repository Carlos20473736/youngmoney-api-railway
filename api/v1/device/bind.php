<?php
/**
 * API: Vincular Dispositivo à Conta
 * 
 * Endpoint: POST /api/v1/device/bind.php
 * 
 * Vincula um dispositivo à conta do usuário autenticado.
 * Deve ser chamado APÓS login bem-sucedido.
 * 
 * REQUER AUTENTICAÇÃO (Bearer Token)
 * 
 * Request Body:
 * {
 *   "device_id": "hash_unico_do_dispositivo",
 *   "device_info": "{json_com_informacoes_do_dispositivo}",
 *   "action": "bind"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Dispositivo vinculado com sucesso"
 * }
 */

// Suprimir warnings e notices do PHP
error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Needs-XReq');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

// Carregar configuração
require_once __DIR__ . '/../../../database.php';
require_once __DIR__ . '/../../../includes/auth_helper.php';

try {
    // Conectar ao banco
    $conn = getDbConnection();
    
    // Verificar autenticação
    $user = getAuthenticatedUser($conn);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autorizado']);
        exit;
    }
    
    $user_id = $user['id'];
    
    // Ler body da requisição (suporta túnel criptografado)
    $rawBody = isset($GLOBALS['_SECURE_REQUEST_BODY']) ? $GLOBALS['_SECURE_REQUEST_BODY'] : file_get_contents('php://input');
    $input = json_decode($rawBody, true);
    
    error_log("[DEVICE_BIND] Raw body: " . substr($rawBody, 0, 200));
    
    if (!$input || !isset($input['device_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'device_id é obrigatório']);
        exit;
    }
    
    $device_id = trim($input['device_id']);
    $device_info = isset($input['device_info']) ? $input['device_info'] : '{}';
    
    // Validar device_id
    if (strlen($device_id) < 32) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'device_id inválido']);
        exit;
    }
    
    // Parsear device_info para extrair dados
    $device_data = json_decode($device_info, true);
    $android_id = $device_data['android_id'] ?? null;
    $model = $device_data['model'] ?? null;
    $manufacturer = $device_data['manufacturer'] ?? null;
    $android_version = $device_data['android_version'] ?? null;
    $fingerprint = $device_data['fingerprint'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Verificar se dispositivo já está vinculado a OUTRA conta
    $checkStmt = $conn->prepare("
        SELECT user_id, id FROM device_bindings 
        WHERE device_id = ? 
        AND is_active = 1
        LIMIT 1
    ");
    $checkStmt->bind_param("s", $device_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $existing = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    if ($existing) {
        if ($existing['user_id'] == $user_id) {
            // Já vinculado à mesma conta - apenas atualizar info
            $updateStmt = $conn->prepare("
                UPDATE device_bindings 
                SET device_info = ?,
                    android_id = ?,
                    model = ?,
                    manufacturer = ?,
                    android_version = ?,
                    fingerprint = ?,
                    last_seen = NOW(),
                    ip_address = ?
                WHERE id = ?
            ");
            
            $updateStmt->bind_param("sssssssi", 
                $device_info, $android_id, $model, $manufacturer, 
                $android_version, $fingerprint, $ip, $existing['id']
            );
            $updateStmt->execute();
            $updateStmt->close();
            
            // Registrar acesso
            logDeviceAccess($conn, $device_id, $user_id, 'login_existing', $device_info, $ip);
            
            echo json_encode([
                'success' => true,
                'message' => 'Dispositivo atualizado'
            ]);
        } else {
            // Vinculado a outra conta - BLOQUEAR
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Dispositivo já vinculado a outra conta'
            ]);
        }
    } else {
        // Novo dispositivo - vincular
        $insertStmt = $conn->prepare("
            INSERT INTO device_bindings 
            (user_id, device_id, device_info, android_id, model, manufacturer, 
             android_version, fingerprint, ip_address, is_active, created_at, last_seen)
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        
        $insertStmt->bind_param("issssssss", 
            $user_id, $device_id, $device_info, $android_id, $model, 
            $manufacturer, $android_version, $fingerprint, $ip
        );
        $insertStmt->execute();
        $insertStmt->close();
        
        // Registrar acesso
        logDeviceAccess($conn, $device_id, $user_id, 'first_bind', $device_info, $ip);
        
        echo json_encode([
            'success' => true,
            'message' => 'Dispositivo vinculado com sucesso'
        ]);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Device bind error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao vincular dispositivo']);
}

/**
 * Registra log de acesso do dispositivo
 */
function logDeviceAccess($conn, $device_id, $user_id, $action, $device_info, $ip) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO device_access_logs 
            (device_id, user_id, action, device_info, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param("sisss", $device_id, $user_id, $action, $device_info, $ip);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Failed to log device access: " . $e->getMessage());
    }
}

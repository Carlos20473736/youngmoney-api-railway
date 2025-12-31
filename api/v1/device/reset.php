<?php
/**
 * API: Reset de Vinculação de Dispositivo
 * 
 * Endpoint: POST /api/v1/device/reset.php
 * 
 * Permite que um usuário autenticado desvincule um dispositivo
 * que estava vinculado a outra conta, permitindo usar o dispositivo
 * com a nova conta.
 * 
 * REQUER AUTENTICAÇÃO (Bearer Token)
 * 
 * Request Body:
 * {
 *   "device_id": "hash_unico_do_dispositivo",
 *   "device_info": "{json_com_informacoes_do_dispositivo}",
 *   "action": "reset"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Dispositivo desvinculado e vinculado à nova conta"
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
    $user_email = $user['email'];
    
    // Ler body da requisição (suporta túnel criptografado)
    $rawBody = isset($GLOBALS['_SECURE_REQUEST_BODY']) ? $GLOBALS['_SECURE_REQUEST_BODY'] : file_get_contents('php://input');
    $input = json_decode($rawBody, true);
    
    error_log("[DEVICE_RESET] User: $user_email, Raw body: " . substr($rawBody, 0, 200));
    
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
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // 1. Desativar vinculação anterior (se existir)
        $deactivateStmt = $conn->prepare("
            UPDATE device_bindings 
            SET is_active = 0, 
                deactivated_at = NOW(),
                deactivated_reason = 'reset_by_new_user'
            WHERE device_id = ? 
            AND is_active = 1
        ");
        $deactivateStmt->bind_param("s", $device_id);
        $deactivateStmt->execute();
        $deactivatedCount = $deactivateStmt->affected_rows;
        $deactivateStmt->close();
        
        error_log("[DEVICE_RESET] Vinculações desativadas: $deactivatedCount");
        
        // 2. Verificar se já existe vinculação inativa para este usuário
        $checkStmt = $conn->prepare("
            SELECT id FROM device_bindings 
            WHERE device_id = ? AND user_id = ?
            LIMIT 1
        ");
        $checkStmt->bind_param("si", $device_id, $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $existingForUser = $checkResult->fetch_assoc();
        $checkStmt->close();
        
        if ($existingForUser) {
            // Reativar vinculação existente
            $reactivateStmt = $conn->prepare("
                UPDATE device_bindings 
                SET is_active = 1,
                    device_info = ?,
                    android_id = ?,
                    model = ?,
                    manufacturer = ?,
                    android_version = ?,
                    fingerprint = ?,
                    ip_address = ?,
                    last_seen = NOW(),
                    deactivated_at = NULL,
                    deactivated_reason = NULL
                WHERE id = ?
            ");
            $reactivateStmt->bind_param("sssssssi", 
                $device_info, $android_id, $model, $manufacturer, 
                $android_version, $fingerprint, $ip, $existingForUser['id']
            );
            $reactivateStmt->execute();
            $reactivateStmt->close();
            
            error_log("[DEVICE_RESET] Vinculação reativada para usuário $user_id");
        } else {
            // Criar nova vinculação
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
            
            error_log("[DEVICE_RESET] Nova vinculação criada para usuário $user_id");
        }
        
        // 3. Registrar log de acesso
        $logStmt = $conn->prepare("
            INSERT INTO device_access_logs 
            (device_id, user_id, action, device_info, ip_address, created_at)
            VALUES (?, ?, 'reset_bind', ?, ?, NOW())
        ");
        $logStmt->bind_param("siss", $device_id, $user_id, $device_info, $ip);
        $logStmt->execute();
        $logStmt->close();
        
        // Commit da transação
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Dispositivo desvinculado e vinculado à nova conta',
            'previous_bindings_deactivated' => $deactivatedCount
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Device reset error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao resetar dispositivo']);
}

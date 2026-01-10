<?php
/**
 * API: Verificar Vinculação de Dispositivo
 * 
 * Endpoint: POST /api/v1/device/check.php
 * 
 * Verifica se um dispositivo já está vinculado a uma conta existente.
 * Deve ser chamado ANTES do login para impedir múltiplas contas por dispositivo.
 * 
 * TAMBÉM VERIFICA MODO DE MANUTENÇÃO (com exceção para admins)
 * 
 * NÃO REQUER AUTENTICAÇÃO
 * 
 * Request Body:
 * {
 *   "device_id": "hash_unico_do_dispositivo",
 *   "device_info": "{json_com_informacoes_do_dispositivo}",
 *   "action": "check",
 *   "email": "email@exemplo.com"  // Opcional - para verificar se é admin
 * }
 * 
 * Response (normal):
 * {
 *   "success": true,
 *   "blocked": false,           // true se dispositivo já vinculado a outra conta
 *   "existing_email": "",       // email da conta existente (se bloqueado)
 *   "message": "Dispositivo liberado"
 * }
 * 
 * Response (manutenção):
 * {
 *   "success": false,
 *   "maintenance": true,
 *   "maintenance_message": "Servidor em manutenção...",
 *   "message": "Servidor em manutenção"
 * }
 */

// Suprimir warnings e notices do PHP
error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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

// Lista de emails de administradores que podem logar durante manutenção
$ADMIN_EMAILS = [
    'soltacartatigri@gmail.com',
    'muriel25herrera@gmail.com'
];

// Carregar configuração do banco
require_once __DIR__ . '/../../../database.php';

try {
    // Ler body da requisição (suporta túnel criptografado)
    $rawBody = isset($GLOBALS['_SECURE_REQUEST_BODY']) ? $GLOBALS['_SECURE_REQUEST_BODY'] : file_get_contents('php://input');
    $input = json_decode($rawBody, true);
    
    // Verificar se é admin (email pode vir no body)
    $requestEmail = isset($input['email']) ? strtolower(trim($input['email'])) : '';
    $isAdmin = in_array($requestEmail, array_map('strtolower', $ADMIN_EMAILS));
    
    // Conectar ao banco
    $conn = getDbConnection();
    
    // ========================================
    // VERIFICAR MODO DE MANUTENÇÃO
    // (Admins podem passar mesmo em manutenção)
    // ========================================
    if (!$isAdmin) {
        try {
            // Criar tabela de configurações do sistema se não existir
            $conn->query("
                CREATE TABLE IF NOT EXISTS system_settings (
                    setting_key VARCHAR(100) PRIMARY KEY,
                    setting_value TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Verificar se está em modo de manutenção
            $maintenanceStmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
            $maintenanceStmt->execute();
            $maintenanceResult = $maintenanceStmt->get_result();
            $maintenanceRow = $maintenanceResult->fetch_assoc();
            $maintenanceStmt->close();
            
            $isMaintenanceMode = ($maintenanceRow && $maintenanceRow['setting_value'] === '1');
            
            if ($isMaintenanceMode) {
                // Buscar mensagem de manutenção personalizada
                $msgStmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_message'");
                $msgStmt->execute();
                $msgResult = $msgStmt->get_result();
                $msgRow = $msgResult->fetch_assoc();
                $msgStmt->close();
                
                $maintenanceMessage = $msgRow ? $msgRow['setting_value'] : 'Servidor em manutenção. Tente novamente mais tarde.';
                
                error_log("[DEVICE_CHECK] MODO DE MANUTENÇÃO ATIVO - Bloqueando acesso");
                
                echo json_encode([
                    'success' => false,
                    'maintenance' => true,
                    'maintenance_message' => $maintenanceMessage,
                    'message' => 'Servidor em manutenção'
                ]);
                
                $conn->close();
                exit;
            }
        } catch (Exception $e) {
            // Se falhar a verificação de manutenção, continuar normalmente
            error_log("[DEVICE_CHECK] Erro ao verificar manutenção (não crítico): " . $e->getMessage());
        }
    } else {
        error_log("[DEVICE_CHECK] Admin detectado ($requestEmail) - Bypass de manutenção");
    }
    // ========================================
    
    error_log("[DEVICE_CHECK] ========== NOVA REQUISIÇÃO ==========");
    error_log("[DEVICE_CHECK] Raw body: " . substr($rawBody, 0, 200));
    error_log("[DEVICE_CHECK] Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("[DEVICE_CHECK] Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
    error_log("[DEVICE_CHECK] User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'not set'));
    
    if (!$input || !isset($input['device_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'device_id é obrigatório']);
        exit;
    }
    
    $device_id = trim($input['device_id']);
    $device_info = isset($input['device_info']) ? $input['device_info'] : '{}';
    
    // Validar device_id (deve ser um hash SHA-256 de 64 caracteres)
    if (strlen($device_id) < 32) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'device_id inválido']);
        exit;
    }
    
    // Verificar se dispositivo já está vinculado
    // Prioridade: 1) email da tabela device_bindings, 2) email da tabela users, 3) fallback user_id
    $stmt = $conn->prepare("
        SELECT 
            db.id,
            db.user_id,
            db.device_id,
            db.email as binding_email,
            db.created_at,
            u.email as user_email,
            u.name
        FROM device_bindings db
        LEFT JOIN users u ON db.user_id = u.id
        WHERE db.device_id = ?
        AND db.is_active = 1
        LIMIT 1
    ");
    
    $stmt->bind_param("s", $device_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    error_log("[DEVICE_CHECK] Device ID recebido: " . substr($device_id, 0, 16) . "...");
    error_log("[DEVICE_CHECK] Query executada com sucesso");
    error_log("[DEVICE_CHECK] Resultado: " . ($existing ? "BLOQUEADO (user_id: " . $existing['user_id'] . ")" : "LIBERADO"));
    
    if ($existing) {
        // Dispositivo já vinculado a uma conta
        // Determinar o email a ser exibido (prioridade: binding_email > user_email)
        // Se não tiver email, mostrar mensagem genérica ao invés de user_id
        $displayEmail = $existing['binding_email'] 
            ?? $existing['user_email'] 
            ?? 'Conta já cadastrada';
        
        error_log("[DEVICE_CHECK] Dispositivo vinculado ao usuário: " . $displayEmail);
        error_log("[DEVICE_CHECK] binding_email: " . ($existing['binding_email'] ?? 'NULL'));
        error_log("[DEVICE_CHECK] user_email: " . ($existing['user_email'] ?? 'NULL'));
        
        // Registrar tentativa de acesso (ignorar erros se a tabela não existir)
        try {
            $logStmt = $conn->prepare("
                INSERT INTO device_access_logs 
                (device_id, user_id, action, device_info, ip_address, created_at)
                VALUES (?, ?, 'check_blocked', ?, ?, NOW())
            ");
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $logStmt->bind_param("siss", $device_id, $existing['user_id'], $device_info, $ip);
            $logStmt->execute();
            $logStmt->close();
        } catch (Exception $e) {
            error_log("[DEVICE_CHECK] Erro ao registrar log (não crítico): " . $e->getMessage());
        }
        
        // Mostrar e-mail para ajudar o usuário a lembrar
        echo json_encode([
            'success' => true,
            'blocked' => true,
            'existing_email' => $displayEmail,
            'message' => 'Este dispositivo já está vinculado a outra conta'
        ]);
    } else {
        // Dispositivo livre
        error_log("[DEVICE_CHECK] Dispositivo livre - permitindo login");
        echo json_encode([
            'success' => true,
            'blocked' => false,
            'existing_email' => '',
            'message' => 'Dispositivo liberado'
        ]);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Device check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao verificar dispositivo']);
}

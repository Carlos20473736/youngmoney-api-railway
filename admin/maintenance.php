<?php
/**
 * Admin: Gerenciar Modo de Manutenção
 * 
 * Endpoint: GET/POST /admin/maintenance.php
 * 
 * GET: Retorna status atual do modo de manutenção
 * POST: Ativa/desativa modo de manutenção
 * 
 * IMPORTANTE: Quando ativado, TODAS as APIs POST e GET são bloqueadas
 * pelo middleware de segurança (security_middleware_v3.php)
 * 
 * Request Body (POST):
 * {
 *   "enabled": true/false,
 *   "message": "Mensagem personalizada de manutenção"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "maintenance_mode": true/false,
 *   "maintenance_message": "Mensagem atual"
 * }
 * 
 * @version 2.0.0 - Integrado com middleware global
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Carregar configuração do banco
require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // Criar tabela de configurações do sistema se não existir
    $conn->query("
        CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Buscar status atual
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $isEnabled = ($row && $row['setting_value'] === '1');
        
        // Buscar mensagem
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_message'");
        $stmt->execute();
        $result = $stmt->get_result();
        $msgRow = $result->fetch_assoc();
        $stmt->close();
        
        $message = $msgRow ? $msgRow['setting_value'] : 'Servidor em manutenção. Tente novamente mais tarde.';
        
        echo json_encode([
            'success' => true,
            'maintenance_mode' => $isEnabled,
            'maintenance_message' => $message,
            'info' => 'Quando ativado, TODAS as APIs POST e GET são bloqueadas automaticamente pelo middleware'
        ]);
        
    } elseif ($method === 'POST') {
        // Atualizar modo de manutenção
        $rawBody = file_get_contents('php://input');
        $input = json_decode($rawBody, true);
        
        $enabled = isset($input['enabled']) ? ($input['enabled'] ? '1' : '0') : '0';
        $message = isset($input['message']) ? $input['message'] : 'Servidor em manutenção. Tente novamente mais tarde.';
        
        // Atualizar ou inserir maintenance_mode
        $stmt = $conn->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES ('maintenance_mode', ?)
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->bind_param("ss", $enabled, $enabled);
        $stmt->execute();
        $stmt->close();
        
        // Atualizar ou inserir maintenance_message
        $stmt = $conn->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES ('maintenance_message', ?)
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->bind_param("ss", $message, $message);
        $stmt->execute();
        $stmt->close();
        
        $isEnabled = ($enabled === '1');
        
        error_log("[MAINTENANCE] Modo de manutenção " . ($isEnabled ? "ATIVADO" : "DESATIVADO") . " - Mensagem: " . $message);
        
        echo json_encode([
            'success' => true,
            'maintenance_mode' => $isEnabled,
            'maintenance_message' => $message,
            'message' => $isEnabled 
                ? 'Modo de manutenção ATIVADO - Todas as APIs estão bloqueadas' 
                : 'Modo de manutenção DESATIVADO - APIs funcionando normalmente',
            'affected' => $isEnabled 
                ? 'Todas as requisições POST e GET serão bloqueadas (exceto admin)' 
                : 'Todas as APIs estão operacionais'
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Maintenance error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao gerenciar manutenção: ' . $e->getMessage()]);
}

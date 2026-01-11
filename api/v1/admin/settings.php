<?php
/**
 * API Endpoint: Configurações do Sistema (Admin)
 * 
 * POST /api/v1/admin/settings.php
 * 
 * Body:
 * {
 *   "token": "ym_reset_ranking_scheduled_2024_secure",
 *   "key": "app_version",
 *   "value": "44.0"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Configuração atualizada com sucesso"
 * }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req, X-Request-ID, X-Timestamp, X-Nonce, X-Device-Fingerprint, X-App-Hash, X-Request-Signature, X-Rotating-Key, X-Native-Signature, X-Key-Window');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../database.php';

// Token de autenticação admin
$ADMIN_TOKEN = 'ym_reset_ranking_scheduled_2024_secure';

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Listar todas as configurações
        $token = $_GET['token'] ?? '';
        
        if ($token !== $ADMIN_TOKEN) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Token inválido']);
            exit;
        }
        
        $conn = getDbConnection();
        
        $result = $conn->query("SELECT setting_key, setting_value, updated_at FROM system_settings ORDER BY setting_key");
        $settings = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $settings[] = $row;
            }
        }
        
        echo json_encode(['success' => true, 'data' => $settings]);
        $conn->close();
        exit;
    }
    
    // POST - Atualizar configuração
    $input = json_decode(file_get_contents('php://input'), true);
    
    $token = $input['token'] ?? '';
    $key = $input['key'] ?? '';
    $value = $input['value'] ?? '';
    
    // Validar token
    if ($token !== $ADMIN_TOKEN) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token inválido']);
        exit;
    }
    
    // Validar key
    if (empty($key)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Key é obrigatório']);
        exit;
    }
    
    $conn = getDbConnection();
    
    // Criar tabela se não existir
    $conn->query("
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_setting_key (setting_key)
        )
    ");
    
    // Inserir ou atualizar configuração
    $stmt = $conn->prepare("
        INSERT INTO system_settings (setting_key, setting_value, created_at, updated_at) 
        VALUES (?, ?, NOW(), NOW()) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
    ");
    
    $stmt->bind_param("ss", $key, $value);
    $stmt->execute();
    
    if ($stmt->affected_rows >= 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Configuração atualizada com sucesso',
            'data' => [
                'key' => $key,
                'value' => $value
            ]
        ]);
    } else {
        throw new Exception("Erro ao atualizar configuração");
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log("admin/settings.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>

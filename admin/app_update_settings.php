<?php
/**
 * Endpoint Admin: Configurações de Atualização do App
 * 
 * GET - Buscar configurações de atualização
 * POST - Atualizar configurações de atualização
 * 
 * Configurações:
 * - app_update_enabled: '0' ou '1'
 * - app_update_min_version: versão mínima (ex: '44.0')
 * - app_update_download_url: URL da Play Store
 * - app_update_secondary_url: URL para download direto do APK
 * - app_update_force: '0' ou '1'
 * - app_update_release_notes: notas da versão
 */

require_once __DIR__ . '/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // ============================================
        // GET - Buscar configurações de atualização
        // ============================================
        
        $stmt = $conn->prepare("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE setting_key LIKE 'app_update_%'
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'app_update_enabled' => $settings['app_update_enabled'] ?? '0',
                'app_update_min_version' => $settings['app_update_min_version'] ?? '1.0.0',
                'app_update_download_url' => $settings['app_update_download_url'] ?? '',
                'app_update_secondary_url' => $settings['app_update_secondary_url'] ?? '',
                'app_update_force' => $settings['app_update_force'] ?? '0',
                'app_update_release_notes' => $settings['app_update_release_notes'] ?? ''
            ]
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // ============================================
        // POST - Atualizar configurações
        // ============================================
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            throw new Exception('Dados inválidos');
        }
        
        $conn->begin_transaction();
        
        try {
            // Lista de configurações permitidas
            $allowedSettings = [
                'app_update_enabled',
                'app_update_min_version',
                'app_update_download_url',
                'app_update_secondary_url',
                'app_update_force',
                'app_update_release_notes'
            ];
            
            foreach ($allowedSettings as $key) {
                if (isset($data[$key])) {
                    $value = $data[$key];
                    
                    $stmt = $conn->prepare("
                        INSERT INTO system_settings (setting_key, setting_value, created_at, updated_at) 
                        VALUES (?, ?, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE 
                            setting_value = VALUES(setting_value),
                            updated_at = NOW()
                    ");
                    $stmt->bind_param('ss', $key, $value);
                    $stmt->execute();
                }
            }
            
            $conn->commit();
            
            // Buscar configurações atualizadas
            $stmt = $conn->prepare("
                SELECT setting_key, setting_value 
                FROM system_settings 
                WHERE setting_key LIKE 'app_update_%'
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $updatedSettings = [];
            while ($row = $result->fetch_assoc()) {
                $updatedSettings[$row['setting_key']] = $row['setting_value'];
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Configurações de atualização salvas com sucesso!',
                'data' => $updatedSettings
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } else {
        throw new Exception('Método não permitido');
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("admin/app_update_settings.php error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

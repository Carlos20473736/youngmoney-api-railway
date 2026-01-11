<?php
/**
 * Script de Inicialização: Configurações de Atualização do App
 * 
 * Execute este script uma vez para garantir que todas as configurações
 * de atualização do app existam no banco de dados.
 * 
 * Acesse via: /admin/init_app_update_settings.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    $results = [];
    
    // Lista de configurações necessárias com valores padrão
    $settings = [
        ['app_update_enabled', '0', 'Habilita verificação de atualização do app'],
        ['app_update_min_version', '1.0.0', 'Versão mínima requerida do app'],
        ['app_update_download_url', '', 'URL para download da nova versão do app (Play Store)'],
        ['app_update_secondary_url', '', 'URL secundária para download direto do APK'],
        ['app_update_force', '0', 'Força atualização (bloqueia uso do app)'],
        ['app_update_release_notes', '', 'Notas de lançamento da nova versão']
    ];
    
    foreach ($settings as $setting) {
        $key = $setting[0];
        $defaultValue = $setting[1];
        $description = $setting[2];
        
        // Verificar se a configuração já existe
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM system_settings WHERE setting_key = ?");
        $checkStmt->bind_param('s', $key);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $row = $checkResult->fetch_assoc();
        
        if ($row['count'] == 0) {
            // Inserir nova configuração (sem coluna description pois pode não existir)
            $insertStmt = $conn->prepare("
                INSERT INTO system_settings (setting_key, setting_value, created_at, updated_at) 
                VALUES (?, ?, NOW(), NOW())
            ");
            $insertStmt->bind_param('ss', $key, $defaultValue);
            
            if ($insertStmt->execute()) {
                $results[] = [
                    'key' => $key,
                    'status' => 'created',
                    'value' => $defaultValue
                ];
            } else {
                $results[] = [
                    'key' => $key,
                    'status' => 'error',
                    'error' => $conn->error
                ];
            }
        } else {
            $results[] = [
                'key' => $key,
                'status' => 'exists'
            ];
        }
    }
    
    // Buscar todas as configurações atuais
    $currentSettings = [];
    $stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'app_update_%' ORDER BY setting_key");
    while ($row = $stmt->fetch_assoc()) {
        $currentSettings[$row['setting_key']] = $row['setting_value'];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Inicialização concluída!',
        'results' => $results,
        'current_settings' => $currentSettings
    ], JSON_PRETTY_PRINT);
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("init_app_update_settings.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

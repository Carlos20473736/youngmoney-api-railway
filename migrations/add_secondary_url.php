<?php
/**
 * Migration: Adicionar app_update_secondary_url
 * 
 * Este script adiciona a configuração de URL secundária para download direto do APK
 * se ela ainda não existir no banco de dados.
 */

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // Verificar se a configuração já existe
    $checkQuery = "SELECT COUNT(*) as count FROM system_settings WHERE setting_key = 'app_update_secondary_url'";
    $result = $conn->query($checkQuery);
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Inserir a configuração
        $insertQuery = "INSERT INTO system_settings (setting_key, setting_value, description, created_at, updated_at) 
                        VALUES ('app_update_secondary_url', '', 'URL secundária para download direto do APK', NOW(), NOW())";
        
        if ($conn->query($insertQuery)) {
            echo json_encode([
                'success' => true,
                'message' => 'Configuração app_update_secondary_url adicionada com sucesso!'
            ]);
        } else {
            throw new Exception("Erro ao inserir: " . $conn->error);
        }
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Configuração app_update_secondary_url já existe.'
        ]);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

<?php
/**
 * Script para limpar vinculações de dispositivos
 * Use apenas para testes/debug
 */

require_once __DIR__ . '/database.php';

try {
    $conn = getDbConnection();
    
    // Desativar todas as vinculações
    $stmt = $conn->prepare("UPDATE device_bindings SET is_active = 0 WHERE 1=1");
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    echo "✅ Vinculações desativadas: " . $affected . "\n";
    echo "Agora você pode testar a proteção de múltiplas contas do zero.\n";
    
    $conn->close();
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

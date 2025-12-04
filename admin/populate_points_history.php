<?php
/**
 * Script para popular points_history com dados existentes
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';

try {
    $conn = getDbConnection();
    
    // Verificar se tabela existe
    $result = $conn->query("SHOW TABLES LIKE 'points_history'");
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Tabela points_history não existe. Execute setup_history_tables.php primeiro.'
        ]);
        exit;
    }
    
    // Contar registros existentes
    $result = $conn->query("SELECT COUNT(*) as total FROM points_history");
    $row = $result->fetch_assoc();
    $existingRecords = $row['total'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Verificação concluída',
        'existing_records' => $existingRecords,
        'note' => 'Histórico é populado automaticamente quando usuário ganha pontos (spin, checkin, etc)'
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

<?php
/**
 * Migration: Adicionar campo has_used_invite_code na tabela users
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';

try {
    $conn = getDbConnection();
    
    // Verificar se coluna já existe
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'has_used_invite_code'");
    
    if ($result->num_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Campo has_used_invite_code já existe',
            'already_exists' => true
        ]);
        exit;
    }
    
    // Adicionar coluna
    $conn->query("
        ALTER TABLE users 
        ADD COLUMN has_used_invite_code TINYINT(1) DEFAULT 0 AFTER invited_by
    ");
    
    echo json_encode([
        'success' => true,
        'message' => 'Campo has_used_invite_code adicionado com sucesso!',
        'sql' => 'ALTER TABLE users ADD COLUMN has_used_invite_code TINYINT(1) DEFAULT 0'
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

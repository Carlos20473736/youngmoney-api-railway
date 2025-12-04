<?php
header('Content-Type: application/json' );
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // Verificar se a coluna já existe
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'invite_code'");
    
    if ($result->num_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Coluna invite_code já existe!'
        ]);
        exit;
    }
    
    // Adicionar coluna invite_code
    $conn->query("ALTER TABLE users ADD COLUMN invite_code VARCHAR(20) DEFAULT NULL");
    
    // Gerar códigos para usuários existentes
    $conn->query("UPDATE users SET invite_code = CONCAT('YM', LPAD(id, 6, '0')) WHERE invite_code IS NULL");
    
    echo json_encode([
        'success' => true,
        'message' => 'Coluna invite_code adicionada com sucesso!',
        'sql_executed' => [
            'ALTER TABLE users ADD COLUMN invite_code VARCHAR(20) DEFAULT NULL',
            'UPDATE users SET invite_code = CONCAT(\'YM\', LPAD(id, 6, \'0\')) WHERE invite_code IS NULL'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500 );
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

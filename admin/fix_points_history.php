<?php
header('Content-Type: application/json' );
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // Verificar se a coluna 'amount' existe
    $result = $conn->query("SHOW COLUMNS FROM points_history LIKE 'amount'");
    
    if ($result->num_rows > 0) {
        // Renomear coluna 'amount' para 'points'
        $conn->query("ALTER TABLE points_history CHANGE COLUMN amount points INT NOT NULL");
        $message = "Coluna 'amount' renomeada para 'points' com sucesso!";
    } else {
        // Verificar se a coluna 'points' já existe
        $result = $conn->query("SHOW COLUMNS FROM points_history LIKE 'points'");
        if ($result->num_rows > 0) {
            $message = "Coluna 'points' já existe!";
        } else {
            // Adicionar coluna 'points'
            $conn->query("ALTER TABLE points_history ADD COLUMN points INT NOT NULL AFTER user_id");
            $message = "Coluna 'points' adicionada com sucesso!";
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    http_response_code(500 );
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

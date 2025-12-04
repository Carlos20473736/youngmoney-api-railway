<?php
/**
 * Script para recriar a tabela withdrawal_quick_values com a estrutura correta
 */

require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

try {
    $conn = getDbConnection();
    
    // 1. Dropar tabela existente
    $conn->query("DROP TABLE IF EXISTS withdrawal_quick_values");
    
    // 2. Criar tabela com estrutura correta
    $sql = "CREATE TABLE withdrawal_quick_values (
        id INT AUTO_INCREMENT PRIMARY KEY,
        value_amount DECIMAL(10,2) NOT NULL,
        display_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active_order (is_active, display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        throw new Exception("Erro ao criar tabela: " . $conn->error);
    }
    
    // 3. Inserir valores padrão
    $defaultValues = [
        ['value' => 1.00, 'order' => 1],
        ['value' => 10.00, 'order' => 2],
        ['value' => 20.00, 'order' => 3],
        ['value' => 50.00, 'order' => 4]
    ];
    
    $stmt = $conn->prepare("
        INSERT INTO withdrawal_quick_values (value_amount, display_order) 
        VALUES (?, ?)
    ");
    
    foreach ($defaultValues as $val) {
        $stmt->bind_param("di", $val['value'], $val['order']);
        $stmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Tabela withdrawal_quick_values recriada com sucesso!'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

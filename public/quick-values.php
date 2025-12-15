<?php
/**
 * Endpoint Público - Valores Rápidos de Saque (SEM CRIPTOGRAFIA)
 * Usado pelo app Android WebView
 * 
 * GET /public/quick-values.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

date_default_timezone_set('America/Sao_Paulo');

// Incluir configuração do banco de dados
require_once __DIR__ . '/../db_config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Método não permitido'
        ]);
        exit;
    }
    
    // Usar a função helper do db_config.php
    $conn = getMySQLiConnection();
    
    // Buscar valores ativos
    $result = $conn->query("
        SELECT value_amount 
        FROM withdrawal_quick_values 
        WHERE is_active = 1 
        ORDER BY display_order ASC
    ");
    
    $values = [];
    while ($row = $result->fetch_assoc()) {
        $values[] = (float)$row['value_amount'];
    }
    
    $conn->close();
    
    // Se não houver valores, retornar padrão
    if (empty($values)) {
        $values = [1.0, 10.0, 20.0, 50.0];
    }
    
    // Enviar resposta SEM criptografia
    echo json_encode([
        'success' => true,
        'values' => $values
    ]);
    
} catch (Exception $e) {
    error_log("Quick values endpoint error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar valores: ' . $e->getMessage()
    ]);
}
?>

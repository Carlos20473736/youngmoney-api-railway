<?php
/**
 * API de Instaladores Permitidos
 * 
 * Este endpoint retorna a lista de instaladores permitidos do banco de dados.
 * O app Android consulta este endpoint para verificar se a origem de instalação é válida.
 * 
 * Endpoint: GET /api/v1/security/allowed-installers.php
 * 
 * Resposta:
 * {
 *   "success": true,
 *   "installers": ["com.android.vending", "com.android.shell", "adb"],
 *   "timestamp": 1234567890
 * }
 */

// Habilitar CORS para permitir requisições do app
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Responder OPTIONS para preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Apenas aceitar GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método não permitido'
    ]);
    exit;
}

// Incluir configuração do banco de dados
require_once __DIR__ . '/../../../db_config.php';

try {
    // Conectar ao banco de dados
    $pdo = getPDOConnection();
    
    // Buscar instaladores ativos da tabela allowed_installers
    $stmt = $pdo->prepare("
        SELECT package_name 
        FROM allowed_installers 
        WHERE is_active = 1 OR is_active = TRUE
        ORDER BY id ASC
    ");
    $stmt->execute();
    
    $installers = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $installers[] = $row['package_name'];
    }
    
    // Se não houver instaladores no banco, retornar lista padrão
    if (empty($installers)) {
        $installers = ['com.android.vending']; // Google Play Store como padrão
    }
    
    // Retornar resposta de sucesso
    echo json_encode([
        'success' => true,
        'installers' => $installers,
        'timestamp' => time() * 1000 // Timestamp em milissegundos
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar instaladores: " . $e->getMessage());
    
    // Em caso de erro no banco, retornar lista padrão
    echo json_encode([
        'success' => true,
        'installers' => ['com.android.vending', 'com.android.shell', 'adb'],
        'timestamp' => time() * 1000,
        'fallback' => true
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor',
        'installers' => ['com.android.vending'], // Fallback mínimo
        'timestamp' => time() * 1000
    ], JSON_UNESCAPED_UNICODE);
}
?>

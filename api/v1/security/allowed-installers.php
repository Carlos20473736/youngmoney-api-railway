<?php
/**
 * API de Instaladores Permitidos
 * 
 * Este endpoint retorna a lista de instaladores permitidos do banco de dados.
 * O app Android consulta este endpoint para verificar se a origem de instalação é válida.
 * 
 * Endpoint: GET /api/v1/security/allowed-installers.php
 * 
 * Comportamento:
 * - Se allow_any_installer = true: retorna ["*"] (qualquer instalador é permitido)
 * - Se allow_any_installer = false: retorna apenas ["com.android.vending"] (Play Store)
 * 
 * Resposta:
 * {
 *   "success": true,
 *   "installers": ["com.android.vending"] ou ["*"],
 *   "allow_any": true/false,
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
require_once __DIR__ . '/../../../database.php';
require_once __DIR__ . '/../middleware/MaintenanceCheck.php';

try {
    // ========================================
    // VERIFICAÇÃO DE MANUTENÇÃO E VERSÃO
    // ========================================
    $maintenanceConn = getDbConnection();
    $userEmail = $_GET['email'] ?? null;
    $appVersion = $_GET['app_version'] ?? $_SERVER['HTTP_X_APP_VERSION'] ?? null;
    checkMaintenanceAndVersion($maintenanceConn, $userEmail, $appVersion);
    $maintenanceConn->close();
    // ========================================
    
    // Conectar ao banco de dados
    $pdo = getPDOConnection();
    
    // Verificar se a configuração allow_any_installer existe na tabela system_settings
    $allowAny = false;
    
    try {
        $stmt = $pdo->prepare("
            SELECT setting_value 
            FROM system_settings 
            WHERE setting_key = 'allow_any_installer'
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Converter para boolean (aceita '1', 'true', 'yes', 'on')
            $value = strtolower(trim($result['setting_value']));
            $allowAny = in_array($value, ['1', 'true', 'yes', 'on']);
        }
    } catch (PDOException $e) {
        // Se a tabela não existir ou der erro, usar valor padrão (false)
        error_log("Erro ao buscar allow_any_installer: " . $e->getMessage());
    }
    
    // Se permitir qualquer instalador, retornar "*"
    if ($allowAny) {
        echo json_encode([
            'success' => true,
            'installers' => ['*'],
            'allow_any' => true,
            'timestamp' => time() * 1000
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Caso contrário, retornar apenas Play Store
    echo json_encode([
        'success' => true,
        'installers' => ['com.android.vending'],
        'allow_any' => false,
        'timestamp' => time() * 1000
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar instaladores: " . $e->getMessage());
    
    // Em caso de erro no banco, retornar apenas Play Store (modo seguro)
    echo json_encode([
        'success' => true,
        'installers' => ['com.android.vending'],
        'allow_any' => false,
        'timestamp' => time() * 1000,
        'fallback' => true
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor',
        'installers' => ['com.android.vending'],
        'allow_any' => false,
        'timestamp' => time() * 1000
    ], JSON_UNESCAPED_UNICODE);
}
?>

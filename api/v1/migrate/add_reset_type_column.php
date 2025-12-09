<?php
/**
 * Migration: Adicionar coluna reset_type nas tabelas de logs
 * 
 * Endpoint: GET /api/v1/migrate/add_reset_type_column.php
 * 
 * Adiciona a coluna 'reset_type' e outras colunas faltantes nas tabelas:
 * - ranking_reset_logs
 * - spin_reset_logs
 * - checkin_reset_logs
 */

header('Content-Type: application/json; charset=utf-8');

try {
    // Conectar ao banco de dados usando MySQLi
    $db_host = $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST') ?: 'localhost';
    $db_user = $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER') ?: 'root';
    $db_pass = $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?: '';
    $db_name = $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?: 'railway';
    $db_port = $_ENV['MYSQLPORT'] ?? getenv('MYSQLPORT') ?: 3306;
    
    $conn = mysqli_init();
    if (!$conn) {
        throw new Exception("mysqli_init failed");
    }
    
    $success = $conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
    if (!$success) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    $results = [];
    
    // 1. Adicionar colunas na tabela ranking_reset_logs
    $tables = [
        'ranking_reset_logs' => [
            'reset_type' => "ALTER TABLE ranking_reset_logs ADD COLUMN reset_type VARCHAR(50) DEFAULT 'scheduled' AFTER id",
            'triggered_by' => "ALTER TABLE ranking_reset_logs ADD COLUMN triggered_by VARCHAR(50) DEFAULT 'cron-api' AFTER reset_type",
            'status' => "ALTER TABLE ranking_reset_logs ADD COLUMN status VARCHAR(50) DEFAULT 'success' AFTER users_affected"
        ],
        'spin_reset_logs' => [
            'reset_type' => "ALTER TABLE spin_reset_logs ADD COLUMN reset_type VARCHAR(50) DEFAULT 'scheduled' AFTER id",
            'triggered_by' => "ALTER TABLE spin_reset_logs ADD COLUMN triggered_by VARCHAR(50) DEFAULT 'cron-api' AFTER reset_type",
            'status' => "ALTER TABLE spin_reset_logs ADD COLUMN status VARCHAR(50) DEFAULT 'success' AFTER spins_deleted"
        ],
        'checkin_reset_logs' => [
            'reset_type' => "ALTER TABLE checkin_reset_logs ADD COLUMN reset_type VARCHAR(50) DEFAULT 'scheduled' AFTER id",
            'triggered_by' => "ALTER TABLE checkin_reset_logs ADD COLUMN triggered_by VARCHAR(50) DEFAULT 'cron-api' AFTER reset_type",
            'status' => "ALTER TABLE checkin_reset_logs ADD COLUMN status VARCHAR(50) DEFAULT 'success' AFTER reset_datetime"
        ]
    ];
    
    foreach ($tables as $table => $columns) {
        foreach ($columns as $column => $sql) {
            try {
                // Verificar se coluna já existe
                $check = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
                
                if ($check && $check->num_rows === 0) {
                    // Coluna não existe, adicionar
                    if ($conn->query($sql)) {
                        $results[] = [
                            'table' => $table,
                            'column' => $column,
                            'status' => 'created',
                            'message' => "Coluna '$column' adicionada com sucesso em '$table'"
                        ];
                    } else {
                        $results[] = [
                            'table' => $table,
                            'column' => $column,
                            'status' => 'error',
                            'message' => "Erro ao adicionar coluna: " . $conn->error
                        ];
                    }
                } else {
                    $results[] = [
                        'table' => $table,
                        'column' => $column,
                        'status' => 'exists',
                        'message' => "Coluna '$column' já existe em '$table'"
                    ];
                }
            } catch (Exception $e) {
                $results[] = [
                    'table' => $table,
                    'column' => $column,
                    'status' => 'error',
                    'message' => "Erro: " . $e->getMessage()
                ];
            }
        }
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Migration executada com sucesso!',
        'results' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao executar migration',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

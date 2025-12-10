<?php
/**
 * Migration: Adicionar colunas de Monetag na tabela users
 * 
 * Endpoint: GET /api/v1/migrate/add_monetag_columns.php
 * 
 * Adiciona as colunas:
 * - monetag_impressions
 * - monetag_clicks
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
    
    // Adicionar colunas na tabela users
    $columns = [
        'monetag_impressions' => "ALTER TABLE users ADD COLUMN monetag_impressions INT DEFAULT 0 AFTER points",
        'monetag_clicks' => "ALTER TABLE users ADD COLUMN monetag_clicks INT DEFAULT 0 AFTER monetag_impressions"
    ];
    
    foreach ($columns as $column => $sql) {
        try {
            // Verificar se coluna já existe
            $check = $conn->query("SHOW COLUMNS FROM users LIKE '$column'");
            
            if ($check && $check->num_rows === 0) {
                // Coluna não existe, adicionar
                if ($conn->query($sql)) {
                    $results[] = [
                        'table' => 'users',
                        'column' => $column,
                        'status' => 'created',
                        'message' => "Coluna '$column' adicionada com sucesso em 'users'"
                    ];
                } else {
                    $results[] = [
                        'table' => 'users',
                        'column' => $column,
                        'status' => 'error',
                        'message' => "Erro ao adicionar coluna: " . $conn->error
                    ];
                }
            } else {
                $results[] = [
                    'table' => 'users',
                    'column' => $column,
                    'status' => 'exists',
                    'message' => "Coluna '$column' já existe em 'users'"
                ];
            }
        } catch (Exception $e) {
            $results[] = [
                'table' => 'users',
                'column' => $column,
                'status' => 'error',
                'message' => "Erro: " . $e->getMessage()
            ];
        }
    }
    
    // Criar tabela monetag_reset_logs se não existir
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS monetag_reset_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reset_type VARCHAR(50) DEFAULT 'manual',
            triggered_by VARCHAR(50) DEFAULT 'manual',
            events_deleted INT DEFAULT 0,
            users_affected INT DEFAULT 0,
            reset_datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(50) DEFAULT 'success',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn->query($create_table_sql)) {
        $results[] = [
            'table' => 'monetag_reset_logs',
            'column' => 'all',
            'status' => 'created',
            'message' => "Tabela 'monetag_reset_logs' criada com sucesso"
        ];
    } else {
        // Tabela pode já existir, não é erro
        $results[] = [
            'table' => 'monetag_reset_logs',
            'column' => 'all',
            'status' => 'exists',
            'message' => "Tabela 'monetag_reset_logs' já existe ou foi criada"
        ];
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

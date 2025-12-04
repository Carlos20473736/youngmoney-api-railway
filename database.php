<?php
// Arquivo de Conexão com o Banco de Dados

function getDbConnection() {
    try {
        // Buscar variáveis de ambiente diretamente
        $db_host = $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST') ?: 'localhost';
        $db_user = $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER') ?: 'root';
        $db_pass = $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?: '';
        $db_name = $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?: 'railway';
        $db_port = $_ENV['MYSQLPORT'] ?? getenv('MYSQLPORT') ?: 3306;
        
        // Debug: Log das configurações (sem senha)
        error_log("DB_HOST: " . $db_host);
        error_log("DB_USER: " . $db_user);
        error_log("DB_NAME: " . $db_name);
        error_log("DB_PORT: " . $db_port);
        error_log("DB_PASSWORD exists: " . ($db_pass ? 'yes' : 'no'));
        
        // Criar conexão MySQLi com SSL
        $conn = mysqli_init();
        
        if (!$conn) {
            throw new Exception("mysqli_init failed");
        }
        
        // Conectar ao banco (Railway MySQL não requer SSL na rede interna)
        $success = $conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
        
        if (!$success) {
            error_log("Connection failed: " . $conn->connect_error);
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        error_log("Database connection successful!");
        return $conn;
    } catch (Exception $e) {
        // Em um ambiente de produção, você pode querer logar o erro em vez de exibi-lo
        error_log("Database exception: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Database connection error: ' . $e->getMessage()]);
        exit;
    }
}
?>

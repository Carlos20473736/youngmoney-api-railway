<?php
// Arquivo de Conexão com o Banco de Dados

function getDbConnection() {
    try {
        // Buscar variáveis de ambiente diretamente
        $db_host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
        $db_user = $_ENV['DB_USER'] ?? getenv('DB_USER');
        $db_pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
        $db_name = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
        $db_port = $_ENV['DB_PORT'] ?? getenv('DB_PORT');
        
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
        
        // Configurar SSL (Aiven requer SSL)
        $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
        $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        
        // Conectar ao banco
        $success = $conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port, NULL, MYSQLI_CLIENT_SSL);
        
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

<?php
// Configurações do Banco de Dados usando variáveis de ambiente do Railway
define('DB_HOST', $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST') ?: 'localhost');
define('DB_USER', $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER') ?: 'root');
define('DB_PASSWORD', $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME', $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?: 'railway');
define('DB_PORT', $_ENV['MYSQLPORT'] ?? getenv('MYSQLPORT') ?: 3306);

// Timezone
date_default_timezone_set('America/Sao_Paulo');
?>

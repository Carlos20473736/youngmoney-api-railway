<?php
/**
 * Initialize Database Tables
 * Creates pix_keys and pix_payments tables if they don't exist
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
$host = getenv('MYSQLHOST') ?: 'localhost';
$user = getenv('MYSQLUSER') ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: '';
$database = getenv('MYSQLDATABASE') ?: 'railway';

try {
    $conn = new mysqli($host, $user, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // SQL para criar tabela pix_keys
    $sql_pix_keys = "CREATE TABLE IF NOT EXISTS `pix_keys` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL UNIQUE,
        `pix_key_type` ENUM('CPF', 'CNPJ', 'Email', 'Telefone', 'Chave Aleatória') NOT NULL,
        `pix_key` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        INDEX `idx_user_id` (`user_id`),
        INDEX `idx_pix_key` (`pix_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // SQL para criar tabela pix_payments
    $sql_pix_payments = "CREATE TABLE IF NOT EXISTS `pix_payments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `position` INT NOT NULL COMMENT 'Posição no ranking (1-10)',
        `amount` DECIMAL(10, 2) NOT NULL COMMENT 'Valor em reais',
        `pix_key_type` ENUM('CPF', 'CNPJ', 'Email', 'Telefone', 'Chave Aleatória') NOT NULL,
        `pix_key` VARCHAR(255) NOT NULL,
        `status` ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        `transaction_id` VARCHAR(255) NULL COMMENT 'ID da transação do gateway de pagamento',
        `error_message` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        INDEX `idx_user_id` (`user_id`),
        INDEX `idx_status` (`status`),
        INDEX `idx_created_at` (`created_at`),
        INDEX `idx_position` (`position`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Executar criação das tabelas
    if (!$conn->query($sql_pix_keys)) {
        throw new Exception("Error creating pix_keys table: " . $conn->error);
    }

    if (!$conn->query($sql_pix_payments)) {
        throw new Exception("Error creating pix_payments table: " . $conn->error);
    }

    $conn->close();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Database tables initialized successfully',
        'tables_created' => ['pix_keys', 'pix_payments']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

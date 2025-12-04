<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    $results = [];
    
    // IMPORTANTE: Desabilitar foreign key checks temporariamente
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $results[] = "Foreign key checks disabled";
    
    // SQL completo para RECRIAR todas as tabelas com TODAS as colunas necessárias
    $sql_statements = [
        // 1. DROP e RECRIAR tabela users com TODAS as colunas
        "DROP TABLE IF EXISTS users",
        
        "CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(255) UNIQUE,
            google_id VARCHAR(255) UNIQUE,
            email VARCHAR(255),
            name VARCHAR(255),
            username VARCHAR(255),
            photo_url TEXT,
            profile_picture TEXT,
            balance DECIMAL(10, 2) DEFAULT 0.00,
            points INT DEFAULT 0,
            daily_points INT DEFAULT 0,
            invite_code VARCHAR(20) UNIQUE,
            invited_by INT DEFAULT NULL,
            has_used_invite_code BOOLEAN DEFAULT FALSE,
            token VARCHAR(255),
            token_expires_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_device_id (device_id),
            INDEX idx_google_id (google_id),
            INDEX idx_email (email),
            INDEX idx_invite_code (invite_code),
            INDEX idx_token (token),
            FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL
        )",
        
        // 2. DROP e RECRIAR tabela daily_tasks
        "DROP TABLE IF EXISTS daily_tasks",
        
        "CREATE TABLE daily_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            task_date DATE NOT NULL,
            task_type VARCHAR(50) NOT NULL,
            completed BOOLEAN DEFAULT FALSE,
            points_earned INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_task_date (user_id, task_date, task_type),
            INDEX idx_user_date (user_id, task_date)
        )",
        
        // 3. DROP e RECRIAR tabela spins
        "DROP TABLE IF EXISTS spins",
        
        "CREATE TABLE spins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            prize_type VARCHAR(50) NOT NULL,
            prize_value DECIMAL(10, 2) NOT NULL,
            spin_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_date (user_id, spin_date)
        )",
        
        // 4. DROP e RECRIAR tabela withdrawals
        "DROP TABLE IF EXISTS withdrawals",
        
        "CREATE TABLE withdrawals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            pix_type VARCHAR(20) NOT NULL,
            pix_key VARCHAR(255) NOT NULL,
            status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_status (user_id, status),
            INDEX idx_status_created (status, created_at)
        )",
        
        // 5. DROP e RECRIAR tabela referrals
        "DROP TABLE IF EXISTS referrals",
        
        "CREATE TABLE referrals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            referrer_id INT NOT NULL,
            referred_id INT NOT NULL,
            points_earned INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_referrer (referrer_id),
            INDEX idx_referred (referred_id)
        )",
        
        // 6. DROP e RECRIAR tabela monetag_events
        "DROP TABLE IF EXISTS monetag_events",
        
        "CREATE TABLE monetag_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            event_type ENUM('impression', 'click') NOT NULL,
            session_id VARCHAR(255),
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_type (user_id, event_type),
            INDEX idx_session (session_id),
            INDEX idx_created (created_at)
        )",
        
        // 7. DROP e RECRIAR tabela active_sessions
        "DROP TABLE IF EXISTS active_sessions",
        
        "CREATE TABLE active_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(255) UNIQUE NOT NULL,
            user_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_session (session_id),
            INDEX idx_expires (expires_at)
        )",
        
        // 8. DROP e RECRIAR tabela point_transactions
        "DROP TABLE IF EXISTS point_transactions",
        
        "CREATE TABLE point_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            points INT NOT NULL,
            type ENUM('credit', 'debit') NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_created (user_id, created_at)
        )",
        
        // 9. DROP e RECRIAR tabela system_settings
        "DROP TABLE IF EXISTS system_settings",
        
        "CREATE TABLE system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_key (setting_key)
        )",
        
        // 10. DROP e RECRIAR tabela notifications
        "DROP TABLE IF EXISTS notifications",
        
        "CREATE TABLE notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) DEFAULT 'info',
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_read (user_id, is_read),
            INDEX idx_created (created_at)
        )",
        
        // 11. Inserir configuração padrão de reset_time
        "INSERT INTO system_settings (setting_key, setting_value) VALUES ('reset_time', '00:00:00')",
        
        // 12. Inserir configurações de valores rápidos de saque
        "INSERT INTO system_settings (setting_key, setting_value) VALUES ('quick_withdraw_values', '[20, 50, 100, 200, 500]')"
    ];
    
    // Executar cada statement
    foreach ($sql_statements as $index => $sql) {
        if ($conn->query($sql)) {
            $results[] = "✅ Statement " . ($index + 1) . " executado com sucesso";
        } else {
            $results[] = "❌ Erro no statement " . ($index + 1) . ": " . $conn->error;
        }
    }
    
    // Reabilitar foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    $results[] = "Foreign key checks re-enabled";
    
    echo json_encode([
        'success' => true,
        'message' => 'All tables recreated successfully with all required columns!',
        'results' => $results
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

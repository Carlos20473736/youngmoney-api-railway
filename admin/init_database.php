<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    $results = [];
    
    // SQL completo para criar todas as tabelas
    $sql_statements = [
        // 1. Criar tabela users primeiro (sem dependências)
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(255) UNIQUE,
            google_id VARCHAR(255) UNIQUE,
            email VARCHAR(255),
            name VARCHAR(255),
            photo_url TEXT,
            balance DECIMAL(10, 2) DEFAULT 0.00,
            points INT DEFAULT 0,
            invite_code VARCHAR(20) UNIQUE,
            invited_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL
        )",
        
        // 2. Criar tabela daily_tasks
        "CREATE TABLE IF NOT EXISTS daily_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            task_date DATE NOT NULL,
            task_type VARCHAR(50) NOT NULL,
            completed BOOLEAN DEFAULT FALSE,
            points_earned INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_task_date (user_id, task_date, task_type)
        )",
        
        // 3. Criar tabela spins
        "CREATE TABLE IF NOT EXISTS spins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            prize_type VARCHAR(50) NOT NULL,
            prize_value DECIMAL(10, 2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        // 4. Criar tabela withdrawals
        "CREATE TABLE IF NOT EXISTS withdrawals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            pix_type VARCHAR(20) NOT NULL,
            pix_key VARCHAR(255) NOT NULL,
            status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        // 5. Criar tabela referrals
        "CREATE TABLE IF NOT EXISTS referrals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            referrer_id INT NOT NULL,
            referred_id INT NOT NULL,
            points_earned INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        // 6. Criar tabela monetag_events
        // CORREÇÃO: Removida a restrição de chave estrangeira (FOREIGN KEY) para evitar erros        // 6. Criar tabela monetag_events
        // CORREÇÃO: Removida a restrição de chave estrangeira (FOREIGN KEY) para evitar erros
        // quando o user_id não existe na tabela users (ex: usuários novos ou temporários)
        "CREATE TABLE IF NOT EXISTS monetag_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            event_type ENUM('impression', 'click') NOT NULL,
            session_id VARCHAR(255),
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",Criar tabela active_sessions
        "CREATE TABLE IF NOT EXISTS active_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(255) UNIQUE NOT NULL,
            user_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        // 8. Criar tabela point_transactions
        "CREATE TABLE IF NOT EXISTS point_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            points INT NOT NULL,
            type ENUM('credit', 'debit') NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        // 9. Criar tabela system_settings
        "CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        // 10. Inserir configuração padrão de reset_time
        "INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('reset_time', '00:00:00')"
    ];
    
    // Executar cada statement
    foreach ($sql_statements as $index => $sql) {
        if ($conn->query($sql)) {
            $results[] = "Statement " . ($index + 1) . " executado com sucesso";
        } else {
            $results[] = "Erro no statement " . ($index + 1) . ": " . $conn->error;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Database initialized successfully!',
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

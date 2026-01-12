<?php
/**
 * Auto Reset Middleware
 * Verifica e reseta o ranking diário automaticamente
 */

/**
 * Verifica se precisa resetar o ranking e executa o reset se necessário
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @return void
 */
function checkAndResetRanking($conn) {
    // Definir timezone
    date_default_timezone_set('America/Sao_Paulo');
    
    // Verificar se existe a tabela de controle de reset
    $checkTable = $conn->query("SHOW TABLES LIKE 'ranking_reset_log'");
    
    if ($checkTable->num_rows === 0) {
        // Criar tabela de controle se não existir
        $conn->query("
            CREATE TABLE IF NOT EXISTS ranking_reset_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reset_date DATE NOT NULL,
                reset_time DATETIME NOT NULL,
                users_reset INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_reset_date (reset_date)
            )
        ");
    }
    
    // Obter data atual
    $currentDate = date('Y-m-d');
    
    // Verificar se já foi feito reset hoje
    $stmt = $conn->prepare("SELECT id FROM ranking_reset_log WHERE reset_date = ?");
    $stmt->bind_param("s", $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Já foi feito reset hoje, não fazer nada
        $stmt->close();
        return;
    }
    
    $stmt->close();
    
    // Fazer reset do ranking (zerar daily_points)
    $conn->query("UPDATE users SET daily_points = 0 WHERE daily_points > 0");
    $usersReset = $conn->affected_rows;
    
    // Registrar o reset no log
    $stmt = $conn->prepare("
        INSERT INTO ranking_reset_log (reset_date, reset_time, users_reset)
        VALUES (?, NOW(), ?)
    ");
    $stmt->bind_param("si", $currentDate, $usersReset);
    $stmt->execute();
    $stmt->close();
    
    error_log("auto_reset: Ranking reset executado. Data: {$currentDate}, Usuários resetados: {$usersReset}");
}
?>

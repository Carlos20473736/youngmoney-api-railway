<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    $results = [];
    
    // Verificar se a coluna created_at já existe
    $checkColumn = $conn->query("SHOW COLUMNS FROM spin_history LIKE 'created_at'");
    
    if ($checkColumn->num_rows == 0) {
        // Adicionar coluna created_at
        $conn->query("ALTER TABLE spin_history ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER spin_date");
        $results[] = "✅ Coluna 'created_at' adicionada à tabela spin_history";
        
        // Copiar dados de spin_date para created_at
        $conn->query("UPDATE spin_history SET created_at = spin_date WHERE created_at IS NULL");
        $results[] = "✅ Dados copiados de spin_date para created_at";
    } else {
        $results[] = "ℹ️ Coluna 'created_at' já existe na tabela spin_history";
    }
    
    // Verificar outras tabelas que podem precisar de created_at
    $tables = ['daily_checkin', 'point_transactions', 'withdrawals', 'referrals'];
    
    foreach ($tables as $table) {
        $checkTable = $conn->query("SHOW TABLES LIKE '$table'");
        if ($checkTable->num_rows > 0) {
            $checkCol = $conn->query("SHOW COLUMNS FROM $table LIKE 'created_at'");
            if ($checkCol->num_rows == 0) {
                $conn->query("ALTER TABLE $table ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                $results[] = "✅ Coluna 'created_at' adicionada à tabela $table";
            } else {
                $results[] = "ℹ️ Tabela $table já tem coluna created_at";
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Correções aplicadas com sucesso!',
        'results' => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

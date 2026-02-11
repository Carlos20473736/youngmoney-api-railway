<?php
/**
 * Migration: Migrate spin_history to user_spins
 * 
 * Copia dados de spin_history para user_spins
 * Marca todos como is_used = 1 (já foram usados)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../database.php';

try {
    $conn = getDbConnection();
    
    // Verificar se user_spins existe
    $checkTable = $conn->query("SHOW TABLES LIKE 'user_spins'");
    if ($checkTable->num_rows == 0) {
        throw new Exception("Tabela user_spins não existe. Execute create_user_spins_table.php primeiro!");
    }
    
    // Contar registros em spin_history
    $result = $conn->query("SELECT COUNT(*) as total FROM spin_history");
    $row = $result->fetch_assoc();
    $totalSpins = $row['total'] ?? 0;
    
    // Contar registros já migrados em user_spins
    $result = $conn->query("SELECT COUNT(*) as total FROM user_spins");
    $row = $result->fetch_assoc();
    $alreadyMigrated = $row['total'] ?? 0;
    
    if ($alreadyMigrated > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Dados já foram migrados anteriormente',
            'data' => [
                'total_spins_in_history' => $totalSpins,
                'already_migrated' => $alreadyMigrated,
                'status' => 'already_migrated'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // Copiar todos os registros de spin_history para user_spins
        // Marcar como is_used = 1 (já foram usados)
        $sql = "
            INSERT INTO user_spins (user_id, prize_value, is_used, used_at, created_at)
            SELECT user_id, prize_value, 1, created_at, created_at
            FROM spin_history
        ";
        
        if (!$conn->query($sql)) {
            throw new Exception("Erro ao migrar dados: " . $conn->error);
        }
        
        $migratedRows = $conn->affected_rows;
        
        // Commit
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Migração concluída com sucesso!',
            'data' => [
                'total_spins_migrated' => $migratedRows,
                'total_spins_in_history' => $totalSpins,
                'status' => 'migrated',
                'note' => 'Todos os giros foram marcados como is_used = 1'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>

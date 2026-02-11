<?php
/**
 * Migration: Add initial spins to users
 * 
 * Adiciona giros iniciais (não usados) para todos os usuários
 * Cada usuário recebe max_daily_spins giros
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../database.php';

try {
    $conn = getDbConnection();
    
    // Verificar se user_spins existe
    $checkTable = $conn->query("SHOW TABLES LIKE 'user_spins'");
    if ($checkTable->num_rows == 0) {
        throw new Exception("Tabela user_spins não existe!");
    }
    
    // Buscar max_daily_spins
    $result = $conn->query("
        SELECT setting_value FROM roulette_settings 
        WHERE setting_key = 'max_daily_spins'
    ");
    
    $maxDailySpins = 10; // padrão
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $maxDailySpins = (int)$row['setting_value'];
    }
    
    // Buscar todos os usuários
    $result = $conn->query("SELECT id FROM users");
    if (!$result) {
        throw new Exception("Erro ao buscar usuários: " . $conn->error);
    }
    
    $totalUsers = $result->num_rows;
    $spinsAdded = 0;
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        while ($row = $result->fetch_assoc()) {
            $userId = $row['id'];
            
            // Verificar se usuário já tem giros não usados
            $checkStmt = $conn->prepare("
                SELECT COUNT(*) as count FROM user_spins 
                WHERE user_id = ? AND is_used = 0
            ");
            $checkStmt->bind_param("i", $userId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $checkRow = $checkResult->fetch_assoc();
            $unusedSpins = (int)$checkRow['count'];
            $checkStmt->close();
            
            // Se não tem giros não usados, adicionar
            if ($unusedSpins == 0) {
                // Adicionar max_daily_spins giros
                for ($i = 0; $i < $maxDailySpins; $i++) {
                    $stmt = $conn->prepare("
                        INSERT INTO user_spins (user_id, prize_value, is_used, created_at)
                        VALUES (?, 0, 0, NOW())
                    ");
                    $stmt->bind_param("i", $userId);
                    if (!$stmt->execute()) {
                        throw new Exception("Erro ao adicionar giro: " . $stmt->error);
                    }
                    $stmt->close();
                    $spinsAdded++;
                }
            }
        }
        
        // Commit
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Giros iniciais adicionados com sucesso!',
            'data' => [
                'total_users' => $totalUsers,
                'spins_per_user' => $maxDailySpins,
                'total_spins_added' => $spinsAdded,
                'status' => 'completed'
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

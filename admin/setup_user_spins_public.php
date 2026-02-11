<?php
/**
 * Setup Público: user_spins
 * 
 * Endpoint: GET /admin/setup_user_spins_public.php?token=seu_token
 * 
 * Executa todas as migrações necessárias
 */

header('Content-Type: application/json; charset=utf-8');

// Verificar token de segurança
$token = $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$expectedToken = getenv('RESET_TOKEN') ?: 'ym_reset_roulette_scheduled_2024_secure';

if (strpos($token, 'Bearer ') === 0) {
    $token = substr($token, 7);
}

if (empty($token) || $token !== $expectedToken) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Token inválido ou não fornecido',
        'required_param' => '?token=seu_token_aqui'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../database.php';

$results = [];
$success = true;

try {
    $conn = getDbConnection();
    
    // ========================================
    // PASSO 1: Criar tabela user_spins
    // ========================================
    $results['step_1_create_table'] = [
        'name' => 'Criar tabela user_spins',
        'status' => 'processing'
    ];
    
    $checkTable = $conn->query("SHOW TABLES LIKE 'user_spins'");
    
    if ($checkTable->num_rows == 0) {
        $sql = "CREATE TABLE IF NOT EXISTS user_spins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            prize_value INT NOT NULL,
            is_used TINYINT DEFAULT 0,
            used_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_is_used (is_used),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$conn->query($sql)) {
            throw new Exception("Erro ao criar tabela: " . $conn->error);
        }
        
        $results['step_1_create_table']['status'] = 'success';
        $results['step_1_create_table']['message'] = 'Tabela criada com sucesso';
    } else {
        $results['step_1_create_table']['status'] = 'skipped';
        $results['step_1_create_table']['message'] = 'Tabela já existe';
    }
    
    // ========================================
    // PASSO 2: Migrar dados de spin_history
    // ========================================
    $results['step_2_migrate_data'] = [
        'name' => 'Migrar dados de spin_history',
        'status' => 'processing'
    ];
    
    $result = $conn->query("SELECT COUNT(*) as total FROM user_spins");
    $row = $result->fetch_assoc();
    $alreadyMigrated = $row['total'] ?? 0;
    
    if ($alreadyMigrated == 0) {
        $result = $conn->query("SELECT COUNT(*) as total FROM spin_history");
        $row = $result->fetch_assoc();
        $totalSpins = $row['total'] ?? 0;
        
        if ($totalSpins > 0) {
            $conn->begin_transaction();
            
            try {
                $sql = "
                    INSERT INTO user_spins (user_id, prize_value, is_used, used_at, created_at)
                    SELECT user_id, prize_value, 1, created_at, created_at
                    FROM spin_history
                ";
                
                if (!$conn->query($sql)) {
                    throw new Exception("Erro ao migrar: " . $conn->error);
                }
                
                $migratedRows = $conn->affected_rows;
                $conn->commit();
                
                $results['step_2_migrate_data']['status'] = 'success';
                $results['step_2_migrate_data']['message'] = "$migratedRows registros migrados";
                $results['step_2_migrate_data']['migrated_rows'] = $migratedRows;
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
        } else {
            $results['step_2_migrate_data']['status'] = 'skipped';
            $results['step_2_migrate_data']['message'] = 'Nenhum registro para migrar';
        }
    } else {
        $results['step_2_migrate_data']['status'] = 'skipped';
        $results['step_2_migrate_data']['message'] = "Dados já migrados ($alreadyMigrated registros)";
    }
    
    // ========================================
    // PASSO 3: Adicionar giros iniciais
    // ========================================
    $results['step_3_initial_spins'] = [
        'name' => 'Adicionar giros iniciais aos usuários',
        'status' => 'processing'
    ];
    
    $result = $conn->query("
        SELECT setting_value FROM roulette_settings 
        WHERE setting_key = 'max_daily_spins'
    ");
    
    $maxDailySpins = 10;
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $maxDailySpins = (int)$row['setting_value'];
    }
    
    $result = $conn->query("
        SELECT u.id FROM users u
        WHERE NOT EXISTS (
            SELECT 1 FROM user_spins us 
            WHERE us.user_id = u.id AND us.is_used = 0
        )
    ");
    
    $usersNeedingSpins = $result->num_rows;
    $spinsAdded = 0;
    
    if ($usersNeedingSpins > 0) {
        $conn->begin_transaction();
        
        try {
            while ($row = $result->fetch_assoc()) {
                $userId = $row['id'];
                
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
            
            $conn->commit();
            
            $results['step_3_initial_spins']['status'] = 'success';
            $results['step_3_initial_spins']['message'] = "$spinsAdded giros adicionados para $usersNeedingSpins usuários";
            $results['step_3_initial_spins']['users_updated'] = $usersNeedingSpins;
            $results['step_3_initial_spins']['spins_added'] = $spinsAdded;
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    } else {
        $results['step_3_initial_spins']['status'] = 'skipped';
        $results['step_3_initial_spins']['message'] = 'Todos os usuários já têm giros';
    }
    
    $conn->close();
    
    // Retornar resultado final
    echo json_encode([
        'success' => true,
        'message' => 'Setup completo executado com sucesso!',
        'steps' => $results,
        'summary' => [
            'max_daily_spins' => $maxDailySpins,
            'timestamp' => date('Y-m-d H:i:s'),
            'timezone' => 'America/Sao_Paulo'
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'steps' => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>

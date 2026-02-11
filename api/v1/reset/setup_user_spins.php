<?php
/**
 * Setup Rápido: user_spins (Versão Otimizada)
 * 
 * Endpoint: GET /admin/setup_user_spins_fast.php?token=seu_token
 * 
 * Versão otimizada com:
 * - Sem transações longas
 * - Bulk inserts
 * - Timeout maior
 * - Feedback em tempo real
 */

set_time_limit(300); // 5 minutos
header('Content-Type: application/json; charset=utf-8');
header('X-Accel-Buffering: no'); // Desabilitar buffering

// Verificar token
$token = $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$expectedToken = getenv('RESET_TOKEN') ?: 'ym_reset_roulette_scheduled_2024_secure';

if (strpos($token, 'Bearer ') === 0) {
    $token = substr($token, 7);
}

if (empty($token) || $token !== $expectedToken) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Token inválido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../database.php';

function log_step($message) {
    echo json_encode(['status' => 'progress', 'message' => $message]) . "\n";
    flush();
}

try {
    $conn = getDbConnection();
    
    log_step("Iniciando setup...");
    
    // ========================================
    // PASSO 1: Criar tabela
    // ========================================
    log_step("Passo 1: Verificando tabela user_spins...");
    
    $checkTable = $conn->query("SHOW TABLES LIKE 'user_spins'");
    
    if ($checkTable->num_rows == 0) {
        log_step("Criando tabela user_spins...");
        
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
        
        log_step("✅ Tabela user_spins criada!");
    } else {
        log_step("✅ Tabela user_spins já existe");
    }
    
    // ========================================
    // PASSO 2: Migrar dados
    // ========================================
    log_step("Passo 2: Verificando dados para migrar...");
    
    $result = $conn->query("SELECT COUNT(*) as total FROM user_spins");
    $row = $result->fetch_assoc();
    $alreadyMigrated = $row['total'] ?? 0;
    
    if ($alreadyMigrated == 0) {
        $result = $conn->query("SELECT COUNT(*) as total FROM spin_history");
        $row = $result->fetch_assoc();
        $totalSpins = $row['total'] ?? 0;
        
        if ($totalSpins > 0) {
            log_step("Migrando $totalSpins registros de spin_history...");
            
            $sql = "
                INSERT INTO user_spins (user_id, prize_value, is_used, used_at, created_at)
                SELECT user_id, prize_value, 1, created_at, created_at
                FROM spin_history
            ";
            
            if (!$conn->query($sql)) {
                throw new Exception("Erro ao migrar: " . $conn->error);
            }
            
            $migratedRows = $conn->affected_rows;
            log_step("✅ $migratedRows registros migrados!");
        } else {
            log_step("✅ Nenhum registro para migrar");
        }
    } else {
        log_step("✅ Dados já foram migrados ($alreadyMigrated registros)");
    }
    
    // ========================================
    // PASSO 3: Adicionar giros iniciais
    // ========================================
    log_step("Passo 3: Adicionando giros iniciais...");
    
    // Buscar max_daily_spins
    $result = $conn->query("
        SELECT setting_value FROM roulette_settings 
        WHERE setting_key = 'max_daily_spins'
    ");
    
    $maxDailySpins = 10;
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $maxDailySpins = (int)$row['setting_value'];
    }
    
    log_step("Max daily spins: $maxDailySpins");
    
    // Buscar usuários sem giros
    $result = $conn->query("
        SELECT u.id FROM users u
        WHERE NOT EXISTS (
            SELECT 1 FROM user_spins us 
            WHERE us.user_id = u.id AND us.is_used = 0
        )
        ORDER BY u.id
    ");
    
    $usersNeedingSpins = $result->num_rows;
    
    if ($usersNeedingSpins > 0) {
        log_step("Encontrados $usersNeedingSpins usuários sem giros");
        log_step("Adicionando giros (isso pode levar um tempo)...");
        
        $spinsAdded = 0;
        $batchSize = 100; // Processar em lotes
        $userCount = 0;
        
        while ($row = $result->fetch_assoc()) {
            $userId = $row['id'];
            $userCount++;
            
            // Usar bulk insert para cada usuário
            $values = [];
            for ($i = 0; $i < $maxDailySpins; $i++) {
                $values[] = "($userId, 0, 0, NOW())";
            }
            
            $sql = "INSERT INTO user_spins (user_id, prize_value, is_used, created_at) VALUES " . implode(",", $values);
            
            if (!$conn->query($sql)) {
                log_step("⚠️ Erro ao adicionar giros para usuário $userId: " . $conn->error);
            } else {
                $spinsAdded += $maxDailySpins;
            }
            
            // Log de progresso a cada 10 usuários
            if ($userCount % 10 == 0) {
                log_step("Progresso: $userCount/$usersNeedingSpins usuários processados");
            }
        }
        
        log_step("✅ $spinsAdded giros adicionados para $usersNeedingSpins usuários!");
    } else {
        log_step("✅ Todos os usuários já têm giros");
    }
    
    // Resultado final
    log_step("=== SETUP COMPLETO COM SUCESSO! ===");
    log_step("Timestamp: " . date('Y-m-d H:i:s'));
    log_step("Seu sistema de giros está pronto!");
    
    echo json_encode([
        'success' => true,
        'message' => 'Setup completo!',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE) . "\n";
    
} catch (Exception $e) {
    log_step("❌ ERRO: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE) . "\n";
}
?>

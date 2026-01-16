<?php
/**
 * Endpoint de Reset de Levels do Jogo Candy
 * 
 * Endpoint: GET /api/v1/cron/reset_levels.php?token=ym_reset_levels_2024_secure_xyz
 * 
 * Função: Reseta os levels de todos os usuários no jogo Candy
 * 
 * O que é resetado:
 * - game_levels: level = 1, highest_level = 1, last_level_score = 0, total_score = 0
 * - candy_level_progress: current_level = 1, level_score = 0
 * 
 * Segurança:
 * - Token obrigatório via query parameter
 * - Validação de conexão com banco de dados
 * - Transação para garantir consistência
 * 
 * IMPORTANTE: Este endpoint NÃO executa automaticamente.
 * Deve ser chamado manualmente ou via cron-job.org quando necessário.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar token de segurança
$token = $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$expectedToken = 'ym_reset_levels_2024_secure_xyz';

// Remover "Bearer " se presente
if (strpos($token, 'Bearer ') === 0) {
    $token = substr($token, 7);
}

if (empty($token) || $token !== $expectedToken) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Token inválido ou não fornecido',
        'required_param' => '?token=ym_reset_levels_2024_secure_xyz'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Configurar timezone para Brasília
date_default_timezone_set('America/Sao_Paulo');

// Incluir arquivo de conexão
require_once __DIR__ . '/../../../database.php';

try {
    // Usar a função de conexão padrão
    $mysqli = getDbConnection();
    
    if (!$mysqli) {
        throw new Exception("Falha ao conectar ao banco de dados");
    }
    
    $mysqli->set_charset("utf8mb4");
    
    // Obter data e hora atual
    $current_date = date('Y-m-d');
    $current_datetime = date('Y-m-d H:i:s');
    
    // Iniciar transação
    $mysqli->begin_transaction();
    
    try {
        // ============================================
        // PASSO 1: Contar usuários afetados ANTES do reset
        // ============================================
        
        // Contar usuários na tabela game_levels
        $countGameLevels = $mysqli->query("SELECT COUNT(*) as total FROM game_levels");
        $gameLevelsCount = $countGameLevels ? $countGameLevels->fetch_assoc()['total'] : 0;
        
        // Contar usuários na tabela candy_level_progress
        $countCandyProgress = $mysqli->query("SELECT COUNT(*) as total FROM candy_level_progress");
        $candyProgressCount = $countCandyProgress ? $countCandyProgress->fetch_assoc()['total'] : 0;
        
        // Obter estatísticas antes do reset
        $statsQuery = $mysqli->query("
            SELECT 
                MAX(level) as max_level,
                MAX(highest_level) as max_highest_level,
                AVG(level) as avg_level,
                SUM(total_score) as total_score_all
            FROM game_levels
        ");
        $statsBefore = $statsQuery ? $statsQuery->fetch_assoc() : null;
        
        // ============================================
        // PASSO 2: Resetar tabela game_levels
        // ============================================
        $resetGameLevels = $mysqli->query("
            UPDATE game_levels 
            SET 
                level = 1,
                highest_level = 1,
                last_level_score = 0,
                total_score = 0,
                updated_at = NOW()
        ");
        
        if (!$resetGameLevels) {
            throw new Exception("Erro ao resetar game_levels: " . $mysqli->error);
        }
        
        $gameLevelsAffected = $mysqli->affected_rows;
        
        // ============================================
        // PASSO 3: Resetar tabela candy_level_progress
        // ============================================
        $resetCandyProgress = $mysqli->query("
            UPDATE candy_level_progress 
            SET 
                current_level = 1,
                level_score = 0
        ");
        
        // Se a tabela não existir, não é erro crítico
        $candyProgressAffected = $mysqli->affected_rows;
        
        // ============================================
        // PASSO 4: Registrar o reset no log
        // ============================================
        
        // Criar tabela de logs se não existir
        $mysqli->query("
            CREATE TABLE IF NOT EXISTS reset_levels_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reset_date DATE NOT NULL,
                reset_datetime DATETIME NOT NULL,
                game_levels_affected INT NOT NULL DEFAULT 0,
                candy_progress_affected INT NOT NULL DEFAULT 0,
                max_level_before INT DEFAULT NULL,
                max_highest_level_before INT DEFAULT NULL,
                total_score_before BIGINT DEFAULT NULL,
                token_used VARCHAR(100) DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_reset_date (reset_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Inserir log do reset
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $mysqli->prepare("
            INSERT INTO reset_levels_log 
            (reset_date, reset_datetime, game_levels_affected, candy_progress_affected, 
             max_level_before, max_highest_level_before, total_score_before, token_used, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $maxLevelBefore = $statsBefore['max_level'] ?? 0;
        $maxHighestBefore = $statsBefore['max_highest_level'] ?? 0;
        $totalScoreBefore = $statsBefore['total_score_all'] ?? 0;
        
        $stmt->bind_param(
            "ssiiiidss",
            $current_date,
            $current_datetime,
            $gameLevelsAffected,
            $candyProgressAffected,
            $maxLevelBefore,
            $maxHighestBefore,
            $totalScoreBefore,
            $expectedToken,
            $ipAddress
        );
        $stmt->execute();
        $stmt->close();
        
        // Commit da transação
        $mysqli->commit();
        
        // ============================================
        // RESPOSTA DE SUCESSO
        // ============================================
        echo json_encode([
            'success' => true,
            'message' => 'Levels resetados com sucesso!',
            'data' => [
                'reset_type' => 'game_levels',
                'description' => 'Todos os usuários tiveram seus levels resetados para o nível 1',
                'reset_date' => $current_date,
                'reset_datetime' => $current_datetime,
                'timezone' => 'America/Sao_Paulo (GMT-3)',
                'timestamp' => time(),
                'statistics' => [
                    'game_levels' => [
                        'total_records' => (int)$gameLevelsCount,
                        'records_affected' => (int)$gameLevelsAffected,
                        'reset_to' => [
                            'level' => 1,
                            'highest_level' => 1,
                            'last_level_score' => 0,
                            'total_score' => 0
                        ]
                    ],
                    'candy_level_progress' => [
                        'total_records' => (int)$candyProgressCount,
                        'records_affected' => (int)$candyProgressAffected,
                        'reset_to' => [
                            'current_level' => 1,
                            'level_score' => 0
                        ]
                    ],
                    'before_reset' => [
                        'max_level' => (int)($statsBefore['max_level'] ?? 0),
                        'max_highest_level' => (int)($statsBefore['max_highest_level'] ?? 0),
                        'avg_level' => round((float)($statsBefore['avg_level'] ?? 0), 2),
                        'total_score_all_users' => (int)($statsBefore['total_score_all'] ?? 0)
                    ]
                ]
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        $mysqli->rollback();
        throw $e;
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao executar reset de levels',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

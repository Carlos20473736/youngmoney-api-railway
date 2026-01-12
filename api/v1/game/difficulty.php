<?php
/**
 * Game Difficulty API Endpoint
 * 
 * Retorna os parâmetros de dificuldade para cada level do jogo Candy
 * A dificuldade aumenta progressivamente a cada level
 * 
 * GET /api/v1/game/difficulty.php?level=1
 * 
 * Parâmetros retornados:
 * - time_limit: Tempo limite em segundos para completar o level
 * - target_score: Pontuação mínima necessária para passar de level
 * - moves_limit: Número máximo de movimentos (0 = ilimitado, baseado em tempo)
 * - candy_types: Quantidade de tipos diferentes de candy (mais tipos = mais difícil)
 * - special_candy_chance: Chance de aparecer candy especial (0-100)
 * - bomb_frequency: Frequência de bombas/obstáculos (0-100)
 */

// Tratamento de erros
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "PHP Error: $errstr in $errfile:$errline"]);
    exit;
});

// Includes para verificação de manutenção
require_once __DIR__ . '/../../../database.php';
require_once __DIR__ . '/../middleware/MaintenanceCheck.php';

// Headers CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-ID');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ========================================
// VERIFICAÇÃO DE MANUTENÇÃO E VERSÃO
// ========================================
$conn = getDbConnection();
$userEmail = $_GET['email'] ?? null;
$appVersion = $_GET['app_version'] ?? $_SERVER['HTTP_X_APP_VERSION'] ?? null;
checkMaintenanceAndVersion($conn, $userEmail, $appVersion);
$conn->close();
// ========================================

// Obter level da query string
$level = isset($_GET['level']) ? intval($_GET['level']) : 1;

if ($level < 1) {
    $level = 1;
}

/**
 * Função para calcular dificuldade baseada no level
 * A dificuldade aumenta progressivamente
 */
function getDifficultyForLevel($level) {
    // ========================================
    // CONFIGURAÇÕES BASE (Level 1)
    // ========================================
    $baseTimeLimit = 60;        // 60 segundos no level 1
    $baseTargetScore = 1000;    // 1000 pontos para passar no level 1
    $baseMovesLimit = 0;        // 0 = sem limite de movimentos (baseado em tempo)
    $baseCandyTypes = 5;        // 5 tipos de candy no level 1
    $baseSpecialChance = 15;    // 15% de chance de candy especial
    $baseBombFrequency = 0;     // Sem bombas no level 1
    
    // ========================================
    // CÁLCULO DE DIFICULDADE PROGRESSIVA
    // ========================================
    
    // Tempo diminui a cada 5 levels (mínimo 20 segundos)
    $timeReduction = floor(($level - 1) / 5) * 5; // Reduz 5 segundos a cada 5 levels
    $timeLimit = max(20, $baseTimeLimit - $timeReduction);
    
    // Meta de pontos aumenta 500 a cada level
    $targetScore = $baseTargetScore + (($level - 1) * 500);
    
    // A cada 10 levels, aumenta mais rápido
    if ($level > 10) {
        $targetScore += (($level - 10) * 300);
    }
    if ($level > 20) {
        $targetScore += (($level - 20) * 500);
    }
    if ($level > 50) {
        $targetScore += (($level - 50) * 1000);
    }
    
    // Tipos de candy aumentam a cada 10 levels (máximo 8)
    $candyTypes = min(8, $baseCandyTypes + floor(($level - 1) / 10));
    
    // Chance de candy especial diminui levemente (mínimo 5%)
    $specialChance = max(5, $baseSpecialChance - floor(($level - 1) / 10) * 2);
    
    // Bombas/obstáculos aparecem a partir do level 5
    $bombFrequency = 0;
    if ($level >= 5) {
        $bombFrequency = min(30, ($level - 4) * 2); // Máximo 30%
    }
    
    // ========================================
    // BÔNUS E MULTIPLICADORES
    // ========================================
    
    // Multiplicador de pontos aumenta em levels altos
    $pointsMultiplier = 1.0;
    if ($level >= 10) $pointsMultiplier = 1.1;
    if ($level >= 20) $pointsMultiplier = 1.2;
    if ($level >= 30) $pointsMultiplier = 1.3;
    if ($level >= 50) $pointsMultiplier = 1.5;
    if ($level >= 100) $pointsMultiplier = 2.0;
    
    // Determinar tier de dificuldade
    $difficultyTier = 'easy';
    if ($level >= 10) $difficultyTier = 'normal';
    if ($level >= 25) $difficultyTier = 'hard';
    if ($level >= 50) $difficultyTier = 'expert';
    if ($level >= 100) $difficultyTier = 'master';
    
    return [
        'level' => $level,
        'difficulty_tier' => $difficultyTier,
        'time_limit' => $timeLimit,
        'target_score' => $targetScore,
        'moves_limit' => $baseMovesLimit, // 0 = ilimitado
        'candy_types' => $candyTypes,
        'special_candy_chance' => $specialChance,
        'bomb_frequency' => $bombFrequency,
        'points_multiplier' => $pointsMultiplier,
        'description' => getDifficultyDescription($level, $difficultyTier)
    ];
}

/**
 * Retorna descrição amigável da dificuldade
 */
function getDifficultyDescription($level, $tier) {
    $descriptions = [
        'easy' => 'Nível fácil - Aprenda os básicos!',
        'normal' => 'Nível normal - O desafio começa!',
        'hard' => 'Nível difícil - Prepare-se!',
        'expert' => 'Nível expert - Apenas os melhores!',
        'master' => 'Nível mestre - Lendário!'
    ];
    
    return $descriptions[$tier] ?? 'Boa sorte!';
}

// ========================================
// RESPOSTA
// ========================================

$difficulty = getDifficultyForLevel($level);

echo json_encode([
    'success' => true,
    'data' => $difficulty
]);
?>

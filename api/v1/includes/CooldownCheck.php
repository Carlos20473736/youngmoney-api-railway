<?php
/**
 * Verificação de Cooldown para Ranking
 * 
 * Função para verificar se um usuário está em cooldown e bloquear acúmulo de daily_points
 * 
 * Cooldown:
 * - Top 1-3: 24 horas - BLOQUEIA acúmulo de pontos
 * - Top 4-10: 2 horas - APENAS bloqueio visual (pontos continuam acumulando)
 */

/**
 * Verifica se um usuário está em cooldown de ranking
 * 
 * @param mysqli $conn - Conexão com banco de dados
 * @param int $userId - ID do usuário
 * @return array - ['in_cooldown' => bool, 'cooldown_until' => datetime, 'position' => int, 'cooldown_hours' => int]
 */
function checkUserCooldown($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT 
            id,
            position,
            cooldown_days as cooldown_hours,
            cooldown_until,
            CASE 
                WHEN cooldown_until > NOW() THEN 1
                ELSE 0
            END as is_active
        FROM ranking_cooldowns 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    if (!$stmt) {
        return ['in_cooldown' => false, 'error' => 'Database error'];
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $cooldown = $result->fetch_assoc();
    $stmt->close();
    
    if (!$cooldown) {
        return ['in_cooldown' => false];
    }
    
    if ($cooldown['is_active']) {
        return [
            'in_cooldown' => true,
            'cooldown_until' => $cooldown['cooldown_until'],
            'position' => $cooldown['position'],
            'cooldown_hours' => $cooldown['cooldown_hours'],
            'time_remaining' => getTimeRemaining($cooldown['cooldown_until'])
        ];
    }
    
    return ['in_cooldown' => false];
}

/**
 * Calcula tempo restante de cooldown
 * 
 * @param string $cooldownUntil - Data/hora até quando o cooldown dura
 * @return array - ['hours' => int, 'minutes' => int, 'seconds' => int]
 */
function getTimeRemaining($cooldownUntil) {
    $now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
    $until = new DateTime($cooldownUntil, new DateTimeZone('America/Sao_Paulo'));
    
    if ($until <= $now) {
        return ['hours' => 0, 'minutes' => 0, 'seconds' => 0];
    }
    
    $diff = $until->diff($now);
    
    return [
        'hours' => $diff->h + ($diff->d * 24),
        'minutes' => $diff->i,
        'seconds' => $diff->s,
        'total_seconds' => $until->getTimestamp() - $now->getTimestamp()
    ];
}

/**
 * Bloqueia adicao de daily_points APENAS para Top 1-3
 * Top 4-10 podem continuar acumulando pontos normalmente
 * 
 * @param mysqli $conn - Conexao com banco de dados
 * @param int $userId - ID do usuario
 * @param int $pointsToAdd - Pontos que seriam adicionados
 * @param string $description - Descricao da atividade
 * @return array - ['allowed' => bool, 'reason' => string, 'cooldown_info' => array]
 */
function shouldBlockDailyPoints($conn, $userId, $pointsToAdd, $description) {
    $cooldownCheck = checkUserCooldown($conn, $userId);
    
    if ($cooldownCheck['in_cooldown']) {
        // APENAS bloquear para posicoes 1-3 (Top 3)
        // Posicoes 4-10 podem continuar acumulando pontos
        if ($cooldownCheck['position'] >= 1 && $cooldownCheck['position'] <= 3) {
            // Registrar tentativa de acumulo durante cooldown
            $stmt = $conn->prepare("
                INSERT INTO cooldown_violations (user_id, position, attempted_points, description, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("iis", $userId, $cooldownCheck['position'], $pointsToAdd, $description);
            $stmt->execute();
            $stmt->close();
            
            return [
                'allowed' => false,
                'reason' => 'Usuario esta em cooldown de ranking (Top 3)',
                'cooldown_info' => [
                    'position' => $cooldownCheck['position'],
                    'cooldown_hours' => $cooldownCheck['cooldown_hours'],
                    'cooldown_until' => $cooldownCheck['cooldown_until'],
                    'time_remaining' => $cooldownCheck['time_remaining']
                ]
            ];
        } else {
            // Posicoes 4-10: Permitir acumulo de pontos (apenas bloqueio visual no ranking)
            return ['allowed' => true];
        }
    }
    
    return ['allowed' => true];
}

/**
 * Cria tabela de violacoes de cooldown se nao existir
 * 
 * @param mysqli $conn - Conexao com banco de dados
 */
function createCooldownViolationsTable($conn) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS cooldown_violations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            position INT NOT NULL COMMENT 'Posicao que o usuario estava quando entrou em cooldown',
            attempted_points INT NOT NULL COMMENT 'Pontos que tentou acumular',
            description VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_violations (user_id, created_at),
            INDEX idx_cooldown_violations (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

?>

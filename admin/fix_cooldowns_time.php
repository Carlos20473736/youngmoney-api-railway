<?php
/**
 * Fix Cooldowns Time - Corrige o tempo de cooldown dos vencedores do ranking
 * 
 * Regras:
 * - Top 1, 2, 3: 24 horas (1 dia) de cooldown
 * - Top 4 a 10: 2 horas de cooldown
 * 
 * Este script recalcula o cooldown_until baseado no created_at de cada registro
 */

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

try {
    $conn = getDbConnection();
    
    $now = date('Y-m-d H:i:s');
    
    // Cooldown por posição (em horas)
    // Top 1-3: 24 horas | Top 4-10: 2 horas
    $cooldownHours = [
        1 => 24,
        2 => 24,
        3 => 24,
        4 => 2,
        5 => 2,
        6 => 2,
        7 => 2,
        8 => 2,
        9 => 2,
        10 => 2
    ];
    
    // Buscar todos os cooldowns ativos
    $stmt = $conn->prepare("
        SELECT 
            rc.id,
            rc.user_id,
            rc.position,
            rc.cooldown_days,
            rc.cooldown_until,
            rc.created_at,
            u.name
        FROM ranking_cooldowns rc
        JOIN users u ON u.id = rc.user_id
        WHERE rc.cooldown_until > ?
        ORDER BY rc.position ASC
    ");
    $stmt->bind_param("s", $now);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $updated = [];
    $skipped = [];
    
    while ($row = $result->fetch_assoc()) {
        $position = (int)$row['position'];
        $correctHours = $cooldownHours[$position] ?? 2;
        
        // Calcular novo cooldown_until baseado no created_at
        $createdAt = new DateTime($row['created_at']);
        $newCooldownUntil = clone $createdAt;
        $newCooldownUntil->modify("+{$correctHours} hours");
        $newCooldownUntilStr = $newCooldownUntil->format('Y-m-d H:i:s');
        
        // Verificar se precisa atualizar
        $currentCooldownUntil = $row['cooldown_until'];
        
        if ($currentCooldownUntil !== $newCooldownUntilStr) {
            // Atualizar o cooldown
            $updateStmt = $conn->prepare("
                UPDATE ranking_cooldowns 
                SET cooldown_until = ?, cooldown_days = ?
                WHERE id = ?
            ");
            $updateStmt->bind_param("sii", $newCooldownUntilStr, $correctHours, $row['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            $updated[] = [
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'name' => $row['name'],
                'position' => $position,
                'old_cooldown_until' => $currentCooldownUntil,
                'new_cooldown_until' => $newCooldownUntilStr,
                'correct_hours' => $correctHours,
                'created_at' => $row['created_at']
            ];
        } else {
            $skipped[] = [
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'name' => $row['name'],
                'position' => $position,
                'cooldown_until' => $currentCooldownUntil,
                'reason' => 'Já está correto'
            ];
        }
    }
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Cooldowns corrigidos com sucesso!',
        'rules' => [
            'top_1_to_3' => '24 horas',
            'top_4_to_10' => '2 horas'
        ],
        'updated_count' => count($updated),
        'skipped_count' => count($skipped),
        'updated' => $updated,
        'skipped' => $skipped,
        'executed_at' => $now
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

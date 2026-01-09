<?php
/**
 * Test Cooldown Expiry - Simula expiração de cooldown para teste
 * Endpoint: /admin/test_cooldown_expiry.php?user_id=X&action=expire|restore
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // Configurar timezone
    date_default_timezone_set('America/Sao_Paulo');
    $now = date('Y-m-d H:i:s');
    
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $action = isset($_GET['action']) ? $_GET['action'] : 'status';
    
    if ($action === 'expire' && $userId > 0) {
        // Expirar cooldown do usuário (definir cooldown_until para o passado)
        $pastDate = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $stmt = $conn->prepare("
            UPDATE ranking_cooldowns 
            SET cooldown_until = ?
            WHERE user_id = ? AND cooldown_until > ?
        ");
        $stmt->bind_param("sis", $pastDate, $userId, $now);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'action' => 'expire',
            'user_id' => $userId,
            'cooldowns_expired' => $affected,
            'message' => "Cooldown do usuário $userId foi expirado manualmente"
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } elseif ($action === 'restore' && $userId > 0) {
        // Restaurar cooldown do usuário (definir cooldown_until para o futuro)
        $futureDate = date('Y-m-d H:i:s', strtotime('+2 days'));
        
        $stmt = $conn->prepare("
            UPDATE ranking_cooldowns 
            SET cooldown_until = ?
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->bind_param("si", $futureDate, $userId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'action' => 'restore',
            'user_id' => $userId,
            'cooldowns_restored' => $affected,
            'new_cooldown_until' => $futureDate,
            'message' => "Cooldown do usuário $userId foi restaurado"
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } else {
        // Mostrar status do usuário
        if ($userId > 0) {
            $stmt = $conn->prepare("
                SELECT 
                    rc.*,
                    u.name,
                    u.email,
                    u.daily_points,
                    u.pix_key IS NOT NULL AND u.pix_key != '' as has_pix
                FROM ranking_cooldowns rc
                JOIN users u ON rc.user_id = u.id
                WHERE rc.user_id = ?
                ORDER BY rc.created_at DESC
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $cooldowns = [];
            while ($row = $result->fetch_assoc()) {
                $row['is_active'] = $row['cooldown_until'] > $now;
                $cooldowns[] = $row;
            }
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'user_id' => $userId,
                'server_time' => $now,
                'cooldowns' => $cooldowns
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => true,
                'usage' => [
                    'status' => '/admin/test_cooldown_expiry.php?user_id=X',
                    'expire' => '/admin/test_cooldown_expiry.php?user_id=X&action=expire',
                    'restore' => '/admin/test_cooldown_expiry.php?user_id=X&action=restore'
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

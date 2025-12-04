<?php
/**
 * Endpoint de Configurações do Sistema
 * Permite buscar e atualizar configurações, incluindo horário de reset diário e roleta
 */

require_once __DIR__ . '/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // ============================================
        // GET - Buscar configurações
        // ============================================
        
        // Buscar configurações do sistema
        $stmt = $conn->prepare("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE setting_key IN ('reset_time', 'min_withdrawal', 'max_withdrawal', 'max_daily_spins')
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Buscar valores da roleta (se houver)
        $prize_values = [];
        try {
            $prizeStmt = $conn->query("SELECT prize_key, prize_value FROM roulette_prizes");
            if ($prizeStmt) {
                while ($row = $prizeStmt->fetch_assoc()) {
                    $prize_values[$row['prize_key']] = (float)$row['prize_value'];
                }
            }
        } catch (Exception $e) {
            // Tabela não existe, usar valores vazios
            $prize_values = [];
        }
        
        // Buscar valores rápidos de saque
        $quick_withdrawal_values = [];
        try {
            $quickStmt = $conn->query("
                SELECT value_amount 
                FROM withdrawal_quick_values 
                WHERE is_active = 1 
                ORDER BY display_order ASC
            ");
            if ($quickStmt) {
                while ($row = $quickStmt->fetch_assoc()) {
                    $quick_withdrawal_values[] = (float)$row['value_amount'];
                }
            }
        } catch (Exception $e) {
            // Tabela não existe, usar valores padrão
            $quick_withdrawal_values = [1, 10, 20, 50, 100];
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'reset_time' => $settings['reset_time'] ?? '21:00',
                'max_daily_spins' => (int)($settings['max_daily_spins'] ?? 10),
                'withdrawal_limits' => [
                    'min' => (int)($settings['min_withdrawal'] ?? 10),
                    'max' => (int)($settings['max_withdrawal'] ?? 1000)
                ],
                'prize_values' => $prize_values,
                'quick_withdrawal_values' => $quick_withdrawal_values
            ]
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // ============================================
        // POST - Atualizar configurações
        // ============================================
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            throw new Exception('Dados inválidos');
        }
        
        $conn->begin_transaction();
        
        try {
            // Atualizar horário de reset
            if (isset($data['reset_time'])) {
                // Validar formato de hora (HH:MM)
                if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['reset_time'])) {
                    throw new Exception('Formato de hora inválido. Use HH:MM (ex: 21:00)');
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES ('reset_time', ?)
                    ON DUPLICATE KEY UPDATE 
                        setting_value = ?,
                        updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->bind_param('ss', $data['reset_time'], $data['reset_time']);
                $stmt->execute();
            }

            // Atualizar max_daily_spins
            if (isset($data['max_daily_spins'])) {
                $spins = (string)$data['max_daily_spins'];
                $stmt = $conn->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES ('max_daily_spins', ?)
                    ON DUPLICATE KEY UPDATE 
                        setting_value = ?,
                        updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->bind_param('ss', $spins, $spins);
                $stmt->execute();
            }
            
            // Atualizar limites de saque
            if (isset($data['withdrawal_limits'])) {
                if (isset($data['withdrawal_limits']['min'])) {
                    $minValue = (string)$data['withdrawal_limits']['min'];
                    $stmt = $conn->prepare("
                        INSERT INTO system_settings (setting_key, setting_value) 
                        VALUES ('min_withdrawal', ?)
                        ON DUPLICATE KEY UPDATE setting_value = ?
                    ");
                    $stmt->bind_param('ss', $minValue, $minValue);
                    $stmt->execute();
                }
                
                if (isset($data['withdrawal_limits']['max'])) {
                    $maxValue = (string)$data['withdrawal_limits']['max'];
                    $stmt = $conn->prepare("
                        INSERT INTO system_settings (setting_key, setting_value) 
                        VALUES ('max_withdrawal', ?)
                        ON DUPLICATE KEY UPDATE setting_value = ?
                    ");
                    $stmt->bind_param('ss', $maxValue, $maxValue);
                    $stmt->execute();
                }
            }
            
            // Atualizar valores da roleta
            if (isset($data['prize_values']) && is_array($data['prize_values'])) {
                foreach ($data['prize_values'] as $key => $value) {
                    try {
                        // Usar INSERT ON DUPLICATE KEY UPDATE para garantir que salve mesmo se a chave não existir
                        $stmt = $conn->prepare("
                            INSERT INTO roulette_prizes (prize_key, prize_value) 
                            VALUES (?, ?)
                            ON DUPLICATE KEY UPDATE prize_value = ?
                        ");
                        $stmt->bind_param('sdd', $key, $value, $value);
                        $stmt->execute();
                    } catch (Exception $e) {
                        error_log("Roulette table error: " . $e->getMessage());
                    }
                }
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'data' => ['success' => true]
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } else {
        throw new Exception('Método não permitido');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

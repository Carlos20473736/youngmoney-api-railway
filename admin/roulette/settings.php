<?php
error_reporting(E_ERROR | E_PARSE);
require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../database.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Obter configurações da roleta
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("SELECT setting_key, setting_value, description FROM roulette_settings ORDER BY setting_key");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = [
                'value' => (int)$row['setting_value'],
                'description' => $row['description']
            ];
        }
        
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'data' => $settings
        ]);
        
    } elseif ($method === 'POST' || $method === 'PUT') {
        // Atualizar configurações da roleta
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['prizes']) || !is_array($input['prizes'])) {
            throw new Exception('Campo "prizes" é obrigatório e deve ser um array');
        }
        
        $conn = getDbConnection();
        $conn->begin_transaction();
        
        try {
            $stmt = $conn->prepare("
                INSERT INTO roulette_settings (setting_key, setting_value, description) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    description = VALUES(description)
            ");
            
            $updatedCount = 0;
            
            foreach ($input['prizes'] as $key => $value) {
                // Validar que a chave é prize_1 até prize_8
                if (!preg_match('/^prize_[1-8]$/', $key)) {
                    throw new Exception("Chave inválida: $key. Use prize_1 até prize_8");
                }
                
                // Validar que o valor é um número positivo
                if (!is_numeric($value) || $value < 0) {
                    throw new Exception("Valor inválido para $key: deve ser um número positivo");
                }
                
                $prizeNumber = substr($key, -1);
                $description = "Prêmio $prizeNumber da roleta (em pontos)";
                
                $stmt->bind_param('sss', $key, $value, $description);
                $stmt->execute();
                $updatedCount++;
            }
            
            // Atualizar maxDailySpins se fornecido
            if (isset($input['max_daily_spins'])) {
                $maxSpins = (int)$input['max_daily_spins'];
                
                // Validar que é um número positivo
                if ($maxSpins < 1) {
                    throw new Exception("max_daily_spins deve ser no mínimo 1");
                }
                
                $key = 'max_daily_spins';
                $description = 'Número máximo de giros diários permitidos';
                
                $stmt->bind_param('sss', $key, $maxSpins, $description);
                $stmt->execute();
                $updatedCount++;
            }
            
            $stmt->close();
            $conn->commit();
            $conn->close();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'updated_count' => $updatedCount,
                    'message' => 'Configurações da roleta atualizadas com sucesso'
                ]
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            $conn->close();
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

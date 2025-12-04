<?php
/**
 * Endpoint de Gerenciamento de Valores Rápidos de Saque
 * Permite buscar, adicionar, atualizar e remover valores rápidos
 */

require_once __DIR__ . '/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // ============================================
        // GET - Buscar valores rápidos
        // ============================================
        
        $stmt = $conn->prepare("
            SELECT id, value_amount, is_active, display_order
            FROM withdrawal_quick_values 
            WHERE is_active = 1
            ORDER BY display_order ASC, value_amount ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $values = [];
        while ($row = $result->fetch_assoc()) {
            $values[] = [
                'id' => (int)$row['id'],
                'value' => (int)$row['value_amount'],
                'order' => (int)$row['display_order']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $values
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // ============================================
        // POST - Atualizar valores rápidos
        // ============================================
        
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // Log para debug
        error_log("Quick Withdrawal POST - Input: $input");
        error_log("Quick Withdrawal POST - Data: " . json_encode($data));
        
        if (!$data || !isset($data['values']) || !is_array($data['values'])) {
            error_log("Quick Withdrawal POST - ERRO: Dados inválidos");
            throw new Exception('Dados inválidos');
        }
        
        error_log("Quick Withdrawal POST - Values: " . json_encode($data['values']));
        
        $conn->begin_transaction();
        
        try {
            // Desativar todos os valores atuais
            $stmt = $conn->prepare("UPDATE withdrawal_quick_values SET is_active = 0");
            $stmt->execute();
            
            // Inserir ou ativar novos valores
            $order = 1;
            foreach ($data['values'] as $value) {
                $valueInt = (int)$value;
                
                // Validar valor
                if ($valueInt <= 0) {
                    continue;
                }
                
                // Inserir ou atualizar
                $stmt = $conn->prepare("
                    INSERT INTO withdrawal_quick_values (value_amount, is_active, display_order) 
                    VALUES (?, 1, ?)
                    ON DUPLICATE KEY UPDATE 
                        is_active = 1,
                        display_order = ?,
                        updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->bind_param('iii', $valueInt, $order, $order);
                $stmt->execute();
                
                $order++;
            }
            
            // Log da alteração
            try {
                $logDetails = json_encode([
                    'values' => $data['values'],
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
                $logStmt = $conn->prepare("
                    INSERT INTO admin_logs (action, details, created_at) 
                    VALUES ('update_quick_withdrawal_values', ?, NOW())
                ");
                $logStmt->bind_param('s', $logDetails);
                $logStmt->execute();
            } catch (Exception $e) {
                // Tabela de logs não existe, continuar sem logar
                error_log("Log table not found: " . $e->getMessage());
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Valores atualizados com sucesso'
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

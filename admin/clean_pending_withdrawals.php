<?php
/**
 * Admin: Limpar Saques Pendentes - Manter Apenas do Top 10 Atual
 * 
 * Este script remove todos os saques pendentes EXCETO os dos usuários
 * que estão atualmente no top 10 do ranking.
 * 
 * Top 10 atual (baseado na imagem):
 * 1. Rafael Germano - R$ 10,00
 * 2. Eduarda - R$ 5,00
 * 3. Luiz Santos - R$ 2,50
 * 4. marcosqz - R$ 1,00
 * 5. Matheus Augusto Pires stella Júnior - R$ 1,00
 * 6. José tiarlem Da Silva julio - R$ 1,00
 * 7. Carlos Ventura - R$ 1,00
 * 8. Alex Santiago - R$ 1,00
 * 9. Regiane Farias - R$ 1,00
 * 10. Maria eudilenedos reis medeiros Medeiros - R$ 1,00
 * 
 * Endpoint: GET /admin/clean_pending_withdrawals.php
 * 
 * Parâmetros:
 * - ?confirm=yes - Executa a limpeza
 * - Sem parâmetro - Mostra prévia
 */

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

date_default_timezone_set('America/Sao_Paulo');

$isConfirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

try {
    $conn = getDbConnection();
    
    // Nomes dos usuários do top 10 atual (da imagem)
    $top10Names = [
        'Rafael Germano',
        'Eduarda',
        'Luiz Santos',
        'marcosqz',
        'Matheus Augusto Pires stella Júnior',
        'José tiarlem Da Silva julio',
        'Carlos Ventura',
        'Alex Santiago',
        'Regiane Farias',
        'Maria eudilenedos reis medeiros Medeiros'
    ];
    
    // Buscar IDs dos usuários do top 10
    $top10UserIds = [];
    foreach ($top10Names as $name) {
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE name = ? OR name LIKE ?");
        $likeName = "%" . $name . "%";
        $stmt->bind_param("ss", $name, $likeName);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $top10UserIds[$row['id']] = $row['name'];
        }
        $stmt->close();
    }
    
    // Buscar todos os saques pendentes
    $pendingResult = $conn->query("
        SELECT w.id, w.user_id, u.name as user_name, w.amount, w.pix_key, w.created_at
        FROM withdrawals w
        LEFT JOIN users u ON w.user_id = u.id
        WHERE w.status = 'pending'
        ORDER BY w.created_at DESC
    ");
    
    $toKeep = [];
    $toRemove = [];
    
    while ($row = $pendingResult->fetch_assoc()) {
        $withdrawal = [
            'id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'user_name' => $row['user_name'],
            'amount' => (float)$row['amount'],
            'pix_key' => $row['pix_key'],
            'created_at' => $row['created_at']
        ];
        
        if (isset($top10UserIds[$row['user_id']])) {
            $toKeep[] = $withdrawal;
        } else {
            $toRemove[] = $withdrawal;
        }
    }
    
    // MODO PRÉVIA
    if (!$isConfirmed) {
        echo json_encode([
            'success' => true,
            'mode' => 'preview',
            'message' => 'Prévia da limpeza de saques. Adicione ?confirm=yes para executar.',
            'summary' => [
                'total_pending' => count($toKeep) + count($toRemove),
                'to_keep' => count($toKeep),
                'to_remove' => count($toRemove),
                'total_amount_to_keep' => array_sum(array_column($toKeep, 'amount')),
                'total_amount_to_remove' => array_sum(array_column($toRemove, 'amount'))
            ],
            'top10_users' => $top10UserIds,
            'withdrawals_to_keep' => $toKeep,
            'withdrawals_to_remove' => $toRemove,
            'how_to_execute' => '/admin/clean_pending_withdrawals.php?confirm=yes'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // MODO EXECUÇÃO
    if (empty($toRemove)) {
        echo json_encode([
            'success' => true,
            'message' => 'Nenhum saque pendente para remover. Todos já são do top 10.',
            'withdrawals_kept' => $toKeep
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        // Remover saques que não são do top 10
        $removeIds = array_column($toRemove, 'id');
        $placeholders = implode(',', array_fill(0, count($removeIds), '?'));
        $types = str_repeat('i', count($removeIds));
        
        $stmt = $conn->prepare("DELETE FROM withdrawals WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$removeIds);
        $stmt->execute();
        $deletedCount = $stmt->affected_rows;
        $stmt->close();
        
        $conn->commit();
        
        error_log("[ADMIN] LIMPEZA DE SAQUES: $deletedCount saques removidos, " . count($toKeep) . " mantidos");
        
        echo json_encode([
            'success' => true,
            'mode' => 'executed',
            'message' => "Limpeza concluída! $deletedCount saques removidos, " . count($toKeep) . " mantidos.",
            'summary' => [
                'removed' => $deletedCount,
                'kept' => count($toKeep),
                'total_amount_kept' => array_sum(array_column($toKeep, 'amount'))
            ],
            'withdrawals_kept' => $toKeep,
            'withdrawals_removed' => $toRemove,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Clean pending withdrawals error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao limpar saques: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

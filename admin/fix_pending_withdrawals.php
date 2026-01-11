<?php
/**
 * Admin: Corrigir Saques Pendentes do Top 10
 * 
 * Este script:
 * 1. Remove todos os saques pendentes atuais
 * 2. Cria novos saques pendentes para o top 10 com os valores corretos do print:
 *    #1 Rafael Germano - R$ 10,00
 *    #2 Eduarda - R$ 5,00
 *    #3 Luiz Santos - R$ 2,50
 *    #4 marcosqz - R$ 1,00
 *    #5 Matheus Augusto Pires stella Júnior - R$ 1,00
 *    #6 José tiarlem Da Silva julio - R$ 1,00
 *    #7 Carlos Ventura - R$ 1,00
 *    #8 Alex Santiago - R$ 1,00
 *    #9 Regiane Farias - R$ 1,00
 *    #10 Maria eudilenedos reis medeiros Medeiros - R$ 1,00
 * 
 * Endpoint: GET /admin/fix_pending_withdrawals.php?confirm=yes
 */

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

date_default_timezone_set('America/Sao_Paulo');

$isConfirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

try {
    $conn = getDbConnection();
    
    // Top 10 com valores corretos do print
    $top10Withdrawals = [
        ['name' => 'Rafael Germano', 'amount' => 10.00],
        ['name' => 'Eduarda', 'amount' => 5.00],
        ['name' => 'Luiz Santos', 'amount' => 2.50],
        ['name' => 'marcosqz', 'amount' => 1.00],
        ['name' => 'Matheus Augusto Pires stella Júnior', 'amount' => 1.00],
        ['name' => 'José tiarlem Da Silva julio', 'amount' => 1.00],
        ['name' => 'Carlos Ventura', 'amount' => 1.00],
        ['name' => 'Alex Santiago', 'amount' => 1.00],
        ['name' => 'Regiane Farias', 'amount' => 1.00],
        ['name' => 'Maria eudilenedos reis medeiros Medeiros', 'amount' => 1.00]
    ];
    
    // Buscar dados dos usuários (id e pix_key)
    $usersData = [];
    foreach ($top10Withdrawals as $w) {
        $stmt = $conn->prepare("SELECT id, name, email, pix_key FROM users WHERE name = ?");
        $stmt->bind_param("s", $w['name']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $usersData[] = [
                'user_id' => $row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'pix_key' => $row['pix_key'] ?: $row['email'],
                'amount' => $w['amount']
            ];
        }
        $stmt->close();
    }
    
    // Buscar saques pendentes atuais
    $pendingResult = $conn->query("
        SELECT w.id, w.user_id, u.name as user_name, w.amount, w.pix_key
        FROM withdrawals w
        LEFT JOIN users u ON w.user_id = u.id
        WHERE w.status = 'pending'
    ");
    
    $currentPending = [];
    while ($row = $pendingResult->fetch_assoc()) {
        $currentPending[] = $row;
    }
    
    // MODO PRÉVIA
    if (!$isConfirmed) {
        echo json_encode([
            'success' => true,
            'mode' => 'preview',
            'message' => 'Prévia da correção. Adicione ?confirm=yes para executar.',
            'current_pending_to_remove' => $currentPending,
            'new_pending_to_create' => $usersData,
            'total_new_amount' => array_sum(array_column($usersData, 'amount')),
            'how_to_execute' => '/admin/fix_pending_withdrawals.php?confirm=yes'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // MODO EXECUÇÃO
    $conn->begin_transaction();
    
    try {
        // 1. Remover todos os saques pendentes atuais
        $conn->query("DELETE FROM withdrawals WHERE status = 'pending'");
        $deletedCount = $conn->affected_rows;
        
        // 2. Criar novos saques pendentes com valores corretos
        $created = [];
        $stmt = $conn->prepare("
            INSERT INTO withdrawals (user_id, amount, pix_type, pix_key, status, created_at)
            VALUES (?, ?, 'email', ?, 'pending', NOW())
        ");
        
        foreach ($usersData as $userData) {
            $stmt->bind_param("ids", $userData['user_id'], $userData['amount'], $userData['pix_key']);
            $stmt->execute();
            
            $created[] = [
                'id' => $conn->insert_id,
                'user_id' => $userData['user_id'],
                'name' => $userData['name'],
                'amount' => $userData['amount'],
                'pix_key' => $userData['pix_key']
            ];
        }
        $stmt->close();
        
        $conn->commit();
        
        error_log("[ADMIN] CORREÇÃO DE SAQUES: $deletedCount removidos, " . count($created) . " criados");
        
        echo json_encode([
            'success' => true,
            'mode' => 'executed',
            'message' => "Correção concluída! $deletedCount saques removidos, " . count($created) . " novos criados.",
            'summary' => [
                'removed' => $deletedCount,
                'created' => count($created),
                'total_amount' => array_sum(array_column($created, 'amount'))
            ],
            'new_pending_withdrawals' => $created,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Fix pending withdrawals error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao corrigir saques: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

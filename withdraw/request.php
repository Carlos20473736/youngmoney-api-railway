<?php
/**
 * Withdraw Request Endpoint
 * POST - Solicita um novo saque
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/auth_helper.php';

try {
    $conn = getDbConnection();
    
    // Autenticar usuário
    $user = getAuthenticatedUser($conn);
    
    if (!$user) {
        sendUnauthorizedError();
    }
    
    // Ler dados da requisição
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        sendError('Dados inválidos', 400);
    }
    
    // Validar campos obrigatórios
    $amount = isset($data['amount']) ? (float)$data['amount'] : 0;
    $pixType = $data['pix_type'] ?? null;
    $pixKey = $data['pix_key'] ?? null;
    
    if ($amount <= 0) {
        sendError('Valor inválido', 400);
    }
    
    if (!$pixType || !$pixKey) {
        sendError('Tipo e chave PIX são obrigatórios', 400);
    }
    
    // Buscar configurações de saque (limites e valores rápidos)
    $stmt = $conn->prepare("
        SELECT setting_key, setting_value 
        FROM system_settings 
        WHERE setting_key IN ('min_withdrawal', 'max_withdrawal')
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $minWithdrawal = 10; // padrão
    $maxWithdrawal = 1000; // padrão
    
    while ($row = $result->fetch_assoc()) {
        if ($row['setting_key'] === 'min_withdrawal') {
            $minWithdrawal = (float)$row['setting_value'];
        } elseif ($row['setting_key'] === 'max_withdrawal') {
            $maxWithdrawal = (float)$row['setting_value'];
        }
    }
    $stmt->close();
    
    // Buscar valores rápidos configurados
    $stmt = $conn->prepare("
        SELECT value_amount 
        FROM withdrawal_quick_values 
        WHERE is_active = 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $quickValues = [];
    while ($row = $result->fetch_assoc()) {
        $quickValues[] = (float)$row['value_amount'];
    }
    $stmt->close();
    
    // Verificar se usuário tem saldo suficiente
    if ($user['points'] < $amount) {
        sendError('Saldo insuficiente', 400);
    }
    
    // Validar valor: aceitar se for um valor rápido OU se estiver dentro dos limites
    $isQuickValue = in_array($amount, $quickValues);
    $isWithinLimits = ($amount >= $minWithdrawal && $amount <= $maxWithdrawal);
    
    if (!$isQuickValue && !$isWithinLimits) {
        if ($amount < $minWithdrawal) {
            sendError("Valor mínimo para saque é R$ " . number_format($minWithdrawal, 2, ',', '.'), 400);
        } else {
            sendError("Valor máximo para saque é R$ " . number_format($maxWithdrawal, 2, ',', '.'), 400);
        }
    }
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // Debitar pontos do usuário
        $stmt = $conn->prepare("
            UPDATE users 
            SET points = points - ?
            WHERE id = ? AND points >= ?
        ");
        $stmt->bind_param("dii", $amount, $user['id'], $amount);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('Saldo insuficiente ou usuário não encontrado');
        }
        $stmt->close();
        
        // Criar registro de saque
        $stmt = $conn->prepare("
            INSERT INTO withdrawals (user_id, amount, pix_type, pix_key, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("idss", $user['id'], $amount, $pixType, $pixKey);
        $stmt->execute();
        $withdrawalId = $stmt->insert_id;
        $stmt->close();
        
        // Registrar transação de pontos
        $description = "Saque solicitado - ID: $withdrawalId";
        $stmt = $conn->prepare("
            INSERT INTO point_transactions (user_id, points, type, description)
            VALUES (?, ?, 'debit', ?)
        ");
        $negativeAmount = -$amount;
        $stmt->bind_param("ids", $user['id'], $negativeAmount, $description);
        $stmt->execute();
        $stmt->close();
        
        // Commit da transação
        $conn->commit();
        
        sendSuccess([
            'withdrawal_id' => $withdrawalId,
            'amount' => $amount,
            'status' => 'pending',
            'message' => 'Saque solicitado com sucesso! Aguarde a aprovação.'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("withdraw/request.php error: " . $e->getMessage());
    sendError('Erro ao solicitar saque: ' . $e->getMessage(), 500);
}
?>

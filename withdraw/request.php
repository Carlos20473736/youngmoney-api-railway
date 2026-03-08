<?php
/**
 * Withdraw Request Endpoint - V2
 * POST - Solicita um novo saque (PIX, Binance ou FaucetPay)
 * 
 * TAXA DE CONVERSÃO: 5.000.000 pontos = R$ 1,00
 * MÍNIMO: 5.000.000 pontos (R$ 1,00)
 * 
 * Para crypto (Binance/FaucetPay): converte R$ para LTC em tempo real
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

// ========================================
// CONSTANTES DE CONVERSÃO
// ========================================
define('POINTS_PER_REAL', 5000000); // 5 milhões de pontos = R$ 1,00
define('MIN_POINTS', 5000000);       // Mínimo para saque: 5M pontos
define('MIN_BRL', 1.00);             // Mínimo em reais

/**
 * Busca cotação do Litecoin em BRL
 */
function getLtcBrlRate() {
    $cacheFile = sys_get_temp_dir() . '/ltc_brl_cache.json';
    $cacheTime = 300; // 5 minutos
    
    if (file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        if ($cacheData && (time() - $cacheData['timestamp']) < $cacheTime) {
            return $cacheData['rate'];
        }
    }
    
    // CoinGecko API
    $url = 'https://api.coingecko.com/api/v3/simple/price?ids=litecoin&vs_currencies=brl';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['litecoin']['brl'])) {
            $rate = (float)$data['litecoin']['brl'];
            file_put_contents($cacheFile, json_encode([
                'rate' => $rate,
                'timestamp' => time(),
            ]));
            return $rate;
        }
    }
    
    // Fallback: Binance API
    $url = 'https://api.binance.com/api/v3/ticker/price?symbol=LTCBRL';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['price'])) {
            $rate = (float)$data['price'];
            file_put_contents($cacheFile, json_encode([
                'rate' => $rate,
                'timestamp' => time(),
            ]));
            return $rate;
        }
    }
    
    return 550.00; // Fallback
}

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
    
    // ========================================
    // VALIDAR CAMPOS
    // ========================================
    $amountBrl = isset($data['amount']) ? (float)$data['amount'] : 0;
    $paymentMethod = strtolower($data['method'] ?? 'pix');
    
    // Validar método de pagamento
    $validMethods = ['pix', 'binance', 'faucetpay'];
    if (!in_array($paymentMethod, $validMethods)) {
        sendError('Método de pagamento inválido. Use: pix, binance ou faucetpay', 400);
    }
    
    // Validar valor mínimo em BRL
    if ($amountBrl < MIN_BRL) {
        sendError('Valor mínimo para saque é R$ ' . number_format(MIN_BRL, 2, ',', '.') . ' (5.000.000 pontos)', 400);
    }
    
    // Calcular pontos necessários
    $pointsRequired = (int)($amountBrl * POINTS_PER_REAL);
    
    // Verificar saldo do usuário
    $currentPoints = (int)$user['points'];
    if ($currentPoints < $pointsRequired) {
        $pointsFormatted = number_format($pointsRequired, 0, '', '.');
        $balanceFormatted = number_format($currentPoints, 0, '', '.');
        sendError("Saldo insuficiente. Necessário: {$pointsFormatted} pontos. Seu saldo: {$balanceFormatted} pontos", 400);
    }
    
    // ========================================
    // VALIDAR DADOS ESPECÍFICOS DO MÉTODO
    // ========================================
    $pixType = null;
    $pixKey = null;
    $cryptoAddress = null;
    $cryptoAmount = null;
    $cryptoCurrency = 'LTC';
    $exchangeRate = null;
    
    if ($paymentMethod === 'pix') {
        // Validar PIX
        $pixType = $data['pix_type'] ?? null;
        $pixKey = $data['pix_key'] ?? null;
        
        if (!$pixType || !$pixKey) {
            sendError('Tipo e chave PIX são obrigatórios', 400);
        }
        
    } else {
        // Validar Crypto (Binance ou FaucetPay)
        $cryptoAddress = $data['crypto_address'] ?? $data['address'] ?? null;
        
        if (!$cryptoAddress || strlen(trim($cryptoAddress)) < 10) {
            sendError('Endereço da carteira é obrigatório', 400);
        }
        
        $cryptoAddress = trim($cryptoAddress);
        
        // Buscar cotação LTC/BRL em tempo real
        $ltcRate = getLtcBrlRate();
        $exchangeRate = $ltcRate;
        
        // Calcular valor em LTC
        $cryptoAmount = round($amountBrl / $ltcRate, 8);
        
        if ($cryptoAmount <= 0) {
            sendError('Erro ao calcular valor em Litecoin. Tente novamente.', 500);
        }
    }
    
    // ========================================
    // PROCESSAR SAQUE
    // ========================================
    $conn->begin_transaction();
    
    try {
        // 1. Debitar pontos do usuário
        $stmt = $conn->prepare("
            UPDATE users 
            SET points = points - ?
            WHERE id = ? AND points >= ?
        ");
        $stmt->bind_param("iii", $pointsRequired, $user['id'], $pointsRequired);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('Saldo insuficiente ou usuário não encontrado');
        }
        $stmt->close();
        
        // 2. Criar registro de saque
        if ($paymentMethod === 'pix') {
            $stmt = $conn->prepare("
                INSERT INTO withdrawals 
                (user_id, amount, pix_type, pix_key, payment_method, points_debited, status)
                VALUES (?, ?, ?, ?, 'pix', ?, 'pending')
            ");
            $stmt->bind_param("idssi", $user['id'], $amountBrl, $pixType, $pixKey, $pointsRequired);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO withdrawals 
                (user_id, amount, payment_method, crypto_address, crypto_amount, crypto_currency, points_debited, exchange_rate, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param("idssdsdi", 
                $user['id'], $amountBrl, $paymentMethod, $cryptoAddress, 
                $cryptoAmount, $cryptoCurrency, $pointsRequired, $exchangeRate
            );
        }
        $stmt->execute();
        $withdrawalId = $stmt->insert_id;
        $stmt->close();
        
        // 3. Registrar transação de pontos
        $methodLabel = strtoupper($paymentMethod);
        if ($paymentMethod === 'pix') {
            $description = "Saque de R$ " . number_format($amountBrl, 2, ',', '.') . " via PIX - ID: $withdrawalId";
        } else {
            $ltcFormatted = number_format($cryptoAmount, 8, '.', '');
            $description = "Saque de R$ " . number_format($amountBrl, 2, ',', '.') . " ({$ltcFormatted} LTC) via {$methodLabel} - ID: $withdrawalId";
        }
        
        $negativePoints = -$pointsRequired;
        $stmt = $conn->prepare("
            INSERT INTO point_transactions (user_id, points, type, description)
            VALUES (?, ?, 'debit', ?)
        ");
        $stmt->bind_param("iis", $user['id'], $negativePoints, $description);
        $stmt->execute();
        $stmt->close();
        
        // Também registrar em points_history se a tabela existir
        try {
            $stmt = $conn->prepare("
                INSERT INTO points_history (user_id, points, description, type)
                VALUES (?, ?, ?, 'debit')
            ");
            $stmt->bind_param("iis", $user['id'], $negativePoints, $description);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            // Tabela pode não existir, ignorar
        }
        
        // Commit da transação
        $conn->commit();
        
        // ========================================
        // RESPOSTA DE SUCESSO
        // ========================================
        $responseData = [
            'withdrawal_id' => $withdrawalId,
            'amount_brl' => $amountBrl,
            'amount_brl_formatted' => 'R$ ' . number_format($amountBrl, 2, ',', '.'),
            'points_debited' => $pointsRequired,
            'points_debited_formatted' => number_format($pointsRequired, 0, '', '.'),
            'remaining_points' => $currentPoints - $pointsRequired,
            'payment_method' => $paymentMethod,
            'status' => 'pending',
            'message' => 'Saque solicitado com sucesso! Aguarde a aprovação.',
        ];
        
        // Adicionar dados crypto se aplicável
        if ($paymentMethod !== 'pix') {
            $responseData['crypto_amount'] = $cryptoAmount;
            $responseData['crypto_amount_formatted'] = number_format($cryptoAmount, 8, '.', '') . ' LTC';
            $responseData['crypto_currency'] = $cryptoCurrency;
            $responseData['exchange_rate'] = $exchangeRate;
            $responseData['exchange_rate_formatted'] = 'R$ ' . number_format($exchangeRate, 2, ',', '.') . '/LTC';
            $responseData['crypto_address'] = $cryptoAddress;
        }
        
        sendSuccess($responseData);
        
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

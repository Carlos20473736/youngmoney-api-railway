<?php
/**
 * User Crypto Address Endpoint
 * GET  - Retorna endereços crypto do usuário autenticado
 * POST - Salva/atualiza endereço crypto do usuário
 * 
 * Suporta: Binance e FaucetPay (Litecoin)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Buscar todos os endereços crypto do usuário
        $platform = $_GET['platform'] ?? null;
        
        if ($platform) {
            // Buscar endereço de uma plataforma específica
            $stmt = $conn->prepare("
                SELECT platform, address, currency 
                FROM user_crypto_addresses 
                WHERE user_id = ? AND platform = ? AND is_active = 1
            ");
            $stmt->bind_param("is", $user['id'], $platform);
        } else {
            // Buscar todos os endereços
            $stmt = $conn->prepare("
                SELECT platform, address, currency 
                FROM user_crypto_addresses 
                WHERE user_id = ? AND is_active = 1
            ");
            $stmt->bind_param("i", $user['id']);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $addresses = [];
        while ($row = $result->fetch_assoc()) {
            $addresses[$row['platform']] = [
                'address' => $row['address'],
                'currency' => $row['currency'],
                'has_address' => true,
            ];
        }
        $stmt->close();
        
        // Garantir que ambas plataformas estejam na resposta
        $platforms = ['binance', 'faucetpay'];
        foreach ($platforms as $p) {
            if (!isset($addresses[$p])) {
                $addresses[$p] = [
                    'address' => '',
                    'currency' => 'LTC',
                    'has_address' => false,
                ];
            }
        }
        
        sendSuccess([
            'addresses' => $addresses,
        ]);
        
    } elseif ($method === 'POST' || $method === 'PUT') {
        // Salvar/atualizar endereço crypto
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        if (!$data) {
            sendError('Dados inválidos', 400);
        }
        
        $platform = strtolower($data['platform'] ?? '');
        $address = trim($data['address'] ?? '');
        $currency = strtoupper($data['currency'] ?? 'LTC');
        
        // Validar plataforma
        $validPlatforms = ['binance', 'faucetpay'];
        if (!in_array($platform, $validPlatforms)) {
            sendError('Plataforma inválida. Use: binance ou faucetpay', 400);
        }
        
        // Validar endereço
        if (empty($address)) {
            sendError('Endereço da carteira é obrigatório', 400);
        }
        
        if (strlen($address) < 10) {
            sendError('Endereço da carteira inválido (muito curto)', 400);
        }
        
        // Inserir ou atualizar (UPSERT)
        $stmt = $conn->prepare("
            INSERT INTO user_crypto_addresses (user_id, platform, address, currency, is_active)
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE 
                address = VALUES(address), 
                currency = VALUES(currency), 
                is_active = 1,
                updated_at = NOW()
        ");
        $stmt->bind_param("isss", $user['id'], $platform, $address, $currency);
        $stmt->execute();
        $stmt->close();
        
        sendSuccess([
            'message' => 'Endereço salvo com sucesso',
            'platform' => $platform,
            'address' => $address,
            'currency' => $currency,
        ]);
        
    } else {
        sendError('Método não permitido', 405);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("user/crypto.php error: " . $e->getMessage());
    sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>

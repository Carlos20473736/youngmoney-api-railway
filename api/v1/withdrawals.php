<?php
// Endpoint da API para Saques (v1)

// Taxa de conversão: 5.000.000 pontos = R$ 1,00

header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../database.php';
require_once __DIR__ . '/../../includes/HeadersValidator.php';
require_once __DIR__ . '/../../includes/auth_helper.php';
require_once __DIR__ . '/../../includes/security_validation_helper.php';
require_once __DIR__ . '/middleware/MaintenanceCheck.php';

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDbConnection();

// ========================================
// VERIFICAÇÃO DE MANUTENÇÃO E VERSÃO
// ========================================
$requestData = ($method === 'POST' || $method === 'PUT') 
    ? json_decode(file_get_contents('php://input'), true) ?? []
    : $_GET;
$userEmail = $requestData['email'] ?? null;
$appVersion = $requestData['app_version'] ?? $_SERVER['HTTP_X_APP_VERSION'] ?? null;
checkMaintenanceAndVersion($conn, $userEmail, $appVersion);
// ========================================

// Validar headers de segurança
$validator = validateRequestHeaders($conn, true);
if (!$validator) exit; // Já enviou resposta de erro


// Taxa de conversão: 5.000.000 pontos = R$ 1,00
define('POINTS_PER_REAL', 5000000);

/**
 * Busca cotação do Litecoin em BRL
 */
function getLtcBrlRate() {
    $cacheFile = sys_get_temp_dir() . '/ltc_brl_cache.json';
    $cacheTime = 300;
    
    if (file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        if ($cacheData && (time() - $cacheData['timestamp']) < $cacheTime) {
            return $cacheData['rate'];
        }
    }
    
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
            file_put_contents($cacheFile, json_encode(['rate' => $rate, 'timestamp' => time()]));
            return $rate;
        }
    }
    
    // Fallback Binance
    $url = 'https://api.binance.com/api/v3/ticker/price?symbol=LTCBRL';
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['price'])) {
            $rate = (float)$data['price'];
            file_put_contents($cacheFile, json_encode(['rate' => $rate, 'timestamp' => time()]));
            return $rate;
        }
    }
    
    return 550.00;
}

switch ($method) {
    case 'GET':
        // Lógica para obter o histórico de saques de um usuário
        if (isset($_GET['user_id'])) {
            $userId = intval($_GET['user_id']);
            $stmt = $conn->prepare("
                SELECT id, pix_key_type, amount, status, payment_method, 
                       crypto_amount, crypto_currency, crypto_address, 
                       points_debited, created_at 
                FROM withdrawals 
                WHERE user_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $withdrawals = [];
            while ($row = $result->fetch_assoc()) {
                $withdrawals[] = $row;
            }
            echo json_encode($withdrawals);
            $stmt->close();
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'User ID is required']);
        }
        break;

    case 'POST':
        try {
            // Validar XReq token
            validateXReq();
            
            // Autenticar usuário
            $user = getAuthenticatedUser($conn);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Não autenticado']);
                exit;
            }
            
            $data = $requestData;

            if (!isset($data['user_id']) || !isset($data['amount'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
                exit;
            }

            $userId = intval($data['user_id']);
            $amountBrl = floatval($data['amount']);
            $paymentMethod = strtolower($data['method'] ?? 'pix');

            // Validar método
            $validMethods = ['pix', 'binance', 'faucetpay'];
            if (!in_array($paymentMethod, $validMethods)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Método de pagamento inválido']);
                exit;
            }

            // Validar valor mínimo (R$ 1,00 = 5.000.000 pontos)
            if ($amountBrl < 1) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Valor mínimo: R$ 1,00 (5.000.000 pontos)']);
                exit;
            }

            // Calcular pontos necessários (R$ 1,00 = 5.000.000 pontos)
            $pointsRequired = intval($amountBrl * POINTS_PER_REAL);

            // Buscar saldo atual do usuário
            $stmt = $conn->prepare("SELECT points FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
                exit;
            }
            
            $user = $result->fetch_assoc();
            $currentPoints = $user['points'];
            $stmt->close();

            // Verificar se tem pontos suficientes
            if ($currentPoints < $pointsRequired) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Saldo insuficiente',
                    'current_points' => $currentPoints,
                    'required_points' => $pointsRequired,
                    'points_per_real' => POINTS_PER_REAL,
                ]);
                exit;
            }

            // Preparar dados crypto se necessário
            $cryptoAddress = null;
            $cryptoAmount = null;
            $cryptoCurrency = null;
            $exchangeRate = null;

            if ($paymentMethod !== 'pix') {
                $cryptoAddress = trim($data['crypto_address'] ?? $data['address'] ?? '');
                if (empty($cryptoAddress) || strlen($cryptoAddress) < 10) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Endereço da carteira é obrigatório']);
                    exit;
                }
                
                $ltcRate = getLtcBrlRate();
                $exchangeRate = $ltcRate;
                $cryptoAmount = round($amountBrl / $ltcRate, 8);
                $cryptoCurrency = 'LTC';
            } else {
                // Validar PIX
                $pixKey = $data['pix_key'] ?? '';
                $pixKeyType = $data['pix_key_type'] ?? '';
                if (empty($pixKey) || empty($pixKeyType)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Chave PIX e tipo são obrigatórios']);
                    exit;
                }
            }

            // Iniciar transação
            $conn->begin_transaction();

            try {
                // 1. Debitar pontos do usuário
                $stmt = $conn->prepare("UPDATE users SET points = points - ? WHERE id = ?");
                $stmt->bind_param("ii", $pointsRequired, $userId);
                $stmt->execute();
                $stmt->close();

                // 2. Registrar no histórico de pontos
                $methodLabel = strtoupper($paymentMethod);
                if ($paymentMethod === 'pix') {
                    $description = "Saque de R$ " . number_format($amountBrl, 2, ',', '.') . " via PIX";
                } else {
                    $ltcFormatted = number_format($cryptoAmount, 8, '.', '');
                    $description = "Saque de R$ " . number_format($amountBrl, 2, ',', '.') . " ({$ltcFormatted} LTC) via {$methodLabel}";
                }
                
                $negativePoints = -$pointsRequired;
                $stmt = $conn->prepare("INSERT INTO points_history (user_id, points, description, type) VALUES (?, ?, ?, 'debit')");
                $stmt->bind_param("iis", $userId, $negativePoints, $description);
                $stmt->execute();
                $stmt->close();

                // 3. Criar registro de saque
                if ($paymentMethod === 'pix') {
                    $stmt = $conn->prepare("
                        INSERT INTO withdrawals (user_id, pix_key, pix_key_type, amount, payment_method, points_debited, status) 
                        VALUES (?, ?, ?, ?, 'pix', ?, 'pending')
                    ");
                    $stmt->bind_param("issdi", $userId, $pixKey, $pixKeyType, $amountBrl, $pointsRequired);
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO withdrawals (user_id, amount, payment_method, crypto_address, crypto_amount, crypto_currency, points_debited, exchange_rate, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->bind_param("idssdsdi", $userId, $amountBrl, $paymentMethod, $cryptoAddress, $cryptoAmount, $cryptoCurrency, $pointsRequired, $exchangeRate);
                }
                $stmt->execute();
                $withdrawalId = $stmt->insert_id;
                $stmt->close();

                // Commit da transação
                $conn->commit();

                $responseData = [
                    'withdrawal_id' => $withdrawalId,
                    'amount_brl' => $amountBrl,
                    'points_debited' => $pointsRequired,
                    'remaining_points' => $currentPoints - $pointsRequired,
                    'payment_method' => $paymentMethod,
                    'status' => 'pending',
                    'message' => 'Solicitação de saque criada com sucesso',
                ];

                if ($paymentMethod !== 'pix') {
                    $responseData['crypto_amount'] = $cryptoAmount;
                    $responseData['crypto_currency'] = $cryptoCurrency;
                    $responseData['exchange_rate'] = $exchangeRate;
                    $responseData['crypto_address'] = $cryptoAddress;
                }

                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'data' => $responseData,
                ]);

            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro ao processar saque: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method Not Allowed']);
        break;
}

$conn->close();
?>

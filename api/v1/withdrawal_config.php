<?php
/**
 * Endpoint: Configuração Completa de Saque
 * GET /api/v1/withdrawal_config.php
 * 
 * Retorna toda a configuração necessária para a tela de saque:
 * - Taxa de conversão (5M pontos = R$1)
 * - Valores rápidos com pontos
 * - Cotação LTC em tempo real
 * - Métodos disponíveis
 * - Exemplos de conversão para crypto
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../database.php';
require_once __DIR__ . '/../../includes/auth_helper.php';

// Constantes
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
            return $cacheData;
        }
    }
    
    $url = 'https://api.coingecko.com/api/v3/simple/price?ids=litecoin&vs_currencies=brl,usd';
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
            $cacheData = [
                'rate' => (float)$data['litecoin']['brl'],
                'rate_usd' => (float)($data['litecoin']['usd'] ?? 0),
                'timestamp' => time(),
            ];
            file_put_contents($cacheFile, json_encode($cacheData));
            return $cacheData;
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
            $cacheData = [
                'rate' => (float)$data['price'],
                'rate_usd' => 0,
                'timestamp' => time(),
            ];
            file_put_contents($cacheFile, json_encode($cacheData));
            return $cacheData;
        }
    }
    
    return ['rate' => 550.00, 'rate_usd' => 0, 'timestamp' => time()];
}

try {
    $conn = getDbConnection();
    
    // Buscar valores rápidos do banco
    $result = $conn->query("
        SELECT value_amount 
        FROM withdrawal_quick_values 
        WHERE is_active = 1 
        ORDER BY display_order ASC
    ");
    
    $quickValues = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $quickValues[] = (float)$row['value_amount'];
        }
    }
    
    if (empty($quickValues)) {
        $quickValues = [1, 2, 5, 10, 20, 50];
    }
    
    $conn->close();
    
    // Buscar cotação LTC
    $ltcData = getLtcBrlRate();
    $ltcRate = $ltcData['rate'];
    
    // Montar tabela de conversão completa
    $conversionTable = [];
    foreach ($quickValues as $brl) {
        $points = (int)($brl * POINTS_PER_REAL);
        $ltcAmount = round($brl / $ltcRate, 8);
        
        $conversionTable[] = [
            'brl' => $brl,
            'brl_formatted' => 'R$ ' . number_format($brl, 2, ',', '.'),
            'points' => $points,
            'points_formatted' => number_format($points, 0, '', '.'),
            'ltc' => $ltcAmount,
            'ltc_formatted' => number_format($ltcAmount, 8, '.', '') . ' LTC',
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            // Configuração de pontos
            'points_per_real' => POINTS_PER_REAL,
            'points_per_real_formatted' => '5.000.000',
            'min_withdrawal_brl' => 1.00,
            'min_withdrawal_points' => POINTS_PER_REAL,
            'min_withdrawal_points_formatted' => '5.000.000',
            
            // Métodos disponíveis
            'methods' => [
                [
                    'id' => 'pix',
                    'name' => 'PIX',
                    'enabled' => true,
                    'currency' => 'BRL',
                    'description' => 'Transferência instantânea via PIX',
                ],
                [
                    'id' => 'binance',
                    'name' => 'Binance',
                    'enabled' => true,
                    'currency' => 'LTC',
                    'description' => 'Receba Litecoin na sua Binance',
                ],
                [
                    'id' => 'faucetpay',
                    'name' => 'FaucetPay',
                    'enabled' => true,
                    'currency' => 'LTC',
                    'description' => 'Receba Litecoin no FaucetPay',
                ],
            ],
            
            // Cotação crypto
            'crypto' => [
                'currency' => 'LTC',
                'currency_name' => 'Litecoin',
                'rate_brl' => $ltcRate,
                'rate_brl_formatted' => 'R$ ' . number_format($ltcRate, 2, ',', '.'),
                'updated_at' => date('Y-m-d H:i:s', $ltcData['timestamp']),
            ],
            
            // Valores rápidos
            'quick_values' => $quickValues,
            
            // Tabela de conversão completa
            'conversion_table' => $conversionTable,
        ],
    ]);
    
} catch (Exception $e) {
    error_log("withdrawal_config.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar configuração']);
}
?>

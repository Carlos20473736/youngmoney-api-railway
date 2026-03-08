<?php
/**
 * Endpoint: Cotação de Criptomoedas em Tempo Real
 * GET /api/v1/crypto/rates.php
 * 
 * Retorna a cotação atual de LTC em BRL
 * Usado pelo app para mostrar quanto o usuário receberá em crypto
 * 
 * Taxa de conversão: 5.000.000 pontos = R$ 1,00
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Constantes de conversão
define('POINTS_PER_REAL', 5000000); // 5 milhões de pontos = R$ 1,00

/**
 * Busca cotação do Litecoin via CoinGecko API (gratuita)
 */
function getLtcBrlRate() {
    $cacheFile = sys_get_temp_dir() . '/ltc_brl_cache.json';
    $cacheTime = 300; // Cache de 5 minutos
    
    // Verificar cache
    if (file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        if ($cacheData && (time() - $cacheData['timestamp']) < $cacheTime) {
            return $cacheData['rate'];
        }
    }
    
    // Buscar cotação da CoinGecko
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
            $rate = (float)$data['litecoin']['brl'];
            $rateUsd = (float)($data['litecoin']['usd'] ?? 0);
            
            // Salvar cache
            $cacheData = [
                'rate' => $rate,
                'rate_usd' => $rateUsd,
                'timestamp' => time(),
            ];
            file_put_contents($cacheFile, json_encode($cacheData));
            
            return $rate;
        }
    }
    
    // Fallback: tentar Binance API
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
            $cacheData = [
                'rate' => $rate,
                'rate_usd' => 0,
                'timestamp' => time(),
            ];
            file_put_contents($cacheFile, json_encode($cacheData));
            return $rate;
        }
    }
    
    // Fallback final: valor estimado
    return 550.00;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método não permitido']);
        exit;
    }
    
    $ltcBrlRate = getLtcBrlRate();
    
    // Calcular exemplos de conversão
    $examples = [];
    $brlValues = [1, 2, 5, 10, 20, 50];
    foreach ($brlValues as $brl) {
        $ltcAmount = round($brl / $ltcBrlRate, 8);
        $pointsNeeded = $brl * POINTS_PER_REAL;
        $examples[] = [
            'brl' => $brl,
            'ltc' => $ltcAmount,
            'points_needed' => $pointsNeeded,
            'points_formatted' => number_format($pointsNeeded, 0, '', '.'),
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'currency' => 'LTC',
            'currency_name' => 'Litecoin',
            'rate_brl' => $ltcBrlRate,
            'rate_formatted' => 'R$ ' . number_format($ltcBrlRate, 2, ',', '.'),
            'points_per_real' => POINTS_PER_REAL,
            'points_per_real_formatted' => number_format(POINTS_PER_REAL, 0, '', '.'),
            'min_withdrawal_brl' => 1.00,
            'min_withdrawal_points' => POINTS_PER_REAL,
            'examples' => $examples,
            'updated_at' => date('Y-m-d H:i:s'),
        ],
    ]);
    
} catch (Exception $e) {
    error_log("crypto/rates.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar cotação']);
}
?>

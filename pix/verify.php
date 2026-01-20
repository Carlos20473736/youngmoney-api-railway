<?php
/**
 * PIX Key Verification Endpoint
 * POST - Verifica uma chave PIX e retorna os dados do titular
 * 
 * Este endpoint consulta o DICT (Diretório de Identificadores de Contas Transacionais)
 * através de um PSP parceiro para obter o nome do titular da chave PIX.
 * 
 * Endpoint: POST /pix/verify.php
 * 
 * Request Body:
 * {
 *     "pix_key_type": "CPF|Email|Telefone|Chave Aleatória",
 *     "pix_key": "valor_da_chave"
 * }
 * 
 * Response (sucesso):
 * {
 *     "status": "success",
 *     "data": {
 *         "owner_name": "Nome do Titular",
 *         "bank": "Nome do Banco",
 *         "account_type": "CONTA_CORRENTE|CONTA_POUPANCA"
 *     }
 * }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req, X-Request-ID, X-Needs-XReq, X-Skip-Encryption');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método não permitido'
    ]);
    exit;
}

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/auth_helper.php';

try {
    $conn = getDbConnection();
    
    // Autenticar usuário (opcional - remova se quiser endpoint público)
    $user = getAuthenticatedUser($conn);
    if (!$user) {
        sendUnauthorizedError();
    }
    
    // Ler dados da requisição
    // Verificar se veio via secure.php (túnel criptografado)
    if (isset($GLOBALS['_SECURE_REQUEST_BODY']) && !empty($GLOBALS['_SECURE_REQUEST_BODY'])) {
        $rawInput = $GLOBALS['_SECURE_REQUEST_BODY'];
    } else {
        $rawInput = file_get_contents('php://input');
    }
    
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        sendError('Dados inválidos', 400);
    }
    
    $pixKeyType = $data['pix_key_type'] ?? '';
    $pixKey = $data['pix_key'] ?? '';
    
    // Validar entrada
    if (empty($pixKeyType) || empty($pixKey)) {
        sendError('Tipo e chave PIX são obrigatórios', 400);
    }
    
    // Validar tipo de chave
    $validTypes = ['CPF', 'CNPJ', 'Email', 'Telefone', 'Chave Aleatória', 'ALEATORIA'];
    if (!in_array($pixKeyType, $validTypes)) {
        sendError('Tipo de chave inválido', 400);
    }
    
    // Log da requisição
    error_log("[PIX Verify] User: " . $user['id'] . " - Type: $pixKeyType, Key: " . substr($pixKey, 0, 5) . "***");
    
    // =====================================================
    // IMPLEMENTAÇÃO COM PSP
    // =====================================================
    // 
    // Descomente a função do PSP que você contratar:
    // 
    // $result = consultaCelcoin($pixKey);
    // $result = consultaK8($pixKeyType, $pixKey);
    // $result = consultaBankly($pixKeyType, $pixKey);
    // 
    // =====================================================
    
    // Por enquanto, usar resposta mock para desenvolvimento
    // REMOVA ESTE BLOCO após implementar integração real
    $result = consultaMock($pixKeyType, $pixKey);
    
    // Processar resultado
    if (isset($result['error'])) {
        sendError($result['error'], 400);
    }
    
    sendSuccess([
        'owner_name' => $result['owner_name'],
        'bank' => $result['bank'] ?? '',
        'account_type' => $result['account_type'] ?? ''
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("[PIX Verify] Error: " . $e->getMessage());
    sendError('Erro ao verificar chave PIX: ' . $e->getMessage(), 500);
}

// =====================================================
// FUNÇÕES DE CONSULTA AOS PSPs
// =====================================================

/**
 * Consulta MOCK para desenvolvimento
 * REMOVA após implementar integração real
 */
function consultaMock($pixKeyType, $pixKey) {
    // Simular delay de rede
    usleep(300000); // 300ms
    
    // Validar formato básico da chave
    $isValid = false;
    
    switch ($pixKeyType) {
        case 'CPF':
            // CPF: 11 dígitos
            $cleanKey = preg_replace('/\D/', '', $pixKey);
            $isValid = strlen($cleanKey) === 11;
            break;
            
        case 'CNPJ':
            // CNPJ: 14 dígitos
            $cleanKey = preg_replace('/\D/', '', $pixKey);
            $isValid = strlen($cleanKey) === 14;
            break;
            
        case 'Email':
            $isValid = filter_var($pixKey, FILTER_VALIDATE_EMAIL) !== false;
            break;
            
        case 'Telefone':
            // Telefone: 10-11 dígitos
            $cleanKey = preg_replace('/\D/', '', $pixKey);
            $isValid = strlen($cleanKey) >= 10 && strlen($cleanKey) <= 11;
            break;
            
        case 'Chave Aleatória':
        case 'ALEATORIA':
            // UUID: 32-36 caracteres
            $isValid = strlen($pixKey) >= 32 && strlen($pixKey) <= 36;
            break;
    }
    
    if (!$isValid) {
        return ['error' => 'Formato de chave inválido para o tipo selecionado'];
    }
    
    // Retornar dados mock
    // Em produção, isso viria do DICT via PSP
    return [
        'success' => true,
        'owner_name' => 'Titular da Conta (Mock)',
        'bank' => 'Banco Exemplo',
        'account_type' => 'CONTA_CORRENTE'
    ];
}

/**
 * Consulta via Celcoin
 * Documentação: https://developers.celcoin.com.br/reference/consulta-dados-bancarios-de-uma-chave-pix
 */
function consultaCelcoin($pixKey) {
    $celcoinUrl = 'https://sandbox.openfinance.celcoin.dev/pix/v1/dict/v2/key';
    $celcoinToken = getenv('CELCOIN_TOKEN');
    $celcoinPayerId = getenv('CELCOIN_PAYER_ID');
    
    if (empty($celcoinToken) || empty($celcoinPayerId)) {
        error_log("[PIX Verify] Celcoin credentials not configured");
        return ['error' => 'Serviço de verificação não configurado'];
    }
    
    $ch = curl_init($celcoinUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $celcoinToken,
            'includeStatistics: false'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'payerId' => $celcoinPayerId,
            'key' => $pixKey
        ]),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("[PIX Verify] Celcoin curl error: " . $curlError);
        return ['error' => 'Erro de conexão com serviço de verificação'];
    }
    
    if ($httpCode !== 200) {
        error_log("[PIX Verify] Celcoin HTTP error: " . $httpCode . " - " . $response);
        return ['error' => 'Chave PIX não encontrada'];
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['account']['owner']['name'])) {
        return [
            'success' => true,
            'owner_name' => $data['account']['owner']['name'],
            'bank' => $data['account']['participant']['name'] ?? '',
            'account_type' => $data['account']['accountType'] ?? ''
        ];
    }
    
    return ['error' => 'Chave PIX não encontrada'];
}

/**
 * Consulta via K8 Fintech
 * Documentação: https://docs.meuk8.com.br/reference/getpix
 */
function consultaK8($pixKeyType, $pixKey) {
    $k8Url = 'https://api-clientes.meuk8.com.br/api/v1/pagamento/consulta/pix';
    $k8ClientId = getenv('K8_CLIENT_ID');
    $k8Token = getenv('K8_TOKEN');
    
    if (empty($k8ClientId) || empty($k8Token)) {
        error_log("[PIX Verify] K8 credentials not configured");
        return ['error' => 'Serviço de verificação não configurado'];
    }
    
    // Mapear tipo de chave para o formato K8
    $typeMap = [
        'CPF' => 'documentNumber',
        'CNPJ' => 'documentNumber',
        'Email' => 'email',
        'Telefone' => 'phoneNumber',
        'Chave Aleatória' => 'randomKey',
        'ALEATORIA' => 'randomKey'
    ];
    
    $keyField = $typeMap[$pixKeyType] ?? 'documentNumber';
    
    $payload = [
        'type' => $pixKeyType,
        $keyField => $pixKey
    ];
    
    $ch = curl_init($k8Url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'client-id: ' . $k8ClientId,
            'Authorization: Bearer ' . $k8Token
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("[PIX Verify] K8 curl error: " . $curlError);
        return ['error' => 'Erro de conexão com serviço de verificação'];
    }
    
    if ($httpCode !== 200) {
        error_log("[PIX Verify] K8 HTTP error: " . $httpCode . " - " . $response);
        return ['error' => 'Chave PIX não encontrada'];
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['name'])) {
        return [
            'success' => true,
            'owner_name' => $data['name'],
            'bank' => $data['bank'] ?? '',
            'account_type' => ''
        ];
    }
    
    return ['error' => 'Chave PIX não encontrada'];
}

/**
 * Consulta via Bankly
 * Documentação: https://docs.bankly.com.br/docs/pix-consulta-por-chaves
 */
function consultaBankly($pixKeyType, $pixKey) {
    $banklyUrl = 'https://api.sandbox.bankly.com.br/pix/entries/' . urlencode($pixKey);
    $banklyToken = getenv('BANKLY_TOKEN');
    
    if (empty($banklyToken)) {
        error_log("[PIX Verify] Bankly credentials not configured");
        return ['error' => 'Serviço de verificação não configurado'];
    }
    
    $ch = curl_init($banklyUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $banklyToken,
            'api-version: 1.0'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("[PIX Verify] Bankly curl error: " . $curlError);
        return ['error' => 'Erro de conexão com serviço de verificação'];
    }
    
    if ($httpCode !== 200) {
        error_log("[PIX Verify] Bankly HTTP error: " . $httpCode . " - " . $response);
        return ['error' => 'Chave PIX não encontrada'];
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['holder']['name'])) {
        return [
            'success' => true,
            'owner_name' => $data['holder']['name'],
            'bank' => $data['participant']['name'] ?? '',
            'account_type' => $data['account']['type'] ?? ''
        ];
    }
    
    return ['error' => 'Chave PIX não encontrada'];
}
?>

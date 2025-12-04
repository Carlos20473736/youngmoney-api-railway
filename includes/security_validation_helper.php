<?php
/**
 * Security Validation Helper
 * Função helper para validar headers de segurança em qualquer endpoint
 */

require_once __DIR__ . '/xreq_manager.php';

/**
 * Valida headers de segurança obrigatórios
 * 
 * @param mysqli $conn Conexão com banco de dados
 * @param array $user Usuário autenticado
 * @return array Resultado da validação
 */
function validateSecurityHeaders($conn, $user) {
    // Verificar método HTTP - apenas POST/PUT precisam de validação completa
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // GET não precisa de validação de headers de segurança
    if ($method === 'GET') {
        return [
            'valid' => true,
            'score' => 100,
            'message' => 'GET request - security headers validation skipped',
            'method' => 'GET'
        ];
    }
    
    // Extrair headers do $_SERVER
    $allHeaders = [];
    foreach ($_SERVER as $key => $value) {
        if (substr($key, 0, 5) == 'HTTP_') {
            // Converter HTTP_X_FULL_REQUEST_HASH para X-FULL-REQUEST-HASH
            $header = substr($key, 5); // Remove HTTP_
            $header = str_replace('_', '-', $header); // Substitui _ por -
            $allHeaders[$header] = $value;
        }
    }
    
    // Headers obrigatórios
    $requiredHeaders = [
        'X-REQ',
        'X-REQUEST-ID',
        'X-FULL-REQUEST-HASH',
        'X-DEVICE-MODEL',
        'X-PLATFORM-VERSION',
        'X-REQUEST-WINDOW'
    ];
    
    // Verificar presença de todos os headers obrigatórios
    foreach ($requiredHeaders as $requiredHeader) {
        if (!isset($allHeaders[$requiredHeader]) || empty($allHeaders[$requiredHeader])) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => "Missing required security header: $requiredHeader",
                'code' => 'MISSING_SECURITY_HEADER'
            ]);
            exit;
        }
    }
    
    // Validar X-REQ (token rotativo gerado pelo app)
    $xReq = $allHeaders['X-REQ'];
    
    // Extrair User-Agent para validação da assinatura
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'okhttp/4.12.0';
    
    // Validação rigorosa: verificar assinatura MD5 e anti-replay
    try {
        validateXReqToken($conn, $user, $xReq, $userAgent);
    } catch (Exception $e) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'X-REQ validation failed: ' . $e->getMessage(),
            'code' => 'INVALID_XREQ_TOKEN'
        ]);
        exit;
    }
    
    // Validar X-REQUEST-ID (deve ser UUID único)
    $requestId = $allHeaders['X-REQUEST-ID'];
    if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $requestId)) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid X-REQUEST-ID format (must be UUID)',
            'code' => 'INVALID_REQUEST_ID'
        ]);
        exit;
    }
    
    // Verificar se X-REQUEST-ID já foi usado (anti-replay)
    $stmt = $conn->prepare("SELECT id FROM request_ids WHERE request_id = ? AND user_id = ? LIMIT 1");
    $userId = $user['id'];
    $stmt->bind_param("si", $requestId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'X-REQUEST-ID already used (replay attack detected)',
            'code' => 'DUPLICATE_REQUEST_ID'
        ]);
        exit;
    }
    $stmt->close();
    
    // Registrar X-REQUEST-ID como usado
    try {
        $stmt = $conn->prepare("INSERT INTO request_ids (user_id, request_id, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("is", $userId, $requestId);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("[SECURITY] Failed to save request_id: " . $e->getMessage());
    }
    
    // Validar X-FULL-REQUEST-HASH (formato SHA256)
    $fullRequestHash = $allHeaders['X-FULL-REQUEST-HASH'];
    if (!preg_match('/^[a-f0-9]{64}$/i', $fullRequestHash)) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid X-FULL-REQUEST-HASH format (must be SHA256)',
            'code' => 'INVALID_SECURITY_HEADER'
        ]);
        exit;
    }
    
    // Validar X-DEVICE-MODEL (não vazio, máx 100 chars)
    $deviceModel = $allHeaders['X-DEVICE-MODEL'];
    if (strlen($deviceModel) > 100) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid X-DEVICE-MODEL (max 100 characters)',
            'code' => 'INVALID_SECURITY_HEADER'
        ]);
        exit;
    }
    
    // Validar X-PLATFORM-VERSION (não vazio, máx 50 chars)
    $platformVersion = $allHeaders['X-PLATFORM-VERSION'];
    if (strlen($platformVersion) > 50) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid X-PLATFORM-VERSION (max 50 characters)',
            'code' => 'INVALID_SECURITY_HEADER'
        ]);
        exit;
    }
    
    // Validar X-REQUEST-WINDOW (deve ser numérico)
    $requestWindow = $allHeaders['X-REQUEST-WINDOW'];
    if (!is_numeric($requestWindow)) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid X-REQUEST-WINDOW (must be numeric)',
            'code' => 'INVALID_SECURITY_HEADER'
        ]);
        exit;
    }
    
    // Log para debug
    error_log("[SECURITY] Headers validated - Hash: $fullRequestHash, Device: $deviceModel, Platform: $platformVersion, Window: $requestWindow");
    
    // Salvar métricas de segurança (opcional)
    try {
        $stmt = $conn->prepare("INSERT INTO security_metrics (user_id, headers_count, device_model, platform_version, request_window, full_request_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $headersCount = count($allHeaders);
        $userId = $user['id'];
        $stmt->bind_param("iissis", $userId, $headersCount, $deviceModel, $platformVersion, $requestWindow, $fullRequestHash);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Não bloquear se falhar ao salvar métricas
        error_log("[SECURITY] Failed to save metrics: " . $e->getMessage());
    }
    
    return [
        'valid' => true,
        'score' => 100,
        'message' => 'Security headers validated',
        'headers_validated' => $requiredHeaders,
        'headers_count' => count($allHeaders)
    ];
}

/**
 * Verifica se o endpoint atual é público (não precisa de validação de headers)
 * 
 * @return bool
 */
function isPublicEndpoint() {
    $publicEndpoints = [
        '/api/v1/auth/google-login.php',
        '/api/v1/auth/device-login.php',
        '/api/v1/invite/validate.php',
        '/api/v1/config.php',
        '/api/v1/config-simple.php',
        '/admin/'
    ];
    
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    
    foreach ($publicEndpoints as $endpoint) {
        if (strpos($requestUri, $endpoint) !== false) {
            return true;
        }
    }
    
    return false;
}
?>

<?php
/**
 * Headers Validator - Validação de Headers Customizados
 * 
 * Valida todos os headers enviados pelo app Android:
 * - Authorization: Bearer {token}
 * - X-Req: {encrypted_request_id}
 * - X-Client-Type: mobile/web
 * - X-Request-Timestamp: {unix_timestamp}
 * - X-Session-ID: {uuid}
 * - X-Request-ID: {uuid}
 * - X-User-Agent-Hash: {sha256_hash}
 * 
 * @version 1.0.0
 * @date 2025-12-02
 */

class HeadersValidator {
    
    private $conn;
    private $errors = [];
    private $headers = [];
    
    // Configurações
    const TIMESTAMP_TOLERANCE = 300; // 5 minutos (300 segundos)
    const REQUIRED_HEADERS = [
        'X-Client-Type',
        'X-Request-Timestamp',
        'X-Session-ID',
        'X-Request-ID'
    ];
    
    // Headers opcionais (não obrigatórios para login)
    const OPTIONAL_HEADERS = [
        'Authorization',
        'X-Req',
        'X-User-Agent-Hash'
    ];
    
    public function __construct($dbConnection = null) {
        $this->conn = $dbConnection;
        $this->loadHeaders();
    }
    
    /**
     * Carrega todos os headers HTTP da requisição
     */
    private function loadHeaders() {
        // Método 1: getallheaders() (Apache)
        if (function_exists('getallheaders')) {
            $this->headers = getallheaders();
        } else {
            // Método 2: $_SERVER (Nginx/outros)
            foreach ($_SERVER as $key => $value) {
                if (substr($key, 0, 5) === 'HTTP_') {
                    $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                    $this->headers[$header] = $value;
                }
            }
        }
        
        // Normalizar chaves (case-insensitive)
        $this->headers = array_change_key_case($this->headers, CASE_LOWER);
    }
    
    /**
     * Obtém um header específico
     */
    public function getHeader($name) {
        $name = strtolower($name);
        return $this->headers[$name] ?? null;
    }
    
    /**
     * Valida todos os headers obrigatórios
     * 
     * @param bool $requireAuth Se true, exige Authorization header
     * @return bool True se válido, False se inválido
     */
    public function validate($requireAuth = true) {
        $this->errors = [];
        
        // 1. Validar headers obrigatórios
        foreach (self::REQUIRED_HEADERS as $header) {
            if (!$this->getHeader($header)) {
                $this->errors[] = "Missing required header: $header";
            }
        }
        
        // 2. Validar Authorization se obrigatório
        if ($requireAuth && !$this->getHeader('authorization')) {
            $this->errors[] = "Missing Authorization header";
        }
        
        // Se já tem erros, não continuar
        if (!empty($this->errors)) {
            return false;
        }
        
        // 3. Validar formato de cada header
        $this->validateClientType();
        $this->validateTimestamp();
        $this->validateSessionID();
        $this->validateRequestID();
        
        // 4. Validar Authorization se presente
        if ($this->getHeader('authorization')) {
            $this->validateAuthorization();
        }
        
        // 5. Validar X-Req se presente
        if ($this->getHeader('x-req')) {
            $this->validateXReq();
        }
        
        // 6. Validar User-Agent-Hash se presente
        if ($this->getHeader('x-user-agent-hash')) {
            $this->validateUserAgentHash();
        }
        
        return empty($this->errors);
    }
    
    /**
     * Valida X-Client-Type
     */
    private function validateClientType() {
        $clientType = $this->getHeader('x-client-type');
        
        if (!$clientType) {
            $this->errors[] = "X-Client-Type is required";
            return;
        }
        
        $validTypes = ['mobile', 'web', 'desktop', 'api'];
        if (!in_array(strtolower($clientType), $validTypes)) {
            $this->errors[] = "Invalid X-Client-Type. Must be: " . implode(', ', $validTypes);
        }
    }
    
    /**
     * Valida X-Request-Timestamp
     * Previne replay attacks verificando se o timestamp é recente
     */
    private function validateTimestamp() {
        $timestamp = $this->getHeader('x-request-timestamp');
        
        if (!$timestamp) {
            $this->errors[] = "X-Request-Timestamp is required";
            return;
        }
        
        // Verificar se é numérico
        if (!is_numeric($timestamp)) {
            $this->errors[] = "X-Request-Timestamp must be a valid Unix timestamp";
            return;
        }
        
        $currentTime = time();
        $timeDiff = abs($currentTime - $timestamp);
        
        // Verificar se está dentro da tolerância (5 minutos)
        if ($timeDiff > self::TIMESTAMP_TOLERANCE) {
            $this->errors[] = "X-Request-Timestamp is too old or too far in the future (tolerance: " . self::TIMESTAMP_TOLERANCE . "s)";
        }
    }
    
    /**
     * Valida X-Session-ID
     */
    private function validateSessionID() {
        $sessionId = $this->getHeader('x-session-id');
        
        if (!$sessionId) {
            $this->errors[] = "X-Session-ID is required";
            return;
        }
        
        // Validar formato UUID
        if (!$this->isValidUUID($sessionId)) {
            $this->errors[] = "X-Session-ID must be a valid UUID";
        }
    }
    
    /**
     * Valida X-Request-ID
     */
    private function validateRequestID() {
        $requestId = $this->getHeader('x-request-id');
        
        if (!$requestId) {
            $this->errors[] = "X-Request-ID is required";
            return;
        }
        
        // Validar formato UUID
        if (!$this->isValidUUID($requestId)) {
            $this->errors[] = "X-Request-ID must be a valid UUID";
        }
        
        // Verificar se já foi usado (prevenir replay attack)
        if ($this->conn && $this->isRequestIDUsed($requestId)) {
            $this->errors[] = "X-Request-ID already used (replay attack detected)";
        }
    }
    
    /**
     * Valida Authorization Bearer token
     */
    private function validateAuthorization() {
        $auth = $this->getHeader('authorization');
        
        if (!$auth) {
            return; // Opcional
        }
        
        // Verificar formato "Bearer {token}"
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
            $this->errors[] = "Authorization must be in format: Bearer {token}";
            return;
        }
        
        $token = $matches[1];
        
        // Validar token no banco de dados
        if ($this->conn && !$this->isValidToken($token)) {
            $this->errors[] = "Invalid or expired authorization token";
        }
    }
    
    /**
     * Valida X-Req (encrypted request ID)
     */
    private function validateXReq() {
        $xreq = $this->getHeader('x-req');
        
        if (!$xreq) {
            return; // Opcional
        }
        
        // Verificar se não está vazio
        if (strlen($xreq) < 10) {
            $this->errors[] = "X-Req is too short";
            return;
        }
        
        // Verificar se já foi usado (prevenir replay attack)
        if ($this->conn && $this->isXReqUsed($xreq)) {
            $this->errors[] = "X-Req already used (replay attack detected)";
        }
    }
    
    /**
     * Valida X-User-Agent-Hash
     */
    private function validateUserAgentHash() {
        $hash = $this->getHeader('x-user-agent-hash');
        
        if (!$hash) {
            return; // Opcional
        }
        
        // Verificar se é um hash SHA-256 válido (64 caracteres hexadecimais)
        if (!preg_match('/^[a-f0-9]{64}$/i', $hash)) {
            $this->errors[] = "X-User-Agent-Hash must be a valid SHA-256 hash";
        }
    }
    
    /**
     * Verifica se é um UUID válido
     */
    private function isValidUUID($uuid) {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
    }
    
    /**
     * Verifica se o Request ID já foi usado
     */
    private function isRequestIDUsed($requestId) {
        if (!$this->conn) return false;
        
        try {
            $stmt = $this->conn->prepare("SELECT id FROM used_request_ids WHERE request_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
            $stmt->bind_param("s", $requestId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return true; // Já foi usado
            }
            
            // Registrar como usado
            $stmt = $this->conn->prepare("INSERT INTO used_request_ids (request_id, created_at) VALUES (?, NOW())");
            $stmt->bind_param("s", $requestId);
            $stmt->execute();
            
            return false;
        } catch (Exception $e) {
            // Se a tabela não existir, ignorar (não bloquear requisições)
            return false;
        }
    }
    
    /**
     * Verifica se o X-Req já foi usado
     */
    private function isXReqUsed($xreq) {
        if (!$this->conn) return false;
        
        try {
            $stmt = $this->conn->prepare("SELECT id FROM used_xreqs WHERE xreq = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
            $stmt->bind_param("s", $xreq);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return true; // Já foi usado
            }
            
            // Registrar como usado
            $stmt = $this->conn->prepare("INSERT INTO used_xreqs (xreq, created_at) VALUES (?, NOW())");
            $stmt->bind_param("s", $xreq);
            $stmt->execute();
            
            return false;
        } catch (Exception $e) {
            // Se a tabela não existir, ignorar
            return false;
        }
    }
    
    /**
     * Verifica se o token é válido
     */
    private function isValidToken($token) {
        if (!$this->conn) return true; // Se não tem conexão, aceitar
        
        try {
            $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE token = ? AND token_expires_at > NOW()");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->num_rows > 0;
        } catch (Exception $e) {
            return true; // Em caso de erro, não bloquear
        }
    }
    
    /**
     * Retorna os erros de validação
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Retorna mensagem de erro formatada
     */
    public function getErrorMessage() {
        return implode('; ', $this->errors);
    }
    
    /**
     * Retorna resposta JSON de erro
     */
    public function sendErrorResponse() {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid request headers',
            'errors' => $this->errors
        ]);
        exit;
    }
    
    /**
     * Extrai user_id do token Bearer
     */
    public function getUserIdFromToken() {
        $auth = $this->getHeader('authorization');
        
        if (!$auth || !preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
            return null;
        }
        
        $token = $matches[1];
        
        if (!$this->conn) return null;
        
        try {
            $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                return $row['user_id'];
            }
        } catch (Exception $e) {
            return null;
        }
        
        return null;
    }
    
    /**
     * Log de validação (para auditoria)
     */
    public function logValidation($success, $endpoint) {
        if (!$this->conn) return;
        
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $requestId = $this->getHeader('x-request-id') ?? 'unknown';
            $errors = $success ? null : json_encode($this->errors);
            
            $stmt = $this->conn->prepare("INSERT INTO headers_validation_log (endpoint, request_id, ip, user_agent, success, errors, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssssis", $endpoint, $requestId, $ip, $userAgent, $success, $errors);
            $stmt->execute();
        } catch (Exception $e) {
            // Ignorar erros de log
        }
    }
}

/**
 * Função helper para validar headers rapidamente
 * 
 * @param mysqli $conn Conexão com banco de dados
 * @param bool $requireAuth Se true, exige Authorization header
 * @return HeadersValidator|null Retorna validator se válido, null se inválido (já envia resposta de erro)
 */
function validateRequestHeaders($conn = null, $requireAuth = true) {
    $validator = new HeadersValidator($conn);
    
    if (!$validator->validate($requireAuth)) {
        $validator->sendErrorResponse();
        return null;
    }
    
    return $validator;
}

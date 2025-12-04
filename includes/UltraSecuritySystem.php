<?php
/**
 * Ultra Security System - Sistema de Segurança Impossível de Burlar
 * 
 * Implementa 5 camadas de segurança:
 * 1. Desafio-Resposta Criptográfico
 * 2. Assinatura HMAC com Timestamp
 * 3. Prova de Trabalho (PoW)
 * 4. Device Attestation
 * 5. Rate Limiting Inteligente
 * 
 * @version 2.0.0
 * @date 2025-12-02
 */

class UltraSecuritySystem {
    
    private $conn;
    private $errors = [];
    
    // Chaves secretas (MUDAR EM PRODUÇÃO!)
    private const SECRET_KEY = 'your-ultra-secret-key-change-in-production-2025';
    private const CHALLENGE_SECRET = 'challenge-secret-key-never-expose-this-2025';
    
    // Configurações
    private const TIMESTAMP_TOLERANCE = 30; // 30 segundos
    private const POW_DIFFICULTY = 4; // Número de zeros no início do hash
    private const RATE_LIMIT_REQUESTS = 60; // Requisições por minuto
    private const RATE_LIMIT_WINDOW = 60; // Janela em segundos
    
    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }
    
    /**
     * Valida requisição completa
     * Retorna array com dados do usuário se válido, ou false se inválido
     */
    public function validateRequest($requireAuth = true) {
        $this->errors = [];
        
        // 1. Verificar headers obrigatórios
        $authToken = $this->getHeader('Authorization');
        $signature = $this->getHeader('X-Signature');
        $timestamp = $this->getHeader('X-Timestamp');
        $deviceId = $this->getHeader('X-Device-ID');
        $challenge = $this->getHeader('X-Challenge');
        $response = $this->getHeader('X-Response');
        $pow = $this->getHeader('X-Proof-Of-Work');
        
        // 2. Validar timestamp (30 segundos)
        if (!$this->validateTimestamp($timestamp)) {
            return $this->fail("Invalid or expired timestamp");
        }
        
        // 3. Validar Prova de Trabalho (PoW)
        if (!$this->validateProofOfWork($pow, $timestamp)) {
            return $this->fail("Invalid proof of work");
        }
        
        // 4. Validar Desafio-Resposta
        if (!$this->validateChallengeResponse($challenge, $response, $deviceId)) {
            return $this->fail("Invalid challenge-response");
        }
        
        // 5. Validar Assinatura HMAC
        if (!$this->validateSignature($signature, $timestamp, $deviceId, $authToken)) {
            return $this->fail("Invalid signature");
        }
        
        // 6. Validar Token (se obrigatório)
        if ($requireAuth) {
            $userData = $this->validateAuthToken($authToken);
            if (!$userData) {
                return $this->fail("Invalid or expired authentication token");
            }
        } else {
            $userData = null;
        }
        
        // 7. Validar Device Attestation
        if (!$this->validateDeviceAttestation($deviceId)) {
            return $this->fail("Device attestation failed - suspicious device detected");
        }
        
        // 8. Rate Limiting Inteligente
        if (!$this->checkRateLimit($deviceId)) {
            return $this->fail("Rate limit exceeded - too many requests");
        }
        
        // 9. Registrar requisição bem-sucedida
        $this->logSuccessfulRequest($deviceId, $userData);
        
        return $userData;
    }
    
    /**
     * Gera desafio para o cliente
     */
    public function generateChallenge($deviceId) {
        $challenge = bin2hex(random_bytes(16));
        $expiresAt = time() + 60; // Válido por 1 minuto
        
        // Salvar desafio no banco
        $stmt = $this->conn->prepare("
            INSERT INTO security_challenges (device_id, challenge, expires_at, created_at)
            VALUES (?, ?, FROM_UNIXTIME(?), NOW())
            ON DUPLICATE KEY UPDATE 
                challenge = VALUES(challenge),
                expires_at = VALUES(expires_at),
                created_at = NOW()
        ");
        $stmt->bind_param("ssi", $deviceId, $challenge, $expiresAt);
        $stmt->execute();
        
        return [
            'challenge' => $challenge,
            'expires_at' => $expiresAt,
            'difficulty' => self::POW_DIFFICULTY
        ];
    }
    
    /**
     * Valida timestamp (30 segundos de tolerância)
     */
    private function validateTimestamp($timestamp) {
        if (!$timestamp || !is_numeric($timestamp)) {
            return false;
        }
        
        $now = time();
        $diff = abs($now - $timestamp);
        
        return $diff <= self::TIMESTAMP_TOLERANCE;
    }
    
    /**
     * Valida Prova de Trabalho (PoW)
     * Cliente precisa encontrar nonce que gera hash com N zeros no início
     */
    private function validateProofOfWork($pow, $timestamp) {
        if (!$pow || !$timestamp) {
            return false;
        }
        
        // Formato: nonce:hash
        $parts = explode(':', $pow);
        if (count($parts) !== 2) {
            return false;
        }
        
        list($nonce, $hash) = $parts;
        
        // Verificar se hash está correto
        $expectedHash = hash('sha256', $timestamp . $nonce . self::SECRET_KEY);
        
        if ($hash !== $expectedHash) {
            return false;
        }
        
        // Verificar dificuldade (N zeros no início)
        $zeros = str_repeat('0', self::POW_DIFFICULTY);
        return substr($hash, 0, self::POW_DIFFICULTY) === $zeros;
    }
    
    /**
     * Valida Desafio-Resposta
     * Cliente precisa resolver desafio usando chave secreta
     */
    private function validateChallengeResponse($challenge, $response, $deviceId) {
        if (!$challenge || !$response || !$deviceId) {
            return false;
        }
        
        // Buscar desafio no banco
        $stmt = $this->conn->prepare("
            SELECT challenge, expires_at 
            FROM security_challenges 
            WHERE device_id = ? 
            AND challenge = ?
            AND expires_at > NOW()
        ");
        $stmt->bind_param("ss", $deviceId, $challenge);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        // Calcular resposta esperada
        $expectedResponse = hash_hmac('sha256', $challenge . $deviceId, self::CHALLENGE_SECRET);
        
        // Comparação segura contra timing attacks
        return hash_equals($expectedResponse, $response);
    }
    
    /**
     * Valida Assinatura HMAC
     * Assina: timestamp + deviceId + authToken + body
     */
    private function validateSignature($signature, $timestamp, $deviceId, $authToken) {
        if (!$signature) {
            return false;
        }
        
        // Obter body da requisição
        $body = file_get_contents('php://input');
        
        // Calcular assinatura esperada
        $data = $timestamp . $deviceId . $authToken . $body;
        $expectedSignature = hash_hmac('sha256', $data, self::SECRET_KEY);
        
        // Comparação segura
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Valida Token de Autenticação
     * Retorna dados do usuário se válido
     */
    private function validateAuthToken($authHeader) {
        if (!$authHeader) {
            return false;
        }
        
        // Extrair token do header "Bearer {token}"
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return false;
        }
        
        $token = $matches[1];
        
        // Buscar usuário no banco
        $stmt = $this->conn->prepare("
            SELECT id, email, google_id 
            FROM users 
            WHERE token = ? 
            AND (token_expires_at IS NULL OR token_expires_at > NOW())
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Valida Device Attestation
     * Verifica se dispositivo não está em blacklist
     */
    private function validateDeviceAttestation($deviceId) {
        if (!$deviceId) {
            return false;
        }
        
        // Verificar se dispositivo está em blacklist
        $stmt = $this->conn->prepare("
            SELECT id FROM device_blacklist 
            WHERE device_id = ? 
            AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->bind_param("s", $deviceId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return false; // Dispositivo bloqueado
        }
        
        return true;
    }
    
    /**
     * Rate Limiting Inteligente
     * Limita requisições por Device ID
     */
    private function checkRateLimit($deviceId) {
        if (!$deviceId) {
            return false;
        }
        
        // Contar requisições na janela de tempo
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM request_log 
            WHERE device_id = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->bind_param("si", $deviceId, self::RATE_LIMIT_WINDOW);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] >= self::RATE_LIMIT_REQUESTS) {
            // Adicionar à blacklist temporária
            $this->addToBlacklist($deviceId, "Rate limit exceeded", 3600); // 1 hora
            return false;
        }
        
        return true;
    }
    
    /**
     * Adiciona dispositivo à blacklist
     */
    private function addToBlacklist($deviceId, $reason, $duration = 3600) {
        $expiresAt = time() + $duration;
        
        $stmt = $this->conn->prepare("
            INSERT INTO device_blacklist (device_id, reason, expires_at, created_at)
            VALUES (?, ?, FROM_UNIXTIME(?), NOW())
            ON DUPLICATE KEY UPDATE 
                reason = VALUES(reason),
                expires_at = VALUES(expires_at)
        ");
        $stmt->bind_param("ssi", $deviceId, $reason, $expiresAt);
        $stmt->execute();
    }
    
    /**
     * Registra requisição bem-sucedida
     */
    private function logSuccessfulRequest($deviceId, $userData) {
        $userId = $userData ? $userData['id'] : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $endpoint = $_SERVER['REQUEST_URI'] ?? 'unknown';
        
        $stmt = $this->conn->prepare("
            INSERT INTO request_log (device_id, user_id, ip, endpoint, success, created_at)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");
        $stmt->bind_param("siss", $deviceId, $userId, $ip, $endpoint);
        $stmt->execute();
    }
    
    /**
     * Registra falha de validação
     */
    private function fail($message) {
        $this->errors[] = $message;
        
        // Log de falha
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $endpoint = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $deviceId = $this->getHeader('X-Device-ID') ?? 'unknown';
        
        $stmt = $this->conn->prepare("
            INSERT INTO request_log (device_id, ip, endpoint, success, error_message, created_at)
            VALUES (?, ?, ?, 0, ?, NOW())
        ");
        $stmt->bind_param("ssss", $deviceId, $ip, $endpoint, $message);
        $stmt->execute();
        
        // Enviar resposta de erro
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Security validation failed',
            'code' => 'SECURITY_VALIDATION_FAILED'
        ]);
        
        return false;
    }
    
    /**
     * Obtém header HTTP
     */
    private function getHeader($name) {
        // Tentar getallheaders() primeiro
        if (function_exists('getallheaders')) {
            $headers = array_change_key_case(getallheaders(), CASE_LOWER);
            $name = strtolower($name);
            return $headers[$name] ?? null;
        }
        
        // Fallback para $_SERVER
        $name = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$name] ?? null;
    }
    
    /**
     * Retorna erros
     */
    public function getErrors() {
        return $this->errors;
    }
}

/**
 * Função helper para validar requisição
 */
function validateSecureRequest($conn, $requireAuth = true) {
    $security = new UltraSecuritySystem($conn);
    $userData = $security->validateRequest($requireAuth);
    
    if ($userData === false) {
        exit; // Já enviou resposta de erro
    }
    
    return $userData;
}

/**
 * Função helper para gerar desafio
 */
function generateSecurityChallenge($conn, $deviceId) {
    $security = new UltraSecuritySystem($conn);
    return $security->generateChallenge($deviceId);
}

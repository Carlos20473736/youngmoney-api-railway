<?php

/**
 * HeadersValidatorV2 - Validador de 30 Headers de Segurança
 * 
 * Valida todos os headers de segurança enviados pelo cliente
 * Implementa 30 camadas de validação para máxima proteção
 */
class HeadersValidatorV2 {
    
    // Headers obrigatórios que bloqueiam a requisição se ausentes
    // Apenas os mais essenciais para evitar bloqueios desnecessários
    private const REQUIRED_HEADERS = [
        'X-REQUEST-ID',
        'X-REQUEST-TIMESTAMP',
        'X-CLIENT-NONCE'
    ];
    
    // Headers recomendados que geram alertas se ausentes
    private const RECOMMENDED_HEADERS = [
        'X-APP-VERSION',
        'X-PLATFORM',
        'X-SESSION-ID',
        'X-DEVICE-FINGERPRINT',
        'X-DEVICE-ID',
        'X-API-VERSION',
        'X-REQUEST-SIGNATURE',
        'X-BODY-HASH',
        'X-KEY-WINDOW',
        'X-ENCRYPTION-VERSION',
        'X-DEVICE-MODEL',
        'X-PLATFORM-VERSION'
    ];
    
    private $conn;
    private $user;
    private $headers;
    private $body;
    private $alerts = [];
    
    public function __construct($conn, $user, $headers, $body) {
        $this->conn = $conn;
        $this->user = $user;
        $this->headers = $headers;
        $this->body = $body;
    }
    
    /**
     * Valida todos os headers de segurança
     */
    public function validateAll() {
        try {
            // 1. Validar presença de headers obrigatórios
            $this->validateRequiredHeaders();
            
            // 2. Validar identificação do dispositivo
            $this->validateDeviceIdentification();
            
            // 3. Validar identificação da requisição
            $this->validateRequestIdentification();
            
            // 4. Validar assinaturas e hashes
            $this->validateSignaturesAndHashes();
            
            // 5. Validar segurança e criptografia
            $this->validateSecurityParams();
            
            // 6. Validar controle de acesso
            $this->validateAccessControl();
            
            // 7. Validar anti-replay
            $this->validateAntiReplay();
            
            // 8. Registrar métricas de segurança
            $this->logSecurityMetrics();
            
            return [
                'valid' => true,
                'alerts' => $this->alerts,
                'security_score' => $this->calculateSecurityScore()
            ];
            
        } catch (Exception $e) {
            $this->logSecurityViolation($e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 1. Validar headers obrigatórios
     */
    private function validateRequiredHeaders() {
        foreach (self::REQUIRED_HEADERS as $header) {
            if (!isset($this->headers[$header]) || empty($this->headers[$header])) {
                throw new Exception("Header obrigatório ausente: $header");
            }
        }
        
        // Verificar headers recomendados
        foreach (self::RECOMMENDED_HEADERS as $header) {
            if (!isset($this->headers[$header])) {
                $this->alerts[] = "Header recomendado ausente: $header";
            }
        }
    }
    
    /**
     * 2. Validar identificação do dispositivo (5 headers)
     */
    private function validateDeviceIdentification() {
        // X-DEVICE-ID (recomendado, não obrigatório)
        if (isset($this->headers['X-DEVICE-ID'])) {
            $deviceId = $this->headers['X-DEVICE-ID'];
            if (!empty($deviceId) && !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $deviceId)) {
                $this->alerts[] = "X-DEVICE-ID inválido (deve ser UUID)";
            } else if (!empty($deviceId)) {
                // Verificar se device_id está registrado para este usuário
                $this->validateDeviceRegistration($deviceId);
            }
        }
        
        // X-App-Version
        if (isset($this->headers['X-App-Version'])) {
            $this->validateAppVersion($this->headers['X-App-Version']);
        }
        
        // X-Platform
        if (isset($this->headers['X-Platform'])) {
            $platform = $this->headers['X-Platform'];
            if (!in_array($platform, ['Android', 'iOS'])) {
                $this->alerts[] = "Plataforma desconhecida: $platform";
            }
        }
        
        // X-Device-Fingerprint
        if (isset($this->headers['X-Device-Fingerprint'])) {
            $this->validateDeviceFingerprint($this->headers['X-Device-Fingerprint']);
        }
    }
    
    /**
     * 3. Validar identificação da requisição (5 headers)
     */
    private function validateRequestIdentification() {
        // X-Request-ID (deve ser único)
        $requestId = $this->headers['X-Request-ID'];
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $requestId)) {
            throw new Exception("X-Request-ID inválido (deve ser UUID)");
        }
        
        // Verificar se request_id já foi usado
        if ($this->isRequestIdUsed($requestId)) {
            throw new Exception("X-Request-ID duplicado - possível replay attack");
        }
        
        // X-Request-Timestamp
        $timestamp = (int)$this->headers['X-Request-Timestamp'];
        $now = time();
        $diff = abs($now - $timestamp);
        
        if ($diff > 90) {
            throw new Exception("X-Request-Timestamp fora da janela permitida (±90s)");
        }
        
        // X-Request-Sequence
        if (isset($this->headers['X-Request-Sequence'])) {
            $this->validateRequestSequence((int)$this->headers['X-Request-Sequence']);
        }
        
        // X-Session-ID
        if (isset($this->headers['X-Session-ID'])) {
            $this->validateSessionId($this->headers['X-Session-ID']);
        }
        
        // X-Client-Nonce
        $clientNonce = $this->headers['X-Client-Nonce'];
        if (strlen($clientNonce) < 16) {
            throw new Exception("X-Client-Nonce muito curto (mínimo 16 caracteres)");
        }
    }
    
    /**
     * 4. Validar assinaturas e hashes (5 headers)
     */
    private function validateSignaturesAndHashes() {
        // X-Body-Hash
        $bodyHash = $this->headers['X-Body-Hash'];
        $calculatedHash = hash('sha256', $this->body);
        
        if ($bodyHash !== $calculatedHash) {
            throw new Exception("X-Body-Hash inválido - body foi modificado");
        }
        
        // X-Request-Signature
        $this->validateRequestSignature();
        
        // X-Headers-Hash
        if (isset($this->headers['X-Headers-Hash'])) {
            $this->validateHeadersHash();
        }
        
        // X-Full-Request-Hash
        if (isset($this->headers['X-Full-Request-Hash'])) {
            $this->validateFullRequestHash();
        }
    }
    
    /**
     * 5. Validar segurança e criptografia (5 headers)
     */
    private function validateSecurityParams() {
        // X-Encryption-Version
        $encVersion = $this->headers['X-Encryption-Version'];
        if ($encVersion !== 'v2') {
            throw new Exception("X-Encryption-Version inválida (esperado: v2)");
        }
        
        // X-Key-Window
        $keyWindow = (int)$this->headers['X-Key-Window'];
        $currentWindow = floor(time() / 30);
        
        // Permitir janela atual e ±3 janelas (90 segundos)
        if (abs($currentWindow - $keyWindow) > 3) {
            throw new Exception("X-Key-Window inválida");
        }
        
        // X-Security-Level
        if (isset($this->headers['X-Security-Level'])) {
            $level = (int)$this->headers['X-Security-Level'];
            if ($level < 3) {
                $this->alerts[] = "Nível de segurança baixo: $level";
            }
        }
        
        // X-Cipher-Algorithm
        if (isset($this->headers['X-Cipher-Algorithm'])) {
            $cipher = $this->headers['X-Cipher-Algorithm'];
            if ($cipher !== 'AES-256-CBC') {
                throw new Exception("Algoritmo de criptografia não suportado: $cipher");
            }
        }
        
        // X-HMAC-Algorithm
        if (isset($this->headers['X-HMAC-Algorithm'])) {
            $hmac = $this->headers['X-HMAC-Algorithm'];
            if ($hmac !== 'SHA256') {
                throw new Exception("Algoritmo HMAC não suportado: $hmac");
            }
        }
    }
    
    /**
     * 6. Validar controle de acesso (5 headers)
     */
    private function validateAccessControl() {
        // X-API-Version
        if (isset($this->headers['X-API-Version'])) {
            $apiVersion = $this->headers['X-API-Version'];
            if ($apiVersion !== 'v1') {
                throw new Exception("Versão da API não suportada: $apiVersion");
            }
        }
        
        // X-Client-Type
        if (isset($this->headers['X-Client-Type'])) {
            $clientType = $this->headers['X-Client-Type'];
            if (!in_array($clientType, ['mobile', 'web', 'admin'])) {
                throw new Exception("Tipo de cliente inválido: $clientType");
            }
        }
        
        // X-User-Agent-Hash
        if (isset($this->headers['X-User-Agent-Hash'])) {
            $this->validateUserAgentHash();
        }
        
        // X-Access-Token-Hash
        if (isset($this->headers['X-Access-Token-Hash'])) {
            $this->validateAccessTokenHash();
        }
    }
    
    /**
     * 7. Validar anti-replay (5 headers)
     */
    private function validateAntiReplay() {
        // X-Request-Window
        if (isset($this->headers['X-Request-Window'])) {
            $window = (int)$this->headers['X-Request-Window'];
            $currentWindow = floor(time() / 30);
            
            if (abs($currentWindow - $window) > 3) {
                throw new Exception("X-Request-Window expirada");
            }
        }
        
        // X-Nonce-Signature
        if (isset($this->headers['X-Nonce-Signature'])) {
            $this->validateNonceSignature();
        }
        
        // Registrar request_id para prevenir replay
        $this->registerRequestId($this->headers['X-Request-ID']);
    }
    
    /**
     * Validar assinatura da requisição
     */
    private function validateRequestSignature() {
        $signature = $this->headers['X-Request-Signature'];
        
        // Criar string para assinar
        $signatureData = implode('|', [
            $this->headers['X-Device-ID'],
            $this->headers['X-Request-ID'],
            $this->headers['X-Request-Timestamp'],
            $this->headers['X-Client-Nonce'],
            $this->headers['X-Body-Hash']
        ]);
        
        // Calcular assinatura esperada usando master_seed do usuário
        $expectedSignature = hash_hmac('sha256', $signatureData, $this->user['master_seed']);
        
        if (!hash_equals($expectedSignature, $signature)) {
            throw new Exception("X-Request-Signature inválida");
        }
    }
    
    /**
     * Validar device_id está registrado
     */
    private function validateDeviceRegistration($deviceId) {
        // Verificar se device_id está na tabela users
        if (isset($this->user['device_id']) && $this->user['device_id'] !== $deviceId) {
            // Device mudou - possível fraude
            $this->alerts[] = "Device ID mudou - possível transferência de conta";
            
            // Atualizar device_id (permitir mudança mas registrar)
            $stmt = $this->conn->prepare("UPDATE users SET device_id = ? WHERE id = ?");
            $stmt->bind_param("si", $deviceId, $this->user['id']);
            $stmt->execute();
        }
    }
    
    /**
     * Validar versão do app
     */
    private function validateAppVersion($version) {
        // Versão mínima suportada
        $minVersion = '1.0.0';
        
        if (version_compare($version, $minVersion, '<')) {
            throw new Exception("Versão do app desatualizada. Atualize para continuar.");
        }
    }
    
    /**
     * Validar fingerprint do dispositivo
     */
    private function validateDeviceFingerprint($fingerprint) {
        // Verificar se fingerprint mudou
        if (isset($this->user['device_fingerprint'])) {
            if ($this->user['device_fingerprint'] !== $fingerprint) {
                $this->alerts[] = "Device fingerprint mudou - possível mudança de dispositivo";
            }
        } else {
            // Primeira vez - salvar fingerprint
            $stmt = $this->conn->prepare("UPDATE users SET device_fingerprint = ? WHERE id = ?");
            $stmt->bind_param("si", $fingerprint, $this->user['id']);
            $stmt->execute();
        }
    }
    
    /**
     * Validar sequência de requisições
     */
    private function validateRequestSequence($sequence) {
        // Verificar se sequência está em ordem
        if (isset($this->user['last_request_sequence'])) {
            $lastSeq = (int)$this->user['last_request_sequence'];
            
            if ($sequence <= $lastSeq) {
                $this->alerts[] = "Sequência de requisição fora de ordem - possível replay";
            }
        }
        
        // Atualizar última sequência
        $stmt = $this->conn->prepare("UPDATE users SET last_request_sequence = ? WHERE id = ?");
        $stmt->bind_param("ii", $sequence, $this->user['id']);
        $stmt->execute();
    }
    
    /**
     * Validar session_id
     */
    private function validateSessionId($sessionId) {
        if (isset($this->user['session_id'])) {
            if ($this->user['session_id'] !== $sessionId) {
                throw new Exception("Session ID inválida - faça login novamente");
            }
        }
    }
    
    /**
     * Verificar se request_id já foi usado
     */
    private function isRequestIdUsed($requestId) {
        $stmt = $this->conn->prepare("SELECT id FROM request_ids WHERE request_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $stmt->bind_param("s", $requestId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    /**
     * Registrar request_id
     */
    private function registerRequestId($requestId) {
        $stmt = $this->conn->prepare("INSERT INTO request_ids (request_id, user_id, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("si", $requestId, $this->user['id']);
        $stmt->execute();
    }
    
    /**
     * Validar hash dos headers
     */
    private function validateHeadersHash() {
        // Criar string com todos os headers relevantes
        $headersString = '';
        $relevantHeaders = ['X-Device-ID', 'X-Request-ID', 'X-Request-Timestamp', 'X-Client-Nonce'];
        
        foreach ($relevantHeaders as $header) {
            if (isset($this->headers[$header])) {
                $headersString .= $this->headers[$header];
            }
        }
        
        $calculatedHash = hash('sha256', $headersString);
        
        if ($this->headers['X-Headers-Hash'] !== $calculatedHash) {
            throw new Exception("X-Headers-Hash inválido");
        }
    }
    
    /**
     * Validar hash completo da requisição
     */
    private function validateFullRequestHash() {
        // URL + Headers + Body
        $fullRequest = $_SERVER['REQUEST_URI'] . 
                      $this->headers['X-Headers-Hash'] . 
                      $this->headers['X-Body-Hash'];
        
        $calculatedHash = hash('sha256', $fullRequest);
        
        if ($this->headers['X-Full-Request-Hash'] !== $calculatedHash) {
            throw new Exception("X-Full-Request-Hash inválido");
        }
    }
    
    /**
     * Validar hash do User-Agent
     */
    private function validateUserAgentHash() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $calculatedHash = hash('sha256', $userAgent);
        
        if ($this->headers['X-User-Agent-Hash'] !== $calculatedHash) {
            throw new Exception("X-User-Agent-Hash inválido");
        }
    }
    
    /**
     * Validar hash do token de acesso
     */
    private function validateAccessTokenHash() {
        $token = $this->user['token'] ?? '';
        $calculatedHash = hash('sha256', $token);
        
        if ($this->headers['X-Access-Token-Hash'] !== $calculatedHash) {
            throw new Exception("X-Access-Token-Hash inválido");
        }
    }
    
    /**
     * Validar assinatura do nonce
     */
    private function validateNonceSignature() {
        $nonce = $this->headers['X-Client-Nonce'];
        $signature = $this->headers['X-Nonce-Signature'];
        
        $expectedSignature = hash_hmac('sha256', $nonce, $this->user['master_seed']);
        
        if (!hash_equals($expectedSignature, $signature)) {
            throw new Exception("X-Nonce-Signature inválida");
        }
    }
    
    /**
     * Calcular score de segurança (0-100)
     */
    private function calculateSecurityScore() {
        $score = 100;
        
        // Deduzir pontos por headers ausentes
        $score -= count($this->alerts) * 2;
        
        // Deduzir pontos por versão antiga
        if (isset($this->headers['X-App-Version'])) {
            $version = $this->headers['X-App-Version'];
            if (version_compare($version, '1.0.5', '<')) {
                $score -= 10;
            }
        }
        
        // Deduzir pontos por nível de segurança baixo
        if (isset($this->headers['X-Security-Level'])) {
            $level = (int)$this->headers['X-Security-Level'];
            if ($level < 3) {
                $score -= 15;
            }
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Registrar métricas de segurança
     */
    private function logSecurityMetrics() {
        $score = $this->calculateSecurityScore();
        
        $stmt = $this->conn->prepare("
            INSERT INTO security_metrics 
            (user_id, security_score, headers_count, alerts_count, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $headersCount = count($this->headers);
        $alertsCount = count($this->alerts);
        
        $stmt->bind_param("iiii", $this->user['id'], $score, $headersCount, $alertsCount);
        $stmt->execute();
    }
    
    /**
     * Registrar violação de segurança
     */
    private function logSecurityViolation($message) {
        $stmt = $this->conn->prepare("
            INSERT INTO security_violations 
            (user_id, violation_type, message, headers, created_at) 
            VALUES (?, 'header_validation', ?, ?, NOW())
        ");
        
        $headersJson = json_encode($this->headers);
        $stmt->bind_param("iss", $this->user['id'], $message, $headersJson);
        $stmt->execute();
    }
}

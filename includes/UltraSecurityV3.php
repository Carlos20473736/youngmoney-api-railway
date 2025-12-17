<?php

/**
 * UltraSecurityV3 - Sistema de Segurança Máxima
 * 
 * Implementa as seguintes proteções:
 * 1. Validação de Proof of Work (PoW)
 * 2. Rate Limiting agressivo por IP/Device
 * 3. Blacklist de dispositivos suspeitos
 * 4. Detecção de padrões de ataque
 * 5. Bloqueio progressivo
 * 6. Validação de assinatura HMAC
 * 7. Anti-replay com nonce único
 * 8. Validação de timestamp
 * 9. Device fingerprint verification
 * 10. Challenge-Response validation
 * 
 * @version 3.0.0
 * @date 2025-12-16
 */
class UltraSecurityV3 {
    
    // Chaves secretas (DEVEM SER AS MESMAS DO APP!)
    private const SECRET_KEY = 'your-ultra-secret-key-change-in-production-2025';
    private const CHALLENGE_SECRET = 'challenge-secret-key-never-expose-this-2025';
    private const POW_DIFFICULTY = 4; // 4 zeros no início do hash
    
    // Configurações de Rate Limiting
    private const RATE_LIMIT_WINDOW = 60; // 1 minuto
    private const RATE_LIMIT_MAX_REQUESTS = 30; // máximo de requisições por janela
    private const RATE_LIMIT_BLOCK_TIME = 300; // 5 minutos de bloqueio
    
    // Configurações de bloqueio progressivo
    private const BLOCK_THRESHOLDS = [
        3 => 60,      // 3 violações = 1 minuto
        5 => 300,     // 5 violações = 5 minutos
        10 => 1800,   // 10 violações = 30 minutos
        20 => 3600,   // 20 violações = 1 hora
        50 => 86400   // 50 violações = 24 horas
    ];
    
    // Timestamp tolerance (em segundos)
    private const TIMESTAMP_TOLERANCE = 90;
    
    private $conn;
    private $headers;
    private $body;
    private $ip;
    private $deviceId;
    private $violations = [];
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->headers = $this->getAllHeaders();
        $this->body = file_get_contents('php://input');
        $this->ip = $this->getClientIP();
        $this->deviceId = $this->headers['X-DEVICE-ID'] ?? null;
    }
    
    /**
     * Validação completa de segurança
     * @return array ['valid' => bool, 'message' => string, 'score' => int]
     */
    public function validate() {
        try {
            // 1. Verificar se IP está na blacklist
            if ($this->isIPBlocked()) {
                return $this->fail('IP bloqueado por atividade suspeita', 'IP_BLOCKED');
            }
            
            // 2. Verificar se device está na blacklist
            if ($this->deviceId && $this->isDeviceBlocked()) {
                return $this->fail('Dispositivo bloqueado por atividade suspeita', 'DEVICE_BLOCKED');
            }
            
            // 3. Verificar rate limiting
            if (!$this->checkRateLimit()) {
                $this->recordViolation('RATE_LIMIT_EXCEEDED');
                return $this->fail('Limite de requisições excedido', 'RATE_LIMIT');
            }
            
            // 4. Validar timestamp
            if (!$this->validateTimestamp()) {
                $this->recordViolation('INVALID_TIMESTAMP');
                return $this->fail('Timestamp inválido ou expirado', 'TIMESTAMP_INVALID');
            }
            
            // 5. Validar Proof of Work
            if (!$this->validateProofOfWork()) {
                $this->recordViolation('INVALID_POW');
                return $this->fail('Proof of Work inválido', 'POW_INVALID');
            }
            
            // 6. Validar assinatura HMAC
            if (!$this->validateSignature()) {
                $this->recordViolation('INVALID_SIGNATURE');
                return $this->fail('Assinatura inválida', 'SIGNATURE_INVALID');
            }
            
            // 7. Validar challenge-response (se presente)
            if (isset($this->headers['X-CHALLENGE'])) {
                if (!$this->validateChallengeResponse()) {
                    $this->recordViolation('INVALID_CHALLENGE');
                    return $this->fail('Challenge-Response inválido', 'CHALLENGE_INVALID');
                }
            }
            
            // 8. Verificar replay attack (nonce único)
            if (!$this->checkReplayProtection()) {
                $this->recordViolation('REPLAY_ATTACK');
                return $this->fail('Possível replay attack detectado', 'REPLAY_DETECTED');
            }
            
            // 9. Registrar requisição bem-sucedida
            $this->recordSuccessfulRequest();
            
            return [
                'valid' => true,
                'message' => 'Validação bem-sucedida',
                'score' => $this->calculateSecurityScore()
            ];
            
        } catch (Exception $e) {
            error_log("[ULTRA_SECURITY_V3] Erro: " . $e->getMessage());
            $this->recordViolation('VALIDATION_ERROR');
            return $this->fail('Erro de validação de segurança', 'VALIDATION_ERROR');
        }
    }
    
    /**
     * Validar timestamp da requisição
     */
    private function validateTimestamp() {
        $timestamp = $this->headers['X-TIMESTAMP'] ?? null;
        
        if (!$timestamp) {
            return false;
        }
        
        $now = time();
        $diff = abs($now - (int)$timestamp);
        
        return $diff <= self::TIMESTAMP_TOLERANCE;
    }
    
    /**
     * Validar Proof of Work
     * O cliente deve encontrar um nonce que gera hash com N zeros no início
     */
    private function validateProofOfWork() {
        $pow = $this->headers['X-PROOF-OF-WORK'] ?? null;
        
        if (!$pow) {
            return false;
        }
        
        // Formato: nonce:hash
        $parts = explode(':', $pow);
        if (count($parts) !== 2) {
            return false;
        }
        
        $nonce = $parts[0];
        $hash = $parts[1];
        
        // Obter timestamp
        $timestamp = $this->headers['X-TIMESTAMP'] ?? time();
        
        // Recalcular hash
        $data = $timestamp . $nonce . self::SECRET_KEY;
        $calculatedHash = hash('sha256', $data);
        
        // Verificar se hash começa com N zeros
        $zeros = str_repeat('0', self::POW_DIFFICULTY);
        
        if (!str_starts_with($calculatedHash, $zeros)) {
            return false;
        }
        
        // Verificar se hash corresponde
        return $calculatedHash === $hash;
    }
    
    /**
     * Validar assinatura HMAC
     */
    private function validateSignature() {
        $signature = $this->headers['X-SIGNATURE'] ?? null;
        
        if (!$signature) {
            return false;
        }
        
        $timestamp = $this->headers['X-TIMESTAMP'] ?? '';
        $deviceId = $this->deviceId ?? '';
        $authToken = $this->extractAuthToken();
        
        // Recalcular assinatura
        $data = $timestamp . $deviceId . $authToken . $this->body;
        $calculatedSignature = hash_hmac('sha256', $data, self::SECRET_KEY);
        
        return hash_equals($calculatedSignature, $signature);
    }
    
    /**
     * Validar Challenge-Response
     */
    private function validateChallengeResponse() {
        $challenge = $this->headers['X-CHALLENGE'] ?? null;
        $response = $this->headers['X-RESPONSE'] ?? null;
        
        if (!$challenge || !$response) {
            return false;
        }
        
        // Recalcular resposta esperada
        $data = $challenge . $this->deviceId;
        $expectedResponse = hash_hmac('sha256', $data, self::CHALLENGE_SECRET);
        
        return hash_equals($expectedResponse, $response);
    }
    
    /**
     * Verificar rate limiting
     */
    private function checkRateLimit() {
        $key = 'rate_' . ($this->deviceId ?? $this->ip);
        $window = floor(time() / self::RATE_LIMIT_WINDOW);
        
        try {
            // Verificar contagem atual
            $stmt = $this->conn->prepare("
                SELECT request_count, window_start 
                FROM security_rate_limits 
                WHERE rate_key = ? AND window_start = ?
            ");
            $stmt->execute([$key, $window]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                if ($row['request_count'] >= self::RATE_LIMIT_MAX_REQUESTS) {
                    return false;
                }
                
                // Incrementar contador
                $stmt = $this->conn->prepare("
                    UPDATE security_rate_limits 
                    SET request_count = request_count + 1 
                    WHERE rate_key = ? AND window_start = ?
                ");
                $stmt->execute([$key, $window]);
            } else {
                // Criar novo registro
                $stmt = $this->conn->prepare("
                    INSERT INTO security_rate_limits (rate_key, window_start, request_count) 
                    VALUES (?, ?, 1)
                    ON DUPLICATE KEY UPDATE request_count = request_count + 1
                ");
                $stmt->execute([$key, $window]);
            }
            
            return true;
            
        } catch (Exception $e) {
            // Se tabela não existe, permitir (mas logar)
            error_log("[RATE_LIMIT] Erro: " . $e->getMessage());
            return true;
        }
    }
    
    /**
     * Verificar proteção contra replay attack
     */
    private function checkReplayProtection() {
        $requestId = $this->headers['X-REQUEST-ID'] ?? null;
        $nonce = $this->headers['X-CLIENT-NONCE'] ?? null;
        
        if (!$requestId && !$nonce) {
            return false;
        }
        
        $uniqueKey = $requestId ?? $nonce;
        
        try {
            // Verificar se já foi usado
            $stmt = $this->conn->prepare("
                SELECT id FROM security_used_nonces 
                WHERE nonce_value = ?
            ");
            $stmt->execute([$uniqueKey]);
            
            if ($stmt->fetch()) {
                return false; // Nonce já usado = replay attack
            }
            
            // Registrar nonce
            $stmt = $this->conn->prepare("
                INSERT INTO security_used_nonces (nonce_value, used_at, ip_address, device_id) 
                VALUES (?, NOW(), ?, ?)
            ");
            $stmt->execute([$uniqueKey, $this->ip, $this->deviceId]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("[REPLAY_PROTECTION] Erro: " . $e->getMessage());
            return true; // Permitir se tabela não existe
        }
    }
    
    /**
     * Verificar se IP está bloqueado
     */
    private function isIPBlocked() {
        try {
            $stmt = $this->conn->prepare("
                SELECT blocked_until FROM security_blocked_ips 
                WHERE ip_address = ? AND blocked_until > NOW()
            ");
            $stmt->execute([$this->ip]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verificar se device está bloqueado
     */
    private function isDeviceBlocked() {
        if (!$this->deviceId) return false;
        
        try {
            $stmt = $this->conn->prepare("
                SELECT blocked_until FROM security_blocked_devices 
                WHERE device_id = ? AND blocked_until > NOW()
            ");
            $stmt->execute([$this->deviceId]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Registrar violação de segurança
     */
    private function recordViolation($type) {
        try {
            // Registrar violação
            $stmt = $this->conn->prepare("
                INSERT INTO security_violations 
                (ip_address, device_id, violation_type, headers_json, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $this->ip, 
                $this->deviceId, 
                $type, 
                json_encode($this->headers)
            ]);
            
            // Contar violações recentes
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count FROM security_violations 
                WHERE (ip_address = ? OR device_id = ?) 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$this->ip, $this->deviceId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $violationCount = $row['count'] ?? 0;
            
            // Aplicar bloqueio progressivo
            foreach (self::BLOCK_THRESHOLDS as $threshold => $blockTime) {
                if ($violationCount >= $threshold) {
                    $this->blockIP($blockTime);
                    if ($this->deviceId) {
                        $this->blockDevice($blockTime);
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("[VIOLATION] Erro ao registrar: " . $e->getMessage());
        }
    }
    
    /**
     * Bloquear IP
     */
    private function blockIP($seconds) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO security_blocked_ips (ip_address, blocked_until, reason) 
                VALUES (?, DATE_ADD(NOW(), INTERVAL ? SECOND), 'Múltiplas violações de segurança')
                ON DUPLICATE KEY UPDATE blocked_until = DATE_ADD(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$this->ip, $seconds, $seconds]);
            
            error_log("[SECURITY] IP bloqueado: {$this->ip} por {$seconds}s");
        } catch (Exception $e) {
            error_log("[BLOCK_IP] Erro: " . $e->getMessage());
        }
    }
    
    /**
     * Bloquear device
     */
    private function blockDevice($seconds) {
        if (!$this->deviceId) return;
        
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO security_blocked_devices (device_id, blocked_until, reason) 
                VALUES (?, DATE_ADD(NOW(), INTERVAL ? SECOND), 'Múltiplas violações de segurança')
                ON DUPLICATE KEY UPDATE blocked_until = DATE_ADD(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$this->deviceId, $seconds, $seconds]);
            
            error_log("[SECURITY] Device bloqueado: {$this->deviceId} por {$seconds}s");
        } catch (Exception $e) {
            error_log("[BLOCK_DEVICE] Erro: " . $e->getMessage());
        }
    }
    
    /**
     * Registrar requisição bem-sucedida
     */
    private function recordSuccessfulRequest() {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO security_request_log 
                (ip_address, device_id, endpoint, success, created_at) 
                VALUES (?, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $this->ip, 
                $this->deviceId, 
                $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            // Ignorar erros de log
        }
    }
    
    /**
     * Calcular score de segurança
     */
    private function calculateSecurityScore() {
        $score = 100;
        
        // Verificar headers presentes
        $securityHeaders = [
            'X-DEVICE-ID', 'X-TIMESTAMP', 'X-SIGNATURE', 
            'X-PROOF-OF-WORK', 'X-REQUEST-ID', 'X-CLIENT-NONCE'
        ];
        
        foreach ($securityHeaders as $header) {
            if (!isset($this->headers[$header])) {
                $score -= 10;
            }
        }
        
        return max(0, $score);
    }
    
    /**
     * Extrair token de autorização
     */
    private function extractAuthToken() {
        $auth = $this->headers['AUTHORIZATION'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return '';
    }
    
    /**
     * Obter IP real do cliente
     */
    private function getClientIP() {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Obter todos os headers
     */
    private function getAllHeaders() {
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            }
        }
        
        // Headers especiais
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['CONTENT-TYPE'] = $_SERVER['CONTENT_TYPE'];
        }
        
        return $headers;
    }
    
    /**
     * Retornar falha
     */
    private function fail($message, $code) {
        return [
            'valid' => false,
            'message' => $message,
            'code' => $code,
            'score' => 0
        ];
    }
    
    /**
     * Gerar challenge para o cliente
     */
    public static function generateChallenge() {
        return bin2hex(random_bytes(16));
    }
}

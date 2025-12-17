<?php
/**
 * AntiScriptValidator - Validação de headers anti-script
 * 
 * Valida os headers de segurança gerados pelo app Android:
 * - X-Device-Fingerprint: Hash único do dispositivo
 * - X-Request-Signature: Assinatura HMAC da requisição
 * - X-Timestamp: Timestamp com validação de janela (60 segundos)
 * - X-App-Hash: Hash da assinatura do APK
 * - X-Nonce: Valor único por requisição (anti-replay)
 * 
 * @version 1.0.0
 */

class AntiScriptValidator {
    
    private const SECRET_KEY = 'YM_S3CR3T_K3Y_2025_PR0T3CT10N';
    private const SALT = 'youngmoney_anti_script_salt_v1';
    private const TIMESTAMP_WINDOW = 120; // 2 minutos de tolerância
    
    private $conn;
    private $errors = [];
    private $headers = [];
    
    public function __construct($dbConnection = null) {
        $this->conn = $dbConnection;
        $this->loadHeaders();
    }
    
    /**
     * Carrega todos os headers HTTP
     */
    private function loadHeaders() {
        if (function_exists('getallheaders')) {
            $this->headers = getallheaders();
        } else {
            foreach ($_SERVER as $key => $value) {
                if (substr($key, 0, 5) === 'HTTP_') {
                    $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                    $this->headers[$header] = $value;
                }
            }
        }
        
        // Normalizar chaves
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
     * Valida todos os headers de segurança
     * 
     * @param bool $strict Se true, rejeita requisições sem headers
     * @return bool True se válido
     */
    public function validate($strict = true) {
        $this->errors = [];
        
        // 1. Verificar presença dos headers obrigatórios
        $requiredHeaders = [
            'x-device-fingerprint',
            'x-timestamp',
            'x-nonce',
            'x-request-signature'
        ];
        
        foreach ($requiredHeaders as $header) {
            if (!$this->getHeader($header)) {
                if ($strict) {
                    $this->errors[] = "Missing required header: $header";
                    return false;
                }
            }
        }
        
        // Se não tem headers e não é strict, permitir
        if (!$this->getHeader('x-timestamp') && !$strict) {
            return true;
        }
        
        // 2. Validar timestamp (janela de 2 minutos)
        $timestamp = (int) $this->getHeader('x-timestamp');
        $currentTime = round(microtime(true) * 1000); // milissegundos
        $diff = abs($currentTime - $timestamp);
        
        if ($diff > (self::TIMESTAMP_WINDOW * 1000)) {
            $this->errors[] = "Timestamp expired or invalid (diff: {$diff}ms)";
            error_log("[AntiScript] Timestamp invalid - received: $timestamp, current: $currentTime, diff: {$diff}ms");
            return false;
        }
        
        // 3. Validar nonce (anti-replay)
        $nonce = $this->getHeader('x-nonce');
        if ($nonce && $this->conn) {
            if ($this->isNonceUsed($nonce)) {
                $this->errors[] = "Nonce already used (replay attack detected)";
                error_log("[AntiScript] Replay attack detected - nonce: $nonce");
                return false;
            }
            $this->saveNonce($nonce);
        }
        
        // 4. Validar device fingerprint (não vazio)
        $fingerprint = $this->getHeader('x-device-fingerprint');
        if (empty($fingerprint) || strlen($fingerprint) < 32) {
            $this->errors[] = "Invalid device fingerprint";
            return false;
        }
        
        // 5. Validar assinatura (opcional - pode ser complexo validar no servidor)
        // A assinatura é gerada com dados do dispositivo que só o app conhece
        // Por enquanto, apenas verificamos se existe e tem formato válido
        $signature = $this->getHeader('x-request-signature');
        if (empty($signature) || strlen($signature) !== 64) {
            $this->errors[] = "Invalid request signature";
            return false;
        }
        
        return true;
    }
    
    /**
     * Verifica se o nonce já foi usado
     */
    private function isNonceUsed($nonce) {
        if (!$this->conn) return false;
        
        try {
            $stmt = $this->conn->prepare("SELECT id FROM security_nonces WHERE nonce = ?");
            $stmt->bind_param("s", $nonce);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->num_rows > 0;
        } catch (Exception $e) {
            error_log("[AntiScript] Error checking nonce: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salva o nonce usado
     */
    private function saveNonce($nonce) {
        if (!$this->conn) return;
        
        try {
            // Criar tabela se não existir
            $this->conn->query("
                CREATE TABLE IF NOT EXISTS security_nonces (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nonce VARCHAR(64) NOT NULL UNIQUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_nonce (nonce),
                    INDEX idx_created (created_at)
                )
            ");
            
            // Inserir nonce
            $stmt = $this->conn->prepare("INSERT IGNORE INTO security_nonces (nonce) VALUES (?)");
            $stmt->bind_param("s", $nonce);
            $stmt->execute();
            
            // Limpar nonces antigos (mais de 5 minutos)
            $this->conn->query("DELETE FROM security_nonces WHERE created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
            
        } catch (Exception $e) {
            error_log("[AntiScript] Error saving nonce: " . $e->getMessage());
        }
    }
    
    /**
     * Retorna os erros de validação
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Retorna informações de debug
     */
    public function getDebugInfo() {
        return [
            'device_fingerprint' => substr($this->getHeader('x-device-fingerprint') ?? '', 0, 16) . '...',
            'timestamp' => $this->getHeader('x-timestamp'),
            'nonce' => substr($this->getHeader('x-nonce') ?? '', 0, 16) . '...',
            'signature' => substr($this->getHeader('x-request-signature') ?? '', 0, 16) . '...',
            'app_hash' => substr($this->getHeader('x-app-hash') ?? '', 0, 16) . '...'
        ];
    }
}

/**
 * Função helper para validar headers anti-script
 */
function validateAntiScriptHeaders($conn, $strict = false) {
    $validator = new AntiScriptValidator($conn);
    
    if (!$validator->validate($strict)) {
        error_log("[AntiScript] Validation failed: " . implode(', ', $validator->getErrors()));
        
        if ($strict) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Security validation failed',
                'code' => 'SECURITY_HEADERS_INVALID',
                'errors' => $validator->getErrors()
            ]);
            exit;
        }
    }
    
    return true;
}
?>

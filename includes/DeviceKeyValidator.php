<?php
/**
 * DeviceKeyValidator - Valida requisições criptografadas com chave de dispositivo
 */

class DeviceKeyValidator {
    
    // Configurações de rotação
    private const ROTATION_WINDOW_MS = 5000; // 5 segundos
    private const ROTATION_SALT = "YM_ROTATING_2025_SECURE";
    private const WINDOW_TOLERANCE = 2; // Aceita 2 janelas antes/depois
    
    // Configurações de timestamp
    private const MAX_TIMESTAMP_DIFF_MS = 120000; // 2 minutos
    
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Valida uma requisição criptografada
     */
    public function validateRequest($deviceId, $rotatingKey, $timestamp, $nonce, $signature) {
        // 1. Validar timestamp
        $currentTime = round(microtime(true) * 1000);
        $timeDiff = abs($currentTime - $timestamp);
        
        if ($timeDiff > self::MAX_TIMESTAMP_DIFF_MS) {
            return ['valid' => false, 'error' => 'TIMESTAMP_EXPIRED', 'message' => 'Request timestamp expired'];
        }
        
        // 2. Obter chave do dispositivo
        $deviceKey = $this->getDeviceKey($deviceId);
        if (!$deviceKey) {
            return ['valid' => false, 'error' => 'DEVICE_NOT_REGISTERED', 'message' => 'Device not registered'];
        }
        
        // 3. Verificar se dispositivo está bloqueado
        if (!empty($deviceKey['is_blocked']) && $deviceKey['is_blocked']) {
            return ['valid' => false, 'error' => 'DEVICE_BLOCKED', 'message' => 'Device is blocked: ' . $deviceKey['blocked_reason']];
        }
        
        // 4. Validar chave rotativa
        $validKey = $this->validateRotatingKey($deviceKey['device_key'], $rotatingKey, $timestamp);
        if (!$validKey) {
            return ['valid' => false, 'error' => 'INVALID_ROTATING_KEY', 'message' => 'Invalid rotating key'];
        }
        
        // 5. Verificar nonce (anti-replay) - desativado temporariamente
        // if (!$this->validateNonce($deviceId, $nonce, $timestamp)) {
        //     return ['valid' => false, 'error' => 'NONCE_REUSED', 'message' => 'Nonce already used'];
        // }
        
        // 6. Atualizar último acesso
        $this->updateLastSeen($deviceId);
        
        return [
            'valid' => true,
            'device_key' => $deviceKey['device_key'],
            'device_id' => $deviceId
        ];
    }
    
    /**
     * Obtém a chave do dispositivo do banco de dados
     */
    private function getDeviceKey($deviceId) {
        try {
            $deviceIdEsc = $this->conn->real_escape_string($deviceId);
            $result = $this->conn->query("
                SELECT device_key, is_blocked, blocked_reason 
                FROM device_keys 
                WHERE device_id = '$deviceIdEsc'
            ");
            
            if ($result && $result->num_rows > 0) {
                return $result->fetch_assoc();
            }
            return null;
        } catch (Exception $e) {
            error_log("Error getting device key: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Valida a chave rotativa
     */
    private function validateRotatingKey($deviceKey, $providedKey, $timestamp) {
        // Tentar janela atual e adjacentes
        for ($offset = -self::WINDOW_TOLERANCE; $offset <= self::WINDOW_TOLERANCE; $offset++) {
            $window = floor($timestamp / self::ROTATION_WINDOW_MS) + $offset;
            $expectedKey = $this->generateRotatingKey($deviceKey, $window);
            
            if ($expectedKey === $providedKey) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Gera a chave rotativa esperada
     */
    private function generateRotatingKey($deviceKey, $window) {
        $data = $deviceKey . $window . self::ROTATION_SALT;
        return hash('sha256', $data);
    }
    
    /**
     * Atualiza último acesso do dispositivo
     */
    private function updateLastSeen($deviceId) {
        try {
            $deviceIdEsc = $this->conn->real_escape_string($deviceId);
            $this->conn->query("
                UPDATE device_keys 
                SET last_seen = NOW(), request_count = request_count + 1 
                WHERE device_id = '$deviceIdEsc'
            ");
        } catch (Exception $e) {
            error_log("Error updating last seen: " . $e->getMessage());
        }
    }
    
    /**
     * Descriptografa dados usando a chave do dispositivo
     */
    public function decryptData($deviceKey, $encryptedData, $timestamp) {
        try {
            // Gerar chave rotativa
            $window = floor($timestamp / self::ROTATION_WINDOW_MS);
            $rotatingKey = $this->generateRotatingKey($deviceKey, $window);
            
            // Usar primeiros 32 bytes como chave AES
            $aesKey = substr($rotatingKey, 0, 32);
            
            // Decodificar Base64
            $combined = base64_decode($encryptedData);
            if ($combined === false) {
                return null;
            }
            
            // Separar IV e dados
            $iv = substr($combined, 0, 16);
            $encrypted = substr($combined, 16);
            
            // Descriptografar
            $decrypted = openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                $aesKey,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            return $decrypted;
            
        } catch (Exception $e) {
            error_log("Error decrypting data: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Criptografa dados usando a chave do dispositivo
     */
    public function encryptData($deviceKey, $plaintext, $timestamp) {
        try {
            // Gerar chave rotativa
            $window = floor($timestamp / self::ROTATION_WINDOW_MS);
            $rotatingKey = $this->generateRotatingKey($deviceKey, $window);
            
            // Usar primeiros 32 bytes como chave AES
            $aesKey = substr($rotatingKey, 0, 32);
            
            // Gerar IV aleatório
            $iv = openssl_random_pseudo_bytes(16);
            
            // Criptografar
            $encrypted = openssl_encrypt(
                $plaintext,
                'AES-256-CBC',
                $aesKey,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            // Combinar IV + dados criptografados
            $combined = $iv . $encrypted;
            
            return base64_encode($combined);
            
        } catch (Exception $e) {
            error_log("Error encrypting data: " . $e->getMessage());
            return null;
        }
    }
}

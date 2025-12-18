<?php
/**
 * NativeCrypto - Sistema de criptografia E2E compatível com código nativo C++
 * 
 * Usa as mesmas chaves e algoritmos do native-lib.cpp para:
 * - Descriptografar requisições do app
 * - Criptografar respostas para o app
 * 
 * A chave é rotativa (muda a cada 5 segundos) baseada no timestamp
 */

class NativeCrypto {
    
    // ============================================
    // CHAVES SECRETAS (MESMAS DO C++)
    // ============================================
    
    // Chave base fragmentada (decodificada do C++)
    // YM_ROTATING_KEY_2025_V3RY_S3CUR3
    private const BASE_KEY = "YM_ROTATING_KEY_2025_V3RY_S3CUR3";
    
    // Salt rotativo
    private const ROTATION_SALT = "youngmoney";
    
    // Janela de rotação em milissegundos (5 segundos)
    private const ROTATION_WINDOW_MS = 5000;
    
    // Tolerância de janelas (aceita janela atual + 2 anteriores + 2 próximas)
    private const WINDOW_TOLERANCE = 2;
    
    /**
     * Gera a chave rotativa baseada no timestamp
     * 
     * @param int $timestamp Timestamp em milissegundos
     * @return string Chave SHA-256 de 64 caracteres
     */
    public static function generateRotatingKey(int $timestamp): string {
        // Calcular janela de 5 segundos
        $window = intval($timestamp / self::ROTATION_WINDOW_MS);
        
        // Combinar chave base + salt + janela
        $combined = self::BASE_KEY . self::ROTATION_SALT . $window;
        
        return hash('sha256', $combined);
    }
    
    /**
     * Obtém a janela atual de rotação
     */
    public static function getCurrentWindow(): int {
        return intval(round(microtime(true) * 1000) / self::ROTATION_WINDOW_MS);
    }
    
    /**
     * Valida se a chave rotativa é válida (dentro da tolerância)
     * 
     * @param string $rotatingKey Chave enviada pelo app
     * @param int $timestamp Timestamp enviado pelo app
     * @return bool True se válida
     */
    public static function validateRotatingKey(string $rotatingKey, int $timestamp): bool {
        $window = intval($timestamp / self::ROTATION_WINDOW_MS);
        
        // Verificar janela atual e tolerância
        for ($i = -self::WINDOW_TOLERANCE; $i <= self::WINDOW_TOLERANCE; $i++) {
            $testWindow = $window + $i;
            $testTimestamp = $testWindow * self::ROTATION_WINDOW_MS;
            $expectedKey = self::generateRotatingKey($testTimestamp);
            
            if (hash_equals($expectedKey, $rotatingKey)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Deriva a chave AES-256 a partir da chave rotativa
     */
    private static function deriveAESKey(int $timestamp): string {
        $rotatingKey = self::generateRotatingKey($timestamp);
        // Usar os primeiros 32 bytes (256 bits) do hash como chave AES
        return substr(hash('sha256', $rotatingKey . self::BASE_KEY, true), 0, 32);
    }
    
    /**
     * Deriva o IV a partir do timestamp
     */
    private static function deriveIV(int $timestamp): string {
        // Usar os primeiros 16 bytes do hash do timestamp como IV
        return substr(hash('sha256', strval($timestamp) . self::ROTATION_SALT, true), 0, 16);
    }
    
    /**
     * Criptografa dados usando AES-256-CBC com chave rotativa
     * 
     * @param string $data Dados para criptografar
     * @param int $timestamp Timestamp para derivar a chave
     * @return string Dados criptografados em Base64
     */
    public static function encrypt(string $data, int $timestamp): string {
        $key = self::deriveAESKey($timestamp);
        $iv = self::deriveIV($timestamp);
        
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        if ($encrypted === false) {
            error_log("[NativeCrypto] Erro ao criptografar: " . openssl_error_string());
            return '';
        }
        
        return base64_encode($encrypted);
    }
    
    /**
     * Descriptografa dados usando AES-256-CBC com chave rotativa
     * 
     * @param string $encryptedData Dados criptografados em Base64
     * @param int $timestamp Timestamp para derivar a chave
     * @return string|null Dados descriptografados ou null se falhar
     */
    public static function decrypt(string $encryptedData, int $timestamp): ?string {
        $key = self::deriveAESKey($timestamp);
        $iv = self::deriveIV($timestamp);
        
        $decoded = base64_decode($encryptedData);
        if ($decoded === false) {
            error_log("[NativeCrypto] Erro ao decodificar Base64");
            return null;
        }
        
        $decrypted = openssl_decrypt($decoded, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        if ($decrypted === false) {
            // Tentar com janelas adjacentes (tolerância)
            for ($i = -self::WINDOW_TOLERANCE; $i <= self::WINDOW_TOLERANCE; $i++) {
                if ($i == 0) continue;
                
                $testTimestamp = $timestamp + ($i * self::ROTATION_WINDOW_MS);
                $testKey = self::deriveAESKey($testTimestamp);
                $testIV = self::deriveIV($testTimestamp);
                
                $decrypted = openssl_decrypt($decoded, 'AES-256-CBC', $testKey, OPENSSL_RAW_DATA, $testIV);
                if ($decrypted !== false) {
                    error_log("[NativeCrypto] Descriptografado com janela adjacente: " . $i);
                    return $decrypted;
                }
            }
            
            error_log("[NativeCrypto] Erro ao descriptografar: " . openssl_error_string());
            return null;
        }
        
        return $decrypted;
    }
    
    /**
     * Assina uma requisição (compatível com C++)
     * 
     * @param string $endpoint Endpoint da requisição
     * @param string $body Corpo da requisição
     * @param int $timestamp Timestamp
     * @param string $nonce Nonce único
     * @return string Assinatura SHA-256
     */
    public static function signRequest(string $endpoint, string $body, int $timestamp, string $nonce): string {
        $rotatingKey = self::generateRotatingKey($timestamp);
        $data = $endpoint . $body . $timestamp . $nonce . $rotatingKey;
        return hash('sha256', $data);
    }
    
    /**
     * Valida a assinatura de uma requisição
     */
    public static function validateSignature(string $signature, string $endpoint, string $body, int $timestamp, string $nonce): bool {
        $expectedSignature = self::signRequest($endpoint, $body, $timestamp, $nonce);
        return hash_equals($expectedSignature, $signature);
    }
}
?>

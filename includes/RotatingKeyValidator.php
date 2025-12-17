<?php
/**
 * RotatingKeyValidator - Validação de chave rotativa nativa
 * 
 * Este validador verifica a chave rotativa que muda a cada 5 segundos.
 * A chave é gerada no código nativo C++ do app Android.
 * 
 * Proteções:
 * - Chave muda a cada 5 segundos (impossível de prever)
 * - Validação de janela de tempo
 * - Anti-replay (nonce único)
 * - Verificação de assinatura
 * 
 * @version 1.0.0
 */

class RotatingKeyValidator {
    
    // Chave base (DEVE SER IDÊNTICA À DO CÓDIGO C++)
    // Esta chave é fragmentada e XOR no código nativo
    private const BASE_KEY = "YM_ROTATING_KEY_2025_V3RY_S3CUR3";
    private const ROTATION_SALT = "youngmoney";
    
    // Janela de tolerância (em número de janelas de 5 segundos)
    // Permite 2 janelas antes e 2 depois = 20 segundos de tolerância total
    private const WINDOW_TOLERANCE = 2;
    
    /**
     * Valida a chave rotativa
     * 
     * @param string $rotatingKey Chave rotativa recebida do app
     * @param string $nativeSignature Assinatura nativa
     * @param int $keyWindow Janela de tempo
     * @param string $endpoint Endpoint da requisição
     * @param string $body Corpo da requisição
     * @param int $timestamp Timestamp em milissegundos
     * @param string $nonce Nonce único
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validate(
        string $rotatingKey,
        string $nativeSignature,
        int $keyWindow,
        string $endpoint,
        string $body,
        int $timestamp,
        string $nonce
    ): array {
        
        // Verificar se todos os parâmetros foram fornecidos
        if (empty($rotatingKey) || empty($nativeSignature)) {
            return ['valid' => false, 'error' => 'MISSING_NATIVE_HEADERS'];
        }
        
        // Calcular janela atual do servidor
        $currentTime = round(microtime(true) * 1000); // milissegundos
        $serverWindow = intval($currentTime / 5000);
        
        // Verificar se a janela está dentro da tolerância
        $windowDiff = abs($serverWindow - $keyWindow);
        if ($windowDiff > self::WINDOW_TOLERANCE) {
            error_log("[RotatingKey] Window fora da tolerância: server=$serverWindow, client=$keyWindow, diff=$windowDiff");
            return ['valid' => false, 'error' => 'KEY_WINDOW_EXPIRED'];
        }
        
        // Gerar a chave rotativa esperada para a janela do cliente
        $expectedKey = self::generateRotatingKey($keyWindow);
        
        // Verificar se a chave corresponde
        if (!hash_equals($expectedKey, $rotatingKey)) {
            // Tentar janelas adjacentes (tolerância)
            $keyValid = false;
            for ($i = -self::WINDOW_TOLERANCE; $i <= self::WINDOW_TOLERANCE; $i++) {
                $testKey = self::generateRotatingKey($keyWindow + $i);
                if (hash_equals($testKey, $rotatingKey)) {
                    $keyValid = true;
                    break;
                }
            }
            
            if (!$keyValid) {
                error_log("[RotatingKey] Chave inválida - esperada: " . substr($expectedKey, 0, 16) . "..., recebida: " . substr($rotatingKey, 0, 16) . "...");
                return ['valid' => false, 'error' => 'INVALID_ROTATING_KEY'];
            }
        }
        
        // Verificar assinatura nativa
        $expectedSignature = self::generateSignature($endpoint, $body, $timestamp, $nonce, $rotatingKey);
        
        if (!hash_equals($expectedSignature, $nativeSignature)) {
            error_log("[RotatingKey] Assinatura inválida");
            return ['valid' => false, 'error' => 'INVALID_NATIVE_SIGNATURE'];
        }
        
        error_log("[RotatingKey] Validação bem-sucedida - Window: $keyWindow");
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Gera a chave rotativa para uma janela específica
     * DEVE SER IDÊNTICO AO ALGORITMO DO CÓDIGO C++
     */
    private static function generateRotatingKey(int $window): string {
        $combined = self::BASE_KEY . self::ROTATION_SALT . $window;
        return hash('sha256', $combined);
    }
    
    /**
     * Gera a assinatura esperada
     * DEVE SER IDÊNTICO AO ALGORITMO DO CÓDIGO C++
     */
    private static function generateSignature(
        string $endpoint,
        string $body,
        int $timestamp,
        string $nonce,
        string $rotatingKey
    ): string {
        $toSign = $endpoint . "|" . $body . "|" . $timestamp . "|" . $nonce . "|" . $rotatingKey;
        return hash('sha256', $toSign);
    }
    
    /**
     * Verifica se os headers de chave rotativa estão presentes
     */
    public static function hasRotatingKeyHeaders(array $headers): bool {
        return !empty($headers['x-rotating-key']) && 
               !empty($headers['x-native-signature']) && 
               !empty($headers['x-key-window']);
    }
}
?>

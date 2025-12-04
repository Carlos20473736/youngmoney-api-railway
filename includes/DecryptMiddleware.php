<?php
/**
 * DecryptMiddleware - Middleware para descriptografar requisições
 * 
 * Processa requisições criptografadas e retorna dados descriptografados
 */

class DecryptMiddleware {
    
    /**
     * Processa a requisição e retorna os dados descriptografados
     * 
     * @return array|null Dados descriptografados ou null se não criptografado
     */
    public static function processRequest() {
        // Verificar se há header X-Req (requisição criptografada)
        $headers = getallheaders();
        $xReq = $headers['X-Req'] ?? $headers['x-req'] ?? null;
        
        if (!$xReq) {
            // Não é uma requisição criptografada, retornar dados do body
            $rawInput = file_get_contents('php://input');
            if (empty($rawInput)) {
                return null;
            }
            
            $data = json_decode($rawInput, true);
            return $data ?: null;
        }
        
        // Requisição criptografada - descriptografar
        try {
            $rawInput = file_get_contents('php://input');
            
            if (empty($rawInput)) {
                return null;
            }
            
            // Tentar decodificar como JSON primeiro
            $encryptedData = json_decode($rawInput, true);
            
            if (isset($encryptedData['encrypted'])) {
                // Formato: {"encrypted": "base64_data"}
                $encrypted = $encryptedData['encrypted'];
            } else {
                // Formato direto: base64_data
                $encrypted = $rawInput;
            }
            
            // Descriptografar usando a chave do header X-Req
            $decrypted = self::decrypt($encrypted, $xReq);
            
            if ($decrypted === false) {
                return null;
            }
            
            // Decodificar JSON descriptografado
            $data = json_decode($decrypted, true);
            return $data ?: null;
            
        } catch (Exception $e) {
            error_log("Erro ao descriptografar requisição: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Descriptografa dados usando AES-256-CBC
     * 
     * @param string $encrypted Dados criptografados em base64
     * @param string $key Chave de descriptografia
     * @return string|false Dados descriptografados ou false em caso de erro
     */
    private static function decrypt($encrypted, $key) {
        try {
            $method = 'AES-256-CBC';
            $ivLength = openssl_cipher_iv_length($method);
            
            // Decodificar base64
            $data = base64_decode($encrypted);
            
            if ($data === false) {
                return false;
            }
            
            // Extrair IV e dados criptografados
            $iv = substr($data, 0, $ivLength);
            $ciphertext = substr($data, $ivLength);
            
            // Derivar chave de 32 bytes
            $derivedKey = hash('sha256', $key, true);
            
            // Descriptografar
            $decrypted = openssl_decrypt($ciphertext, $method, $derivedKey, OPENSSL_RAW_DATA, $iv);
            
            return $decrypted;
        } catch (Exception $e) {
            error_log("Erro na descriptografia: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Criptografa dados para resposta
     * 
     * @param mixed $data Dados para criptografar
     * @param string $key Chave de criptografia
     * @return string Dados criptografados em base64
     */
    public static function encrypt($data, $key) {
        try {
            $method = 'AES-256-CBC';
            $ivLength = openssl_cipher_iv_length($method);
            $iv = openssl_random_pseudo_bytes($ivLength);
            
            // Derivar chave de 32 bytes
            $derivedKey = hash('sha256', $key, true);
            
            // Converter dados para JSON se necessário
            if (!is_string($data)) {
                $data = json_encode($data);
            }
            
            // Criptografar
            $encrypted = openssl_encrypt($data, $method, $derivedKey, OPENSSL_RAW_DATA, $iv);
            
            // Retornar IV + dados criptografados em base64
            return base64_encode($iv . $encrypted);
        } catch (Exception $e) {
            error_log("Erro na criptografia: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envia resposta de sucesso (criptografada se necessário)
     * 
     * @param mixed $data Dados para enviar
     * @param string|null $encryptionKey Chave para criptografar (opcional)
     */
    public static function sendSuccess($data, $encryptionKey = null) {
        header('Content-Type: application/json');
        
        if ($encryptionKey) {
            $encrypted = self::encrypt($data, $encryptionKey);
            echo json_encode([
                'status' => 'success',
                'encrypted' => $encrypted
            ]);
        } else {
            echo json_encode([
                'status' => 'success',
                'data' => $data
            ]);
        }
        
        exit;
    }
    
    /**
     * Envia resposta de erro
     * 
     * @param string $message Mensagem de erro
     * @param int $code Código HTTP (padrão: 400)
     */
    public static function sendError($message, $code = 400) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        echo json_encode([
            'status' => 'error',
            'message' => $message
        ]);
        
        exit;
    }
}

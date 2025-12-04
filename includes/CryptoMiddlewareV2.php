<?php
/**
 * CryptoMiddlewareV2 - Sistema de Criptografia Ultra Seguro
 * 
 * Camadas de Segurança:
 * - Criptografia AES-256-CBC com chaves rotativas (30s)
 * - HKDF para derivação de chaves
 * - HMAC-SHA256 para integridade
 * - Timestamp Window Validation (±90s)
 * - Request Nonce (previne replay)
 * - Rate Limiting por usuário
 * 
 * NUNCA transmite master_seed pela rede!
 */

require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/HeadersValidatorV2.php';

class CryptoMiddlewareV2 {
    
    private static $conn = null;
    private static $user = null;
    
    // Janela temporal (30 segundos)
    const WINDOW_SIZE_MS = 30000;
    
    // Tolerância de janelas (±3 = 180 segundos total)
    const WINDOW_TOLERANCE = 3;
    
    // Chave do servidor (para criptografar master_seed no banco)
    const SERVER_KEY = 'YM_2024_ULTRA_SECURE_KEY_V2_PRODUCTION';
    
    /**
     * Processa requisição criptografada
     * 
     * @param mysqli $conn Conexão com banco
     * @param string $token Token do usuário (Bearer)
     * @return array|null Dados descriptografados ou null se falhar
     */
    public static function processRequest($conn, $token) {
        self::$conn = $conn;
        
        // 1. BUSCAR USUÁRIO PELO TOKEN
        $user = self::getUserByToken($token);
        if (!$user) {
            self::sendError('Token inválido ou expirado', 401);
            return null;
        }
        
        self::$user = $user;
        
        // 2. VERIFICAR RATE LIMITING
        if (!RateLimiter::checkLimit($conn, $user['id'])) {
            self::sendError('Limite de requisições excedido. Tente novamente em alguns minutos.', 429);
            return null;
        }
        
        // 3. LER RAW INPUT
        $rawInput = file_get_contents('php://input');
        
        if (empty($rawInput)) {
            self::sendError('Corpo da requisição vazio', 400);
            return null;
        }
        
        $requestData = json_decode($rawInput, true);
        
        if (!$requestData) {
            self::sendError('JSON inválido', 400);
            return null;
        }
        
        // 3. VALIDAR 30 HEADERS DE SEGURANÇA
        $headers = getallheaders();
        $validator = new HeadersValidatorV2(self::$conn, $user, $headers, $rawInput);
        
        try {
            $validationResult = $validator->validateAll();
            
            // Log de alertas (se houver)
            if (!empty($validationResult['alerts'])) {
                error_log("Alertas de segurança para usuário {$user['id']}: " . implode(', ', $validationResult['alerts']));
            }
            
            // Log do security score
            error_log("Security Score para usuário {$user['id']}: {$validationResult['security_score']}/100");
            
        } catch (Exception $e) {
            self::sendError('Validação de segurança falhou: ' . $e->getMessage(), 403);
            return null;
        }
        
        // 4. VALIDAR ESTRUTURA
        if (!isset($requestData['encrypted']) || !isset($requestData['window']) || !isset($requestData['hmac'])) {
            self::sendError('Estrutura de requisição inválida', 400);
            return null;
        }
        
        $encrypted = $requestData['encrypted'];
        $window = (int)$requestData['window'];
        $hmac = $requestData['hmac'];
        $nonce = $requestData['nonce'] ?? null;
        
        // 5. VALIDAR JANELA TEMPORAL
        if (!self::isWindowValid($window)) {
            error_log("CryptoV2: Janela temporal inválida - window: $window, current: " . self::getCurrentWindow());
            self::sendError('Janela temporal inválida ou expirada', 401);
            return null;
        }
        
        // 6. VALIDAR NONCE (previne replay attacks)
        if ($nonce && !self::validateNonce($user['id'], $nonce)) {
            self::sendError('Requisição duplicada detectada', 401);
            return null;
        }
        
        // 7. DERIVAR CHAVE PARA A JANELA
        $derivedKey = self::deriveKeyForWindow($user, $window);
        
        if (!$derivedKey) {
            self::sendError('Falha ao derivar chave de criptografia', 500);
            return null;
        }
        
        // 8. VALIDAR HMAC
        $expectedHmac = hash_hmac('sha256', $encrypted, $derivedKey);
        
        if (!hash_equals($expectedHmac, $hmac)) {
            error_log("CryptoV2: HMAC inválido - expected: $expectedHmac, received: $hmac");
            self::sendError('Assinatura HMAC inválida - dados adulterados', 401);
            return null;
        }
        
        // 9. DESCRIPTOGRAFAR
        $decrypted = self::decrypt($encrypted, $derivedKey);
        
        if ($decrypted === null) {
            self::sendError('Falha ao descriptografar dados', 401);
            return null;
        }
        
        // 10. DECODIFICAR JSON
        $data = json_decode($decrypted, true);
        
        if (!$data) {
            self::sendError('Dados descriptografados inválidos', 400);
            return null;
        }
        
        error_log("CryptoV2: Requisição processada com sucesso para user_id: " . $user['id']);
        
        return [
            'user' => $user,
            'data' => $data
        ];
    }
    
    /**
     * Envia resposta criptografada
     * 
     * @param array $data Dados para enviar
     * @param int $httpCode Código HTTP
     */
    public static function sendResponse($data, $httpCode = 200) {
        if (!self::$user) {
            self::sendError('Usuário não autenticado', 401);
            return;
        }
        
        // 1. CODIFICAR JSON
        $json = json_encode($data);
        
        // 2. OBTER JANELA ATUAL
        $window = self::getCurrentWindow();
        
        // 3. DERIVAR CHAVE
        $derivedKey = self::deriveKeyForWindow(self::$user, $window);
        
        if (!$derivedKey) {
            self::sendError('Falha ao derivar chave de criptografia', 500);
            return;
        }
        
        // 4. CRIPTOGRAFAR
        $encrypted = self::encrypt($json, $derivedKey);
        
        if (!$encrypted) {
            self::sendError('Falha ao criptografar resposta', 500);
            return;
        }
        
        // 5. GERAR HMAC
        $hmac = hash_hmac('sha256', $encrypted, $derivedKey);
        
        // 6. ENVIAR RESPOSTA
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'encrypted' => $encrypted,
            'window' => $window,
            'hmac' => $hmac
        ]);
        
        exit;
    }
    
    /**
     * Envia erro (JSON puro, sem criptografia)
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
    
    /**
     * Busca usuário pelo token
     */
    private static function getUserByToken($token) {
        $stmt = self::$conn->prepare("
            SELECT id, google_id, email, name, profile_picture, points, 
                   master_seed, session_salt, salt_updated_at
            FROM users 
            WHERE token = ?
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        
        return $user;
    }
    
    /**
     * Deriva chave usando HKDF
     * 
     * Implementação de HKDF-SHA256:
     * - Extract: PRK = HMAC-SHA256(salt, IKM)
     * - Expand: OKM = HMAC-SHA256(PRK, info || 0x01)
     */
    private static function deriveKeyForWindow($user, $window) {
        try {
            // Descriptografar master_seed do banco
            $masterSeed = self::decryptMasterSeed($user['master_seed'], $user['id']);
            
            if (!$masterSeed) {
                error_log("CryptoV2: Falha ao descriptografar master_seed");
                return null;
            }
            
            $sessionSalt = $user['session_salt'];
            
            // Info = "youngmoney_v2_" + timestamp_window
            $info = "youngmoney_v2_" . $window;
            
            // HKDF Extract: PRK = HMAC-SHA256(salt, seed)
            $prk = hash_hmac('sha256', $masterSeed, $sessionSalt, true);
            
            // HKDF Expand: OKM = HMAC-SHA256(PRK, info || 0x01)
            $okm = hash_hmac('sha256', $info . chr(1), $prk, true);
            
            // Retornar 32 bytes (AES-256) em formato binário
            return $okm;
            
        } catch (Exception $e) {
            error_log("CryptoV2: Erro no HKDF - " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Descriptografa master_seed do banco
     */
    private static function decryptMasterSeed($encryptedSeed, $userId) {
        try {
            $iv = substr(md5($userId), 0, 16);
            $decrypted = openssl_decrypt($encryptedSeed, 'AES-256-CBC', self::SERVER_KEY, 0, $iv);
            return $decrypted;
        } catch (Exception $e) {
            error_log("CryptoV2: Erro ao descriptografar master_seed - " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Criptografa dados usando AES-256-CBC
     */
    private static function encrypt($data, $key) {
        try {
            // Gerar IV aleatório (16 bytes)
            $iv = openssl_random_pseudo_bytes(16);
            
            // Criptografar
            $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            
            if ($encrypted === false) {
                return null;
            }
            
            // Concatenar IV + dados criptografados
            $combined = $iv . $encrypted;
            
            // Retornar em base64
            return base64_encode($combined);
            
        } catch (Exception $e) {
            error_log("CryptoV2: Erro ao criptografar - " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Descriptografa dados usando AES-256-CBC
     */
    private static function decrypt($encryptedData, $key) {
        try {
            // Decodificar base64
            $combined = base64_decode($encryptedData);
            
            if ($combined === false || strlen($combined) < 16) {
                return null;
            }
            
            // Extrair IV (primeiros 16 bytes)
            $iv = substr($combined, 0, 16);
            $encrypted = substr($combined, 16);
            
            // Descriptografar
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            
            if ($decrypted === false) {
                return null;
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            error_log("CryptoV2: Erro ao descriptografar - " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Calcula janela temporal atual
     */
    private static function getCurrentWindow() {
        return floor(microtime(true) * 1000 / self::WINDOW_SIZE_MS);
    }
    
    /**
     * Valida se janela temporal está dentro do intervalo válido
     */
    private static function isWindowValid($window) {
        $currentWindow = self::getCurrentWindow();
        $diff = abs($currentWindow - $window);
        return $diff <= self::WINDOW_TOLERANCE;
    }
    
    /**
     * Valida nonce (previne replay attacks)
     */
    private static function validateNonce($userId, $nonce) {
        // Verificar se nonce já foi usado (últimos 5 minutos)
        $stmt = self::$conn->prepare("
            SELECT COUNT(*) as count 
            FROM request_nonces 
            WHERE user_id = ? AND nonce = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        $stmt->bind_param("is", $userId, $nonce);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row['count'] > 0) {
            return false; // Nonce já usado
        }
        
        // Armazenar nonce
        $stmt = self::$conn->prepare("INSERT INTO request_nonces (user_id, nonce, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("is", $userId, $nonce);
        $stmt->execute();
        $stmt->close();
        
        // Limpar nonces antigos (> 5 minutos)
        self::$conn->query("DELETE FROM request_nonces WHERE created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        
        return true;
    }
}
?>

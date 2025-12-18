<?php
/**
 * Headers Validator - Validação Simplificada
 * 
 * Valida apenas os headers essenciais:
 * - Authorization: Bearer {token} (para endpoints autenticados)
 * - X-Request-ID: {uuid} (opcional)
 * 
 * @version 2.0.0 - Simplificado
 * @date 2025-12-16
 */

class HeadersValidator {
    
    private $conn;
    private $errors = [];
    private $headers = [];
    private $user = null;
    
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
            $this->headers = [];
        }
        
        // Método 2: $_SERVER (Nginx/outros e chamadas via secure.php)
        // IMPORTANTE: Sempre verificar $_SERVER para pegar headers passados via secure.php
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                // Sobrescrever com valor do $_SERVER (prioridade para chamadas via secure.php)
                $this->headers[$header] = $value;
            }
        }
        
        // Normalizar chaves (case-insensitive)
        $this->headers = array_change_key_case($this->headers, CASE_LOWER);
        
        // Debug: logar headers carregados
        error_log("[HeadersValidator] Headers carregados: " . json_encode($this->headers));
    }
    
    /**
     * Obtém um header específico
     */
    public function getHeader($name) {
        $name = strtolower($name);
        return $this->headers[$name] ?? null;
    }
    
    /**
     * Valida os headers simplificados
     * 
     * @param bool $requireAuth Se true, exige Authorization header
     * @return bool True se válido, False se inválido
     */
    public function validate($requireAuth = true) {
        $this->errors = [];
        
        // 1. Validar Authorization se obrigatório
        if ($requireAuth) {
            $auth = $this->getHeader('authorization');
            if (!$auth) {
                $this->errors[] = "Missing Authorization header";
                return false;
            }
            
            // Verificar formato "Bearer {token}"
            if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
                $this->errors[] = "Authorization must be in format: Bearer {token}";
                return false;
            }
            
            $token = $matches[1];
            
            // Validar token no banco de dados
            if ($this->conn && !$this->isValidToken($token)) {
                $this->errors[] = "Invalid or expired authorization token";
                return false;
            }
        }
        
        // X-Request-ID é opcional, apenas logar se presente
        $requestId = $this->getHeader('x-request-id');
        if ($requestId) {
            // Logar para debug (opcional)
            // error_log("[HeadersValidator] X-Request-ID: " . $requestId);
        }
        
        return empty($this->errors);
    }
    
    /**
     * Verifica se o token é válido e retorna o usuário
     */
    private function isValidToken($token) {
        if (!$this->conn) return true;
        
        try {
            $stmt = $this->conn->prepare("SELECT id, username, email, points FROM users WHERE token = ? AND token_expires_at > NOW()");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $this->user = $result->fetch_assoc();
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("[HeadersValidator] Erro ao validar token: " . $e->getMessage());
            return true; // Em caso de erro, permitir (para não bloquear)
        }
    }
    
    /**
     * Retorna o usuário autenticado
     */
    public function getUser() {
        return $this->user;
    }
    
    /**
     * Retorna os erros de validação
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Extrai o token Bearer do header Authorization
     */
    public function getBearerToken() {
        $auth = $this->getHeader('authorization');
        
        if ($auth && preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}

/**
 * Função helper para validar headers em endpoints
 * 
 * @param mysqli $conn Conexão com banco de dados
 * @param bool $requireAuth Se true, exige autenticação
 * @return array|false Retorna dados do usuário se válido, false se inválido
 */
function validateRequestHeaders($conn, $requireAuth = true) {
    $validator = new HeadersValidator($conn);
    
    if (!$validator->validate($requireAuth)) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid request headers',
            'errors' => $validator->getErrors()
        ]);
        return false;
    }
    
    return $validator->getUser() ?: true;
}

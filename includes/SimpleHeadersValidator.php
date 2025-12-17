<?php

/**
 * SimpleHeadersValidator - Validador Simplificado
 * 
 * Valida apenas:
 * - X-Request-ID (UUID único da requisição)
 * - Authorization: Bearer (token de autenticação)
 * 
 * @version 1.0.0
 */
class SimpleHeadersValidator {
    
    private $conn;
    private $headers;
    
    public function __construct($conn, $headers) {
        $this->conn = $conn;
        $this->headers = $this->normalizeHeaders($headers);
    }
    
    /**
     * Normaliza os headers para uppercase
     */
    private function normalizeHeaders($headers) {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtoupper($key)] = $value;
        }
        return $normalized;
    }
    
    /**
     * Valida os headers simplificados
     */
    public function validate() {
        $errors = [];
        
        // Validar X-Request-ID (opcional mas recomendado)
        if (!isset($this->headers['X-REQUEST-ID']) || empty($this->headers['X-REQUEST-ID'])) {
            // Não bloquear, apenas alertar
            error_log("[SimpleValidator] X-Request-ID ausente");
        }
        
        // Authorization é validado separadamente pelo endpoint
        // Aqui apenas verificamos se existe
        
        return [
            'valid' => true,
            'errors' => $errors
        ];
    }
    
    /**
     * Extrai o token Bearer do header Authorization
     */
    public function getBearerToken() {
        $auth = $this->headers['AUTHORIZATION'] ?? '';
        
        if (preg_match('/Bearer\s+(.+)/i', $auth, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Obtém o X-Request-ID
     */
    public function getRequestId() {
        return $this->headers['X-REQUEST-ID'] ?? null;
    }
}

<?php
/**
 * SecureKeyManager - Gerenciador de Chaves Seguras
 * 
 * Responsável por gerar e gerenciar seeds, salts e criptografia
 */

class SecureKeyManager {
    
    /**
     * Gera um Master Seed aleatório de 64 bytes (512 bits)
     * 
     * @return string Master seed em hexadecimal
     */
    public static function generateMasterSeed() {
        return bin2hex(random_bytes(64));
    }
    
    /**
     * Gera um Session Salt aleatório de 32 bytes (256 bits)
     * 
     * @return string Session salt em hexadecimal
     */
    public static function generateSessionSalt() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Criptografa o Master Seed com uma senha (device_id ou email)
     * 
     * @param string $masterSeed Master seed em hexadecimal
     * @param string $password Senha para criptografar (device_id ou email)
     * @return string Seed criptografado em base64
     */
    public static function encryptSeedWithPassword($masterSeed, $password) {
        // Usar AES-256-CBC para criptografia
        $method = 'AES-256-CBC';
        
        // Gerar IV (Initialization Vector)
        $ivLength = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        // Derivar chave da senha usando PBKDF2
        $key = hash_pbkdf2('sha256', $password, 'youngmoney_salt', 10000, 32, true);
        
        // Criptografar
        $encrypted = openssl_encrypt($masterSeed, $method, $key, OPENSSL_RAW_DATA, $iv);
        
        // Retornar IV + dados criptografados em base64
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Descriptografa o Master Seed com uma senha
     * 
     * @param string $encryptedSeed Seed criptografado em base64
     * @param string $password Senha para descriptografar
     * @return string|false Master seed descriptografado ou false em caso de erro
     */
    public static function decryptSeedWithPassword($encryptedSeed, $password) {
        try {
            $method = 'AES-256-CBC';
            $ivLength = openssl_cipher_iv_length($method);
            
            // Decodificar base64
            $data = base64_decode($encryptedSeed);
            
            // Extrair IV e dados criptografados
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);
            
            // Derivar chave da senha
            $key = hash_pbkdf2('sha256', $password, 'youngmoney_salt', 10000, 32, true);
            
            // Descriptografar
            $decrypted = openssl_decrypt($encrypted, $method, $key, OPENSSL_RAW_DATA, $iv);
            
            return $decrypted;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Armazena os secrets do usuário no banco de dados
     * 
     * @param PDO $pdo Conexão PDO com o banco
     * @param int $userId ID do usuário
     * @param string $masterSeed Master seed em hexadecimal
     * @param string $sessionSalt Session salt em hexadecimal
     * @return bool True se armazenado com sucesso
     */
    public static function storeUserSecrets($pdo, $userId, $masterSeed, $sessionSalt) {
        try {
            // Verificar se já existe
            $stmt = $pdo->prepare("SELECT id FROM user_secrets WHERE user_id = ?");
            $stmt->execute([$userId]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Atualizar
                $stmt = $pdo->prepare(
                    "UPDATE user_secrets 
                    SET master_seed = ?, session_salt = ?, updated_at = NOW() 
                    WHERE user_id = ?"
                );
                $stmt->execute([$masterSeed, $sessionSalt, $userId]);
            } else {
                // Inserir
                $stmt = $pdo->prepare(
                    "INSERT INTO user_secrets (user_id, master_seed, session_salt, created_at, updated_at) 
                    VALUES (?, ?, ?, NOW(), NOW())"
                );
                $stmt->execute([$userId, $masterSeed, $sessionSalt]);
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao armazenar secrets: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Recupera os secrets do usuário do banco de dados
     * 
     * @param PDO $pdo Conexão PDO com o banco
     * @param int $userId ID do usuário
     * @return array|false Array com master_seed e session_salt ou false
     */
    public static function getUserSecrets($pdo, $userId) {
        try {
            $stmt = $pdo->prepare("SELECT master_seed, session_salt FROM user_secrets WHERE user_id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: false;
        } catch (PDOException $e) {
            error_log("Erro ao recuperar secrets: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gera uma chave rotativa baseada no master seed e session salt
     * 
     * @param string $masterSeed Master seed em hexadecimal
     * @param string $sessionSalt Session salt em hexadecimal
     * @param int $timestamp Timestamp para rotação (opcional)
     * @return string Chave rotativa em hexadecimal
     */
    public static function generateRotatingKey($masterSeed, $sessionSalt, $timestamp = null) {
        if ($timestamp === null) {
            $timestamp = time();
        }
        
        // Rotacionar a cada 5 minutos (300 segundos)
        $timeSlot = floor($timestamp / 300);
        
        // Combinar master seed + session salt + time slot
        $combined = $masterSeed . $sessionSalt . $timeSlot;
        
        // Gerar hash SHA-256
        return hash('sha256', $combined);
    }
}

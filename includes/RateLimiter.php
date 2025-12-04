<?php
/**
 * RateLimiter - Controle de Taxa de Requisições
 * 
 * Previne abuso e ataques de força bruta
 * Limites:
 * - 100 requisições por minuto por usuário
 * - 1000 requisições por hora por usuário
 */

class RateLimiter {
    
    private static $conn = null;
    
    // Limites
    const LIMIT_PER_MINUTE = 200;
    const LIMIT_PER_HOUR = 10000;
    
    /**
     * Verifica se usuário excedeu limite de requisições
     * 
     * @param mysqli $conn Conexão com banco
     * @param int $userId ID do usuário
     * @return bool true se permitido, false se excedeu limite
     */
    public static function checkLimit($conn, $userId) {
        self::$conn = $conn;
        
        // Verificar limite por minuto
        if (!self::checkMinuteLimit($userId)) {
            error_log("RateLimiter: Usuário $userId excedeu limite por minuto");
            return false;
        }
        
        // Verificar limite por hora
        if (!self::checkHourLimit($userId)) {
            error_log("RateLimiter: Usuário $userId excedeu limite por hora");
            return false;
        }
        
        // Registrar requisição
        self::logRequest($userId);
        
        return true;
    }
    
    /**
     * Verifica limite por minuto
     */
    private static function checkMinuteLimit($userId) {
        $stmt = self::$conn->prepare("
            SELECT COUNT(*) as count 
            FROM rate_limit_log 
            WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['count'] < self::LIMIT_PER_MINUTE;
    }
    
    /**
     * Verifica limite por hora
     */
    private static function checkHourLimit($userId) {
        $stmt = self::$conn->prepare("
            SELECT COUNT(*) as count 
            FROM rate_limit_log 
            WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['count'] < self::LIMIT_PER_HOUR;
    }
    
    /**
     * Registra requisição
     */
    private static function logRequest($userId) {
        $stmt = self::$conn->prepare("INSERT INTO rate_limit_log (user_id, created_at) VALUES (?, NOW())");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
        
        // Limpar logs antigos (> 1 hora)
        self::$conn->query("DELETE FROM rate_limit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    }
    
    /**
     * Obtém informações de limite para o usuário
     */
    public static function getLimitInfo($conn, $userId) {
        self::$conn = $conn;
        
        // Contar requisições no último minuto
        $stmt = self::$conn->prepare("
            SELECT COUNT(*) as count 
            FROM rate_limit_log 
            WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $minuteCount = $result->fetch_assoc()['count'];
        $stmt->close();
        
        // Contar requisições na última hora
        $stmt = self::$conn->prepare("
            SELECT COUNT(*) as count 
            FROM rate_limit_log 
            WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $hourCount = $result->fetch_assoc()['count'];
        $stmt->close();
        
        return [
            'minute' => [
                'used' => $minuteCount,
                'limit' => self::LIMIT_PER_MINUTE,
                'remaining' => self::LIMIT_PER_MINUTE - $minuteCount
            ],
            'hour' => [
                'used' => $hourCount,
                'limit' => self::LIMIT_PER_HOUR,
                'remaining' => self::LIMIT_PER_HOUR - $hourCount
            ]
        ];
    }
}
?>

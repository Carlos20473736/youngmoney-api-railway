<?php
/**
 * ResponseHelper - Helper para respostas padronizadas da API
 */

class ResponseHelper {
    
    /**
     * Envia resposta de sucesso no formato padrão
     * 
     * @param mixed $data Dados para enviar
     * @param int $code Código HTTP (padrão: 200)
     */
    public static function success($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
        
        exit;
    }
    
    /**
     * Envia resposta de erro no formato padrão
     * 
     * @param string $message Mensagem de erro
     * @param int $code Código HTTP (padrão: 400)
     */
    public static function error($message, $code = 400) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        echo json_encode([
            'status' => 'error',
            'message' => $message
        ]);
        
        exit;
    }
}
?>

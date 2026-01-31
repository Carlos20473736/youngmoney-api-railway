<?php
/**
 * CORS Headers - Configuração Global
 * MUST be included BEFORE any other output
 */

if (!headers_sent()) {
    // CORREÇÃO CORS: Garantir que o header Access-Control-Allow-Origin seja envif (!headers_sent()) {
    // CORREÇÃO CORS: Garantir que o header Access-Control-Allow-Origin seja enviado corretamente
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    
    // Permitir explicitamente a origem do frontend ou qualquer origem segura
    if ($origin !== '*') {
        header("Access-Control-Allow-Origin: $origin");
        header('Vary: Origin');
    } else {
        header('Access-Control-Allow-Origin: *');
    }
    
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Req, X-Device-ID, X-Timestamp, X-Signature, X-App-Version, X-Platform, X-Platform-Version, X-Device-Model, X-Session-ID, X-Device-Fingerprint, Accept, Origin, X-New-Req');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
    header('Access-Control-Expose-Headers: X-New-Req');
    
    // Handle preflight OPTIONS request immediately
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit(0);
    }
}
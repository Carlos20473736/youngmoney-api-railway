<?php
/**
 * CORS Headers - Configuração Global
 * MUST be included BEFORE any other output
 */

if (!headers_sent()) {
    // Allow all origins (including WebView with origin 'null')
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    
    // Se a origem for 'null' (WebView) ou qualquer HTTP/HTTPS, permitir
    if ($origin === 'null' || $origin === '*' || strpos($origin, 'http') === 0) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        header('Access-Control-Allow-Origin: *');
    }
    
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Req, X-Device-ID, X-Timestamp, X-Signature, X-App-Version, X-Platform, X-Platform-Version, X-Device-Model, X-Session-ID, X-Device-Fingerprint, Accept, Origin');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
    header('Access-Control-Expose-Headers: X-New-Req');
    
    // Handle preflight OPTIONS request immediately
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit(0);
    }
}
?>

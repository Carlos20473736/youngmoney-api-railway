<?php
// CORS headers - MUST be sent before any other output
if (!headers_sent()) {
    // Allow all origins (including WebView with origin 'null')
    $origin = $_SERVER['HTTP_ORIGIN'] ?? 'null';
    if ($origin === 'null' || strpos($origin, 'http') === 0) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        header('Access-Control-Allow-Origin: *');
    }
    
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
    
    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit(0);
    }
}
?>

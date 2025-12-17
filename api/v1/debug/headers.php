<?php
/**
 * Debug - Ver todos os headers recebidos
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$allHeaders = [];

// Método 1: getallheaders()
if (function_exists('getallheaders')) {
    $allHeaders['getallheaders'] = getallheaders();
}

// Método 2: $_SERVER
$serverHeaders = [];
foreach ($_SERVER as $key => $value) {
    if (substr($key, 0, 5) === 'HTTP_') {
        $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
        $serverHeaders[$header] = $value;
    }
}
$allHeaders['server_headers'] = $serverHeaders;

// Authorization específico
$allHeaders['authorization_direct'] = $_SERVER['HTTP_AUTHORIZATION'] ?? 'NOT SET';
$allHeaders['authorization_redirect'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'NOT SET';

echo json_encode([
    'status' => 'success',
    'headers' => $allHeaders,
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'request_uri' => $_SERVER['REQUEST_URI']
], JSON_PRETTY_PRINT);
?>

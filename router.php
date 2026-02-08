<?php
/**
 * Router para PHP Built-in Server
 * Serve o SPA React e as APIs PHP
 */

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Log para debug
error_log("Request: $path");

// Arquivos estáticos da pasta public (assets, etc)
if (preg_match('/^\/assets\//', $path) || preg_match('/^\/__manus__\//', $path)) {
    $file = __DIR__ . '/public' . $path;
    if (file_exists($file)) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
        ];
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }
        readfile($file);
        return true;
    }
}

// APIs PHP - verificar se é uma rota de API
$apiPaths = ['/api/', '/user/', '/ranking/', '/pix/', '/monetag/', '/admin/', '/admin-panel/', 
             '/invite/', '/history/', '/settings/', '/withdraw/', '/notifications/', 
             '/database/', '/middleware/', '/migrations/', '/routes/', '/uploads/'];

foreach ($apiPaths as $apiPath) {
    if (strpos($path, $apiPath) === 0) {
        // É uma rota de API, deixar o PHP processar
        return false;
    }
}

// Arquivos PHP específicos
if (preg_match('/\.php$/', $path)) {
    $file = __DIR__ . $path;
    if (file_exists($file)) {
        return false; // Deixar o PHP processar
    }
}

// Rotas da pasta Postback (site de anúncios)
if (preg_match('/^\/Postback\//', $path) || preg_match('/^\/postback\//', $path)) {
    // Normalizar para Postback (com P maiúsculo)
    $postbackPath = preg_replace('/^\/postback\//i', '/Postback/', $path);
    $file = __DIR__ . $postbackPath;
    
    // Se for diretório, servir index.html
    if (is_dir($file)) {
        $file = rtrim($file, '/') . '/index.html';
    }
    
    if (file_exists($file) && is_file($file)) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $mimeTypes = [
            'html' => 'text/html; charset=UTF-8',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
        ];
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }
        readfile($file);
        return true;
    }
}

// Rota raiz - servir index.html do SPA
if ($path === '/' || $path === '/index.html') {
    $file = __DIR__ . '/public/index.html';
    if (file_exists($file)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($file);
        return true;
    }
}

// Rotas do SPA (auth, tasks, etc) - servir index.html
$spaRoutes = ['/auth', '/tasks', '/404'];
foreach ($spaRoutes as $route) {
    if ($path === $route || strpos($path, $route . '/') === 0 || strpos($path, $route . '?') === 0) {
        $file = __DIR__ . '/public/index.html';
        if (file_exists($file)) {
            header('Content-Type: text/html; charset=UTF-8');
            readfile($file);
            return true;
        }
    }
}

// Verificar se é um arquivo estático na pasta public
$publicFile = __DIR__ . '/public' . $path;
if (file_exists($publicFile) && is_file($publicFile)) {
    return false; // Deixar o servidor servir o arquivo
}

// Fallback - servir index.html para qualquer outra rota (SPA)
$file = __DIR__ . '/public/index.html';
if (file_exists($file)) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($file);
    return true;
}

// Se nada funcionar, deixar o PHP processar
return false;

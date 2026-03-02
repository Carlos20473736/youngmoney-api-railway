<?php
/**
 * CORS Headers - Configuração Global
 * NOTA: O Caddyfile já define Access-Control-Allow-Origin: *
 * Este arquivo apenas trata requisições OPTIONS (preflight)
 * para evitar headers CORS duplicados.
 */

// Handle preflight OPTIONS request immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

<?php
header('Content-Type: application/json');

$env_vars = [
    'MYSQLHOST' => getenv('MYSQLHOST'),
    'MYSQLPORT' => getenv('MYSQLPORT'),
    'MYSQLUSER' => getenv('MYSQLUSER'),
    'MYSQLPASSWORD' => getenv('MYSQLPASSWORD') ? '***' : null,
    'MYSQLDATABASE' => getenv('MYSQLDATABASE'),
];

echo json_encode([
    'env_vars' => $env_vars,
    'all_env' => array_keys($_ENV)
], JSON_PRETTY_PRINT);
?>

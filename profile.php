<?php
/**
 * Profile Endpoint - Redireciona para user/profile.php
 * Endpoint: /profile
 */

// CORS Headers - MUST be first
require_once __DIR__ . '/cors.php';

// Incluir o arquivo real de profile
require_once __DIR__ . '/user/profile.php';

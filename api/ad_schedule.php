<?php
/**
 * API de Controle de Horário para Anúncios
 * 
 * Verifica se o horário atual permite exibição de anúncios.
 * 
 * Regras:
 * - Entre 00:00 e 09:00: Anúncios PERMITIDOS (is_available = true)
 * - Entre 09:00 e 00:00: Anúncios BLOQUEADOS (is_available = false)
 * 
 * Endpoint: GET /api/ad_schedule.php
 * 
 * Resposta:
 * {
 *   "success": true,
 *   "data": {
 *     "is_available": boolean,
 *     "message": string,
 *     "current_time": string,
 *     "next_available": string (se bloqueado)
 *   }
 * }
 */

date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Obter hora atual no fuso horário de São Paulo
$currentHour = (int) date('G'); // Hora em formato 24h (0-23)
$currentTime = date('H:i:s');
$currentDate = date('Y-m-d');

// Verificar se está no horário permitido (00:00 às 09:00)
// Horário bloqueado: 09:00 às 23:59 (9h às meia-noite)
$isAvailable = ($currentHour >= 0 && $currentHour < 9);

if ($isAvailable) {
    // Anúncios disponíveis
    $response = [
        'success' => true,
        'data' => [
            'is_available' => true,
            'message' => 'Anúncios disponíveis',
            'button_text' => 'ASSISTIR ANÚNCIO',
            'current_time' => $currentTime,
            'current_hour' => $currentHour,
            'timezone' => 'America/Sao_Paulo'
        ]
    ];
} else {
    // Anúncios bloqueados - calcular próximo horário disponível
    $nextAvailable = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
    
    $response = [
        'success' => true,
        'data' => [
            'is_available' => false,
            'message' => 'Voltamos à meia-noite',
            'button_text' => 'VOLTAMOS À MEIA-NOITE',
            'current_time' => $currentTime,
            'current_hour' => $currentHour,
            'next_available' => $nextAvailable,
            'timezone' => 'America/Sao_Paulo'
        ]
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

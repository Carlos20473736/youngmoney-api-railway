<?php
/**
 * Script para aplicar cooldowns manualmente aos ganhadores do ranking de hoje
 * 
 * Endpoint: /admin/apply_cooldowns_today.php?token=ym_auto_reset_2024_secure_xyz
 * 
 * Ganhadores de 16/01/2026:
 * - #80 - Xonta t Teste (R$ 10,00) - 1º lugar - 2 dias cooldown
 * - #81 - Maria Jesus (R$ 5,00) - 2º lugar - 2 dias cooldown
 * - #82 - Cristiane Izidorio (R$ 2,50) - 3º lugar - 2 dias cooldown
 * - #83 - lucas (R$ 1,00) - 4º lugar - 1 dia cooldown
 * - #84 - José tiarlem Da Silva julio (R$ 1,00) - 5º lugar - 1 dia cooldown
 * - #85 - Maria eudilenedos reis medeiros Medeiros (R$ 1,00) - 6º lugar - 1 dia cooldown
 * - #86 - Fabricio Silva da Costa (R$ 1,00) - 7º lugar - 1 dia cooldown
 * - #87 - Maria Oliveira (R$ 1,00) - 8º lugar - 1 dia cooldown
 * - #88 - Cerlane Chaveiro (R$ 1,00) - 9º lugar - 1 dia cooldown
 * - #89 - Jesmare Alves Cordeiro (R$ 1,00) - 10º lugar - 1 dia cooldown
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Verificar token de segurança
$token = $_GET['token'] ?? '';
$expectedToken = 'ym_auto_reset_2024_secure_xyz';

if ($token !== $expectedToken) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Token inválido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../database.php';

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

try {
    $conn = getDbConnection();
    
    $current_date = date('Y-m-d');
    $current_datetime = date('Y-m-d H:i:s');
    
    // Lista de ganhadores de hoje com seus emails
    $winners = [
        ['email' => 'testen1.919191@gmail.com', 'position' => 1, 'prize' => 10.00, 'cooldown_days' => 2],
        ['email' => 'ivanirmariadejesus00@gmail.com', 'position' => 2, 'prize' => 5.00, 'cooldown_days' => 2],
        ['email' => 'izidoriocristiane70@gmail.com', 'position' => 3, 'prize' => 2.50, 'cooldown_days' => 2],
        ['email' => 'lucasmoneycash2025@gmail.com', 'position' => 4, 'prize' => 1.00, 'cooldown_days' => 1],
        ['email' => 'jdasilvajulio9@gmail.com', 'position' => 5, 'prize' => 1.00, 'cooldown_days' => 1],
        ['email' => 'mariaeudilenedosreismedelrosm@gmail.com', 'position' => 6, 'prize' => 1.00, 'cooldown_days' => 1],
        ['email' => 'fabriciosilvadacosta41@gmail.com', 'position' => 7, 'prize' => 1.00, 'cooldown_days' => 1],
        ['email' => 'maria12345.mo88@gmail.com', 'position' => 8, 'prize' => 1.00, 'cooldown_days' => 1],
        ['email' => 'cerlanedib@gmail.com', 'position' => 9, 'prize' => 1.00, 'cooldown_days' => 1],
        ['email' => 'ajesmare@gmail.com', 'position' => 10, 'prize' => 1.00, 'cooldown_days' => 1],
    ];
    
    // Criar tabela de cooldowns se não existir
    $conn->query("
        CREATE TABLE IF NOT EXISTS ranking_cooldowns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            position INT NOT NULL COMMENT 'Posição no ranking quando ganhou',
            prize_amount DECIMAL(10,2) NOT NULL COMMENT 'Valor do prêmio recebido',
            cooldown_days INT NOT NULL COMMENT 'Dias de cooldown (1 ou 2)',
            cooldown_until DATETIME NOT NULL COMMENT 'Data/hora até quando está bloqueado',
            reset_date DATE NOT NULL COMMENT 'Data do reset que gerou o cooldown',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_cooldown (user_id, cooldown_until),
            INDEX idx_cooldown_until (cooldown_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    $cooldowns_created = [];
    $errors = [];
    
    foreach ($winners as $winner) {
        // Buscar user_id pelo email
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->bind_param("s", $winner['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user) {
            $errors[] = "Usuário não encontrado: " . $winner['email'];
            continue;
        }
        
        $user_id = $user['id'];
        $cooldownUntil = date('Y-m-d H:i:s', strtotime("+{$winner['cooldown_days']} days"));
        
        // Verificar se já existe cooldown ativo para este usuário
        $stmt = $conn->prepare("SELECT id FROM ranking_cooldowns WHERE user_id = ? AND cooldown_until > ?");
        $stmt->bind_param("is", $user_id, $current_datetime);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($existing) {
            $errors[] = "Cooldown já existe para: " . $winner['email'];
            continue;
        }
        
        // Inserir cooldown
        $stmt = $conn->prepare("
            INSERT INTO ranking_cooldowns 
            (user_id, position, prize_amount, cooldown_days, cooldown_until, reset_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iidiss", 
            $user_id, 
            $winner['position'], 
            $winner['prize'], 
            $winner['cooldown_days'], 
            $cooldownUntil, 
            $current_date
        );
        
        if ($stmt->execute()) {
            $cooldowns_created[] = [
                'user_id' => $user_id,
                'name' => $user['name'],
                'email' => $winner['email'],
                'position' => $winner['position'],
                'prize' => $winner['prize'],
                'cooldown_days' => $winner['cooldown_days'],
                'cooldown_until' => $cooldownUntil
            ];
        } else {
            $errors[] = "Erro ao inserir cooldown para: " . $winner['email'];
        }
        $stmt->close();
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Cooldowns aplicados com sucesso!',
        'cooldowns_created' => count($cooldowns_created),
        'details' => $cooldowns_created,
        'errors' => $errors,
        'reset_date' => $current_date,
        'executed_at' => $current_datetime
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

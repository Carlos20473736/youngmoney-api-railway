<?php
/**
 * API de Controle de Status do Site (Online/Offline)
 * 
 * Endpoints:
 * - GET: Verifica se o site está online e se o usuário tem permissão
 * - POST: Atualiza o status do site (requer admin_key)
 * 
 * Parâmetros GET:
 * - user_id: ID do usuário para verificar se tem permissão
 * 
 * Parâmetros POST:
 * - admin_key: Chave de admin para autorização
 * - action: 'set_online', 'set_offline', 'add_allowed_id', 'remove_allowed_id', 'get_status'
 * - user_id: ID do usuário (para add/remove)
 */

date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuração do banco de dados
$host = 'gondola.proxy.rlwy.net';
$port = 46765;
$dbname = 'railway';
$username = 'root';
$password = 'XvWOlrgTfcJLaDjfywmnSHRNdwEhktSS';

// Chave de admin para autorização
$ADMIN_KEY = 'YM_ADMIN_2025_SECRET';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Criar tabela de controle se não existir
$pdo->exec("
    CREATE TABLE IF NOT EXISTS site_status (
        id INT PRIMARY KEY DEFAULT 1,
        is_online BOOLEAN DEFAULT TRUE,
        allowed_ids TEXT DEFAULT '',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(255) DEFAULT 'system'
    )
");

// Inserir registro padrão se não existir
$stmt = $pdo->query("SELECT COUNT(*) FROM site_status WHERE id = 1");
if ($stmt->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO site_status (id, is_online, allowed_ids) VALUES (1, TRUE, '')");
}

// Função para obter status atual
function getSiteStatus($pdo) {
    $stmt = $pdo->query("SELECT * FROM site_status WHERE id = 1");
    $status = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $allowedIds = [];
    if (!empty($status['allowed_ids'])) {
        $allowedIds = array_map('intval', array_filter(explode(',', $status['allowed_ids'])));
    }
    
    return [
        'is_online' => (bool)$status['is_online'],
        'allowed_ids' => $allowedIds,
        'updated_at' => $status['updated_at'],
        'updated_by' => $status['updated_by']
    ];
}

// Função para verificar se usuário tem permissão
function userHasAccess($pdo, $userId) {
    $status = getSiteStatus($pdo);
    
    // Se o site está online, todos têm acesso
    if ($status['is_online']) {
        return true;
    }
    
    // Se o site está offline, verificar se o usuário está na lista de permitidos
    return in_array((int)$userId, $status['allowed_ids']);
}

// Processar requisição
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Verificar status do site e permissão do usuário
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    
    $status = getSiteStatus($pdo);
    $hasAccess = $userId > 0 ? userHasAccess($pdo, $userId) : $status['is_online'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'is_online' => $status['is_online'],
            'user_has_access' => $hasAccess,
            'allowed_ids_count' => count($status['allowed_ids']),
            'updated_at' => $status['updated_at']
        ]
    ]);
    exit;
}

if ($method === 'POST') {
    // Obter dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $adminKey = $input['admin_key'] ?? '';
    $action = $input['action'] ?? '';
    $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    
    // Verificar chave de admin
    if ($adminKey !== $ADMIN_KEY) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized - Invalid admin key']);
        exit;
    }
    
    switch ($action) {
        case 'set_online':
            $pdo->exec("UPDATE site_status SET is_online = TRUE, updated_by = 'admin' WHERE id = 1");
            echo json_encode(['success' => true, 'message' => 'Site está agora ONLINE']);
            break;
            
        case 'set_offline':
            $pdo->exec("UPDATE site_status SET is_online = FALSE, updated_by = 'admin' WHERE id = 1");
            echo json_encode(['success' => true, 'message' => 'Site está agora OFFLINE']);
            break;
            
        case 'add_allowed_id':
            if ($userId <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de usuário inválido']);
                exit;
            }
            
            $status = getSiteStatus($pdo);
            if (!in_array($userId, $status['allowed_ids'])) {
                $status['allowed_ids'][] = $userId;
                $newIds = implode(',', $status['allowed_ids']);
                $stmt = $pdo->prepare("UPDATE site_status SET allowed_ids = ?, updated_by = 'admin' WHERE id = 1");
                $stmt->execute([$newIds]);
            }
            
            echo json_encode([
                'success' => true, 
                'message' => "ID $userId adicionado à lista de permitidos",
                'allowed_ids' => $status['allowed_ids']
            ]);
            break;
            
        case 'remove_allowed_id':
            if ($userId <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de usuário inválido']);
                exit;
            }
            
            $status = getSiteStatus($pdo);
            $status['allowed_ids'] = array_filter($status['allowed_ids'], function($id) use ($userId) {
                return $id !== $userId;
            });
            $newIds = implode(',', $status['allowed_ids']);
            $stmt = $pdo->prepare("UPDATE site_status SET allowed_ids = ?, updated_by = 'admin' WHERE id = 1");
            $stmt->execute([$newIds]);
            
            echo json_encode([
                'success' => true, 
                'message' => "ID $userId removido da lista de permitidos",
                'allowed_ids' => array_values($status['allowed_ids'])
            ]);
            break;
            
        case 'get_status':
            $status = getSiteStatus($pdo);
            echo json_encode([
                'success' => true,
                'data' => $status
            ]);
            break;
            
        case 'clear_allowed_ids':
            $pdo->exec("UPDATE site_status SET allowed_ids = '', updated_by = 'admin' WHERE id = 1");
            echo json_encode(['success' => true, 'message' => 'Lista de IDs permitidos limpa']);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Ação inválida']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Método não suportado']);

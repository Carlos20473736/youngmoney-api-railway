<?php
/**
 * Endpoint Temporário: Corrigir vinculações de dispositivo
 * 
 * ATENÇÃO: Remover após uso!
 * 
 * Endpoint: GET /api/v1/admin/fix_bindings.php?key=YOUNGMONEY_FIX_2024
 */

header('Content-Type: application/json');

// Verificar chave de segurança
$key = $_GET['key'] ?? '';
if ($key !== 'YOUNGMONEY_FIX_2024') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

// Carregar configuração do banco
require_once __DIR__ . '/../../../database.php';

$result = [
    'success' => false,
    'steps' => [],
    'error' => null
];

try {
    $conn = getDbConnection();
    $result['steps'][] = 'Conectado ao banco de dados';
    
    // 1. Verificar estado atual
    $res = $conn->query("SELECT COUNT(*) as total FROM device_bindings");
    $row = $res->fetch_assoc();
    $result['before'] = ['total' => (int)$row['total']];
    
    $res = $conn->query("SELECT COUNT(*) as active FROM device_bindings WHERE is_active = 1");
    $row = $res->fetch_assoc();
    $result['before']['active'] = (int)$row['active'];
    
    $res = $conn->query("SELECT COUNT(*) as inactive FROM device_bindings WHERE is_active = 0");
    $row = $res->fetch_assoc();
    $result['before']['inactive'] = (int)$row['inactive'];
    
    $result['steps'][] = 'Estado atual verificado';
    
    // 2. Encontrar o registro mais antigo de cada device_id (SEM depender da tabela users)
    $query = "
        SELECT 
            db1.id,
            db1.device_id,
            db1.user_id,
            db1.created_at
        FROM device_bindings db1
        WHERE db1.id = (
            SELECT MIN(db2.id) 
            FROM device_bindings db2 
            WHERE db2.device_id = db1.device_id
        )
    ";
    
    $res = $conn->query($query);
    $firstBindings = [];
    
    while ($row = $res->fetch_assoc()) {
        $firstBindings[] = $row;
    }
    
    $result['unique_devices'] = count($firstBindings);
    $result['steps'][] = 'Identificados ' . count($firstBindings) . ' dispositivos únicos';
    
    // 3. Desativar todos os registros
    $conn->query("UPDATE device_bindings SET is_active = 0");
    $result['steps'][] = 'Todos os registros desativados';
    
    // 4. Reativar apenas o primeiro registro de cada device_id
    $reactivated = 0;
    $reactivatedList = [];
    
    foreach ($firstBindings as $binding) {
        $stmt = $conn->prepare("UPDATE device_bindings SET is_active = 1 WHERE id = ?");
        $stmt->bind_param("i", $binding['id']);
        $stmt->execute();
        $stmt->close();
        $reactivated++;
        $reactivatedList[] = [
            'id' => $binding['id'],
            'user_id' => $binding['user_id'],
            'device_id_prefix' => substr($binding['device_id'], 0, 16) . '...',
            'created_at' => $binding['created_at']
        ];
    }
    
    $result['reactivated'] = $reactivated;
    $result['reactivated_list'] = $reactivatedList;
    $result['steps'][] = 'Reativados ' . $reactivated . ' registros';
    
    // 5. Verificar resultado final
    $res = $conn->query("SELECT COUNT(*) as active FROM device_bindings WHERE is_active = 1");
    $row = $res->fetch_assoc();
    $result['after']['active'] = (int)$row['active'];
    
    $res = $conn->query("SELECT COUNT(*) as inactive FROM device_bindings WHERE is_active = 0");
    $row = $res->fetch_assoc();
    $result['after']['inactive'] = (int)$row['inactive'];
    
    $result['steps'][] = 'Verificação final concluída';
    
    // 6. Listar todos os registros para debug
    $res = $conn->query("SELECT id, device_id, user_id, is_active, created_at FROM device_bindings ORDER BY id");
    $allBindings = [];
    while ($row = $res->fetch_assoc()) {
        $allBindings[] = [
            'id' => $row['id'],
            'device_id_prefix' => substr($row['device_id'], 0, 16) . '...',
            'user_id' => $row['user_id'],
            'is_active' => $row['is_active'],
            'created_at' => $row['created_at']
        ];
    }
    $result['all_bindings'] = $allBindings;
    
    $conn->close();
    
    $result['success'] = true;
    $result['message'] = 'Correção concluída com sucesso! Agora o sistema irá bloquear login de outras contas em dispositivos já vinculados.';
    
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);

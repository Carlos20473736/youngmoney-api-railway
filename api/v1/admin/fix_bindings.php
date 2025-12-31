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
    
    // 1. Listar todos os registros atuais
    $res = $conn->query("SELECT id, device_id, user_id, is_active, created_at FROM device_bindings ORDER BY id");
    $allBindings = [];
    while ($row = $res->fetch_assoc()) {
        $allBindings[] = $row;
    }
    $result['all_bindings_before'] = $allBindings;
    $result['steps'][] = 'Listados ' . count($allBindings) . ' registros';
    
    // 2. Encontrar device_ids únicos
    $res = $conn->query("SELECT DISTINCT device_id FROM device_bindings");
    $uniqueDevices = [];
    while ($row = $res->fetch_assoc()) {
        $uniqueDevices[] = $row['device_id'];
    }
    $result['unique_device_ids'] = count($uniqueDevices);
    $result['steps'][] = 'Encontrados ' . count($uniqueDevices) . ' device_ids únicos';
    
    // 3. Para cada device_id único, encontrar o registro mais antigo e reativá-lo
    $reactivated = 0;
    $reactivatedList = [];
    
    foreach ($uniqueDevices as $deviceId) {
        // Primeiro, desativar todos os registros deste device_id
        $stmt = $conn->prepare("UPDATE device_bindings SET is_active = 0 WHERE device_id = ?");
        $stmt->bind_param("s", $deviceId);
        $stmt->execute();
        $stmt->close();
        
        // Depois, encontrar o registro mais antigo (menor ID) e reativá-lo
        $stmt = $conn->prepare("SELECT id, user_id FROM device_bindings WHERE device_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->bind_param("s", $deviceId);
        $stmt->execute();
        $res = $stmt->get_result();
        $oldest = $res->fetch_assoc();
        $stmt->close();
        
        if ($oldest) {
            // Reativar o registro mais antigo
            $stmt = $conn->prepare("UPDATE device_bindings SET is_active = 1 WHERE id = ?");
            $stmt->bind_param("i", $oldest['id']);
            $stmt->execute();
            $stmt->close();
            
            $reactivated++;
            $reactivatedList[] = [
                'id' => $oldest['id'],
                'user_id' => $oldest['user_id'],
                'device_id_prefix' => substr($deviceId, 0, 20) . '...'
            ];
        }
    }
    
    $result['reactivated'] = $reactivated;
    $result['reactivated_list'] = $reactivatedList;
    $result['steps'][] = 'Reativados ' . $reactivated . ' registros (um por device_id)';
    
    // 4. Listar resultado final
    $res = $conn->query("SELECT id, device_id, user_id, is_active, created_at FROM device_bindings ORDER BY id");
    $allBindingsAfter = [];
    while ($row = $res->fetch_assoc()) {
        $allBindingsAfter[] = [
            'id' => $row['id'],
            'device_id_prefix' => substr($row['device_id'], 0, 20) . '...',
            'user_id' => $row['user_id'],
            'is_active' => $row['is_active'],
            'created_at' => $row['created_at']
        ];
    }
    $result['all_bindings_after'] = $allBindingsAfter;
    
    // Contar ativos e inativos
    $res = $conn->query("SELECT COUNT(*) as active FROM device_bindings WHERE is_active = 1");
    $row = $res->fetch_assoc();
    $result['final_active'] = (int)$row['active'];
    
    $res = $conn->query("SELECT COUNT(*) as inactive FROM device_bindings WHERE is_active = 0");
    $row = $res->fetch_assoc();
    $result['final_inactive'] = (int)$row['inactive'];
    
    $conn->close();
    
    $result['success'] = true;
    $result['message'] = 'Correção concluída! Dispositivos agora estão vinculados à primeira conta que os usou.';
    
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);

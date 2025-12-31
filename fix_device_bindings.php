<?php
/**
 * Script de Correção: Reativar vinculações de dispositivo
 * 
 * Este script corrige o problema causado pelo commit ef99cf9 que
 * desativou os registros de device_bindings (is_active = 0).
 * 
 * O que ele faz:
 * 1. Para cada device_id único, reativa o registro MAIS ANTIGO (primeiro a vincular)
 * 2. Mantém os outros registros do mesmo device_id como inativos
 * 3. Isso garante que o dispositivo fique vinculado à PRIMEIRA conta que o usou
 * 
 * Uso: php fix_device_bindings.php
 */

echo "=== CORREÇÃO DE VINCULAÇÕES DE DISPOSITIVO ===\n\n";

// Carregar configuração do banco
require_once __DIR__ . '/database.php';

try {
    $conn = getDbConnection();
    echo "✅ Conectado ao banco de dados\n\n";
    
    // 1. Primeiro, desativar TODOS os registros para começar do zero
    echo "📋 Passo 1: Verificando estado atual...\n";
    
    $result = $conn->query("SELECT COUNT(*) as total FROM device_bindings");
    $row = $result->fetch_assoc();
    echo "   Total de registros: {$row['total']}\n";
    
    $result = $conn->query("SELECT COUNT(*) as active FROM device_bindings WHERE is_active = 1");
    $row = $result->fetch_assoc();
    echo "   Registros ativos: {$row['active']}\n";
    
    $result = $conn->query("SELECT COUNT(*) as inactive FROM device_bindings WHERE is_active = 0");
    $row = $result->fetch_assoc();
    echo "   Registros inativos: {$row['inactive']}\n\n";
    
    // 2. Encontrar o registro mais antigo de cada device_id
    echo "📋 Passo 2: Identificando primeiro registro de cada dispositivo...\n";
    
    $query = "
        SELECT 
            db1.id,
            db1.device_id,
            db1.user_id,
            db1.created_at,
            u.email
        FROM device_bindings db1
        INNER JOIN users u ON db1.user_id = u.id
        WHERE db1.id = (
            SELECT MIN(db2.id) 
            FROM device_bindings db2 
            WHERE db2.device_id = db1.device_id
        )
        ORDER BY db1.created_at ASC
    ";
    
    $result = $conn->query($query);
    $firstBindings = [];
    
    while ($row = $result->fetch_assoc()) {
        $firstBindings[] = $row;
        echo "   Device: " . substr($row['device_id'], 0, 16) . "... -> User: {$row['email']} (ID: {$row['id']})\n";
    }
    
    echo "\n   Total de dispositivos únicos: " . count($firstBindings) . "\n\n";
    
    // 3. Desativar todos os registros
    echo "📋 Passo 3: Desativando todos os registros...\n";
    $conn->query("UPDATE device_bindings SET is_active = 0");
    echo "   ✅ Todos os registros desativados\n\n";
    
    // 4. Reativar apenas o primeiro registro de cada device_id
    echo "📋 Passo 4: Reativando primeiro registro de cada dispositivo...\n";
    
    $reactivated = 0;
    foreach ($firstBindings as $binding) {
        $stmt = $conn->prepare("UPDATE device_bindings SET is_active = 1 WHERE id = ?");
        $stmt->bind_param("i", $binding['id']);
        $stmt->execute();
        $stmt->close();
        $reactivated++;
        echo "   ✅ Reativado ID {$binding['id']} para {$binding['email']}\n";
    }
    
    echo "\n   Total reativados: {$reactivated}\n\n";
    
    // 5. Verificar resultado final
    echo "📋 Passo 5: Verificando resultado final...\n";
    
    $result = $conn->query("SELECT COUNT(*) as active FROM device_bindings WHERE is_active = 1");
    $row = $result->fetch_assoc();
    echo "   Registros ativos: {$row['active']}\n";
    
    $result = $conn->query("SELECT COUNT(*) as inactive FROM device_bindings WHERE is_active = 0");
    $row = $result->fetch_assoc();
    echo "   Registros inativos: {$row['inactive']}\n\n";
    
    // 6. Listar vinculações ativas
    echo "📋 Vinculações ativas:\n";
    $result = $conn->query("
        SELECT db.id, db.device_id, db.user_id, u.email, db.created_at
        FROM device_bindings db
        INNER JOIN users u ON db.user_id = u.id
        WHERE db.is_active = 1
        ORDER BY db.created_at ASC
    ");
    
    while ($row = $result->fetch_assoc()) {
        echo "   ID: {$row['id']} | User: {$row['email']} | Device: " . substr($row['device_id'], 0, 20) . "...\n";
    }
    
    $conn->close();
    
    echo "\n=== CORREÇÃO CONCLUÍDA COM SUCESSO ===\n";
    echo "\nAgora o sistema irá:\n";
    echo "1. Bloquear login de outras contas em dispositivos já vinculados\n";
    echo "2. Permitir login apenas da conta original (primeira a vincular)\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

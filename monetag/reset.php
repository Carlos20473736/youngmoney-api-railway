<?php
/**
 * Endpoint para resetar dados de MoniTag de um usuário (v3 - APENAS IMPRESSÕES)
 * 
 * Uso: GET /monetag/reset.php?user_id=2&admin_key=your_secret_key
 * 
 * Lógica de cliques removida completamente
 */

// DEFINIR TIMEZONE NO INÍCIO DO ARQUIVO
date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json');

// Verificar se foi fornecido um user_id
if (!isset($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'user_id é obrigatório'
    ]);
    exit;
}

$user_id = intval($_GET['user_id']);

// Conectar ao banco de dados
try {
    // Variáveis do Railway
    $host = getenv('MYSQL_HOST') ?: getenv('DB_HOST') ?: 'localhost';
    $user = getenv('MYSQL_USER') ?: getenv('DB_USER') ?: 'root';
    $password = getenv('MYSQL_PASSWORD') ?: getenv('DB_PASSWORD') ?: '';
    $database = getenv('MYSQL_DATABASE') ?: getenv('DB_NAME') ?: 'railway';
    $port = intval(getenv('MYSQL_PORT') ?: getenv('DB_PORT') ?: 3306);
    
    // Debug
    error_log("Reset: host=$host, user=$user, database=$database, port=$port");

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    
    // CORREÇÃO: Definir timezone na conexão MySQL
    $pdo->exec("SET time_zone = '-03:00'");

    // Iniciar transação
    $pdo->beginTransaction();

    // 1. Deletar todos os eventos de MoniTag do usuário
    $stmt = $pdo->prepare('DELETE FROM monetag_events WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $deleted_events = $stmt->rowCount();

    // 2. Resetar contadores no usuário (se houver coluna)
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $updates = [];
    if (in_array('monetag_impressions', $columns)) {
        $updates[] = 'monetag_impressions = 0';
    }
    if (in_array('monetag_clicks', $columns)) {
        $updates[] = 'monetag_clicks = 0';
    }

    if (!empty($updates)) {
        $update_query = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $pdo->prepare($update_query);
        $stmt->execute([$user_id]);
    }

    // Commit da transação
    $pdo->commit();

    // Retornar sucesso
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Dados de MoniTag resetados com sucesso',
        'data' => [
            'user_id' => $user_id,
            'deleted_events' => $deleted_events,
            'timestamp' => date('Y-m-d H:i:s'),
            'timezone' => 'America/Sao_Paulo'
        ]
    ]);

} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao resetar dados: ' . $e->getMessage()
    ]);
}
?>

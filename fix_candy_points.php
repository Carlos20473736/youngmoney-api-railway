<?php
/**
 * Script para devolver pontos do Candy não creditados
 * 
 * Este script:
 * 1. Busca todos os registros na tabela candy_scores
 * 2. Verifica quais pontos não foram creditados na tabela points_history
 * 3. Adiciona os pontos faltantes aos usuários
 */

require_once __DIR__ . '/db_config.php';

// Conectar ao banco
$conn = getMySQLiConnection();
if (!$conn) {
    die("Erro ao conectar ao banco de dados\n");
}

echo "=== SCRIPT DE RECUPERAÇÃO DE PONTOS DO CANDY ===\n\n";

// Buscar todos os scores do Candy que não foram creditados
// Comparar candy_scores com points_history
$query = "
    SELECT cs.user_id, cs.score, cs.created_at, u.email, u.name
    FROM candy_scores cs
    LEFT JOIN users u ON cs.user_id = u.id
    WHERE NOT EXISTS (
        SELECT 1 FROM points_history ph 
        WHERE ph.user_id = cs.user_id 
        AND ph.description LIKE '%Candy%'
        AND ph.created_at >= cs.created_at - INTERVAL 1 MINUTE
        AND ph.created_at <= cs.created_at + INTERVAL 1 MINUTE
    )
    ORDER BY cs.created_at DESC
";

$result = $conn->query($query);

if (!$result) {
    // Se a tabela candy_scores não existir, tentar outra abordagem
    echo "Tabela candy_scores não encontrada. Verificando game_levels...\n\n";
    
    // Buscar usuários que jogaram mas não têm pontos do Candy no histórico
    $query2 = "
        SELECT gl.user_id, gl.last_level_score, gl.highest_level, gl.updated_at, u.email, u.name
        FROM game_levels gl
        LEFT JOIN users u ON gl.user_id = u.id
        WHERE gl.last_level_score > 0
        ORDER BY gl.updated_at DESC
    ";
    
    $result2 = $conn->query($query2);
    
    if ($result2 && $result2->num_rows > 0) {
        echo "Usuários com levels no Candy:\n";
        echo str_repeat("-", 80) . "\n";
        
        while ($row = $result2->fetch_assoc()) {
            echo "User ID: " . $row['user_id'] . "\n";
            echo "Nome: " . ($row['name'] ?? 'N/A') . "\n";
            echo "Email: " . ($row['email'] ?? 'N/A') . "\n";
            echo "Último Score: " . $row['last_level_score'] . "\n";
            echo "Highest Level: " . $row['highest_level'] . "\n";
            echo "Atualizado em: " . $row['updated_at'] . "\n";
            echo str_repeat("-", 80) . "\n";
        }
    }
    
    $conn->close();
    exit;
}

$totalRecuperado = 0;
$usuariosAfetados = [];

if ($result->num_rows > 0) {
    echo "Encontrados " . $result->num_rows . " registros de pontos não creditados:\n\n";
    
    while ($row = $result->fetch_assoc()) {
        $userId = $row['user_id'];
        $score = $row['score'];
        $email = $row['email'] ?? 'N/A';
        $name = $row['name'] ?? 'N/A';
        
        echo "- User ID: $userId | Nome: $name | Email: $email | Score: $score\n";
        
        // Adicionar pontos ao usuário
        $stmt = $conn->prepare("UPDATE users SET daily_points = daily_points + ?, points = points + ? WHERE id = ?");
        $stmt->bind_param("iii", $score, $score, $userId);
        $stmt->execute();
        $stmt->close();
        
        // Registrar no histórico
        $description = "Candy Crush - Pontos recuperados: " . $score;
        $stmt = $conn->prepare("INSERT INTO points_history (user_id, points, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $userId, $score, $description);
        $stmt->execute();
        $stmt->close();
        
        $totalRecuperado += $score;
        if (!isset($usuariosAfetados[$userId])) {
            $usuariosAfetados[$userId] = ['name' => $name, 'email' => $email, 'total' => 0];
        }
        $usuariosAfetados[$userId]['total'] += $score;
    }
    
    echo "\n=== RESUMO ===\n";
    echo "Total de pontos recuperados: $totalRecuperado\n";
    echo "Usuários afetados: " . count($usuariosAfetados) . "\n\n";
    
    foreach ($usuariosAfetados as $uid => $data) {
        echo "- $uid: {$data['name']} ({$data['email']}) -> +{$data['total']} pontos\n";
    }
} else {
    echo "Nenhum ponto não creditado encontrado.\n";
    
    // Mostrar estatísticas gerais
    echo "\n=== ESTATÍSTICAS DO CANDY ===\n";
    
    $statsQuery = "SELECT COUNT(*) as total_scores, SUM(score) as total_points FROM candy_scores";
    $statsResult = $conn->query($statsQuery);
    if ($statsResult && $row = $statsResult->fetch_assoc()) {
        echo "Total de scores registrados: " . ($row['total_scores'] ?? 0) . "\n";
        echo "Total de pontos: " . ($row['total_points'] ?? 0) . "\n";
    }
}

$conn->close();
echo "\n=== FIM DO SCRIPT ===\n";
?>

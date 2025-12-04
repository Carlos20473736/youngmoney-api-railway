<?php
require_once __DIR__ . '/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    $offset = ($page - 1) * $limit;
    
    $conn = getDbConnection();
    
    // Construir query com busca
    $whereClause = '';
    $params = [];
    $types = '';
    
    if ($search) {
        $whereClause = " WHERE name LIKE ? OR email LIKE ?";
        $searchParam = "%$search%";
        $params = [$searchParam, $searchParam];
        $types = 'ss';
    }
    
    // Contar total
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users" . $whereClause);
    if ($search) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    
    // Buscar usuários
    $stmt = $conn->prepare("
        SELECT 
            id, 
            name, 
            email, 
            points, 
            created_at
        FROM users" . $whereClause . "
        ORDER BY points DESC
        LIMIT ? OFFSET ?
    ");
    
    if ($search) {
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'points' => (int)$row['points'],
            'daily_points' => 0,
            'created_at' => $row['created_at'],
            'banned' => false
        ];
    }
    
    $totalPages = ceil($total / $limit);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'users' => $users,
            'total' => (int)$total,
            'page' => $page,
            'totalPages' => $totalPages
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>

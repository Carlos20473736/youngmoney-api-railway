<?php
header('Content-Type: text/html; charset=utf-8');

// Database configuration from environment variables
$host = getenv('MYSQLHOST') ?: 'localhost';
$port = getenv('MYSQLPORT') ?: '3306';
$database = getenv('MYSQLDATABASE') ?: 'railway';
$username = getenv('MYSQLUSER') ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: '';

// Create connection
$conn = new mysqli($host, $username, $password, $database, $port);

// Check connection
if ($conn->connect_error) {
    die("❌ Conexão falhou: " . $conn->connect_error);
}

echo "✅ Conectado ao banco de dados com sucesso!<br>";
echo "📊 Database: " . $conn->server_info . "<br>";
echo "🖥️ Host: $host:$port<br><br>";

// Check if table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'withdrawal_quick_values'");
if ($tableCheck->num_rows == 0) {
    die("❌ Tabela 'withdrawal_quick_values' não existe!");
}

echo "✅ Tabela 'withdrawal_quick_values' existe!<br><br>";

// Check if 'value' column exists and drop it
echo "🔧 Verificando coluna 'value' antiga...<br>";
$checkValueColumn = $conn->query("SHOW COLUMNS FROM withdrawal_quick_values LIKE 'value'");
if ($checkValueColumn->num_rows > 0) {
    echo "🔧 Removendo coluna 'value' antiga...<br>";
    $dropValueColumn = "ALTER TABLE withdrawal_quick_values DROP COLUMN value";
    if ($conn->query($dropValueColumn) === TRUE) {
        echo "✅ Coluna 'value' removida com sucesso!<br>";
    } else {
        echo "❌ Erro ao remover coluna 'value': " . $conn->error . "<br>";
    }
} else {
    echo "ℹ️ Coluna 'value' não existe ou já foi removida!<br>";
}

// Add UNIQUE constraint to value_amount if not exists
echo "<br>🔧 Adicionando UNIQUE constraint na coluna 'value_amount'...<br>";
$addUniqueConstraint = "ALTER TABLE withdrawal_quick_values ADD UNIQUE KEY unique_value_amount (value_amount)";
if ($conn->query($addUniqueConstraint) === TRUE) {
    echo "✅ UNIQUE constraint adicionada com sucesso!<br>";
} else {
    if (strpos($conn->error, 'Duplicate key name') !== false) {
        echo "ℹ️ UNIQUE constraint já existe!<br>";
    } else {
        echo "❌ Erro ao adicionar UNIQUE constraint: " . $conn->error . "<br>";
    }
}

// Add display_order column if not exists
echo "<br>🔧 Verificando coluna 'display_order'...<br>";
$checkDisplayOrder = $conn->query("SHOW COLUMNS FROM withdrawal_quick_values LIKE 'display_order'");
if ($checkDisplayOrder->num_rows == 0) {
    echo "🔧 Adicionando coluna 'display_order'...<br>";
    $addDisplayOrder = "ALTER TABLE withdrawal_quick_values ADD COLUMN display_order INT DEFAULT 0";
    if ($conn->query($addDisplayOrder) === TRUE) {
        echo "✅ Coluna 'display_order' adicionada com sucesso!<br>";
    } else {
        echo "❌ Erro ao adicionar coluna 'display_order': " . $conn->error . "<br>";
    }
} else {
    echo "ℹ️ Coluna 'display_order' já existe!<br>";
}

// Add updated_at column if not exists
echo "<br>🔧 Verificando coluna 'updated_at'...<br>";
$checkUpdatedAt = $conn->query("SHOW COLUMNS FROM withdrawal_quick_values LIKE 'updated_at'");
if ($checkUpdatedAt->num_rows == 0) {
    echo "🔧 Adicionando coluna 'updated_at'...<br>";
    $addUpdatedAt = "ALTER TABLE withdrawal_quick_values ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    if ($conn->query($addUpdatedAt) === TRUE) {
        echo "✅ Coluna 'updated_at' adicionada com sucesso!<br>";
    } else {
        echo "❌ Erro ao adicionar coluna 'updated_at': " . $conn->error . "<br>";
    }
} else {
    echo "ℹ️ Coluna 'updated_at' já existe!<br>";
}

// Show final table structure
echo "<br>📋 Estrutura final da tabela withdrawal_quick_values:<br>";
echo "------------------------------------------------------------<br>";
$result = $conn->query("DESCRIBE withdrawal_quick_values");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " " . $row['Type'] . " " . $row['Null'] . "<br>";
}
echo "------------------------------------------------------------<br>";

echo "<br>🎉 Migração concluída com sucesso!<br>";

$conn->close();
?>

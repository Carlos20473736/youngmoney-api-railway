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

// Drop old column 'value' if it exists
echo "🔧 Removendo coluna 'value' antiga...<br>";
$dropValueColumn = "ALTER TABLE withdrawal_quick_values DROP COLUMN IF EXISTS value";
if ($conn->query($dropValueColumn) === TRUE) {
    echo "✅ Coluna 'value' removida com sucesso!<br>";
} else {
    echo "ℹ️ Coluna 'value' não existe ou já foi removida: " . $conn->error . "<br>";
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
echo "<br>🔧 Adicionando coluna 'display_order'...<br>";
$addDisplayOrder = "ALTER TABLE withdrawal_quick_values ADD COLUMN IF NOT EXISTS display_order INT DEFAULT 0";
if ($conn->query($addDisplayOrder) === TRUE) {
    echo "✅ Coluna 'display_order' adicionada com sucesso!<br>";
} else {
    echo "ℹ️ Coluna 'display_order' já existe ou erro: " . $conn->error . "<br>";
}

// Add updated_at column if not exists
echo "<br>🔧 Adicionando coluna 'updated_at'...<br>";
$addUpdatedAt = "ALTER TABLE withdrawal_quick_values ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
if ($conn->query($addUpdatedAt) === TRUE) {
    echo "✅ Coluna 'updated_at' adicionada com sucesso!<br>";
} else {
    echo "ℹ️ Coluna 'updated_at' já existe ou erro: " . $conn->error . "<br>";
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

<?php
/**
 * Script para executar migration das tabelas de segurança V3
 * Execute este script uma vez para criar as tabelas necessárias
 */

require_once __DIR__ . '/database.php';

echo "=== YoungMoney Security Migration V3 ===\n\n";

try {
    $conn = getDbConnection();
    echo "✓ Conexão com banco de dados estabelecida\n\n";
    
    // Ler arquivo SQL
    $sqlFile = __DIR__ . '/migrations/security_tables_v3.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Arquivo SQL não encontrado: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    echo "✓ Arquivo SQL carregado\n\n";
    
    // Separar comandos (ignorar eventos por enquanto)
    $statements = [];
    $currentStatement = '';
    $inEvent = false;
    
    foreach (explode("\n", $sql) as $line) {
        $trimmedLine = trim($line);
        
        // Ignorar comentários e linhas vazias
        if (empty($trimmedLine) || strpos($trimmedLine, '--') === 0) {
            continue;
        }
        
        // Detectar início de evento
        if (stripos($trimmedLine, 'CREATE EVENT') !== false) {
            $inEvent = true;
        }
        
        // Detectar fim de evento
        if ($inEvent && stripos($trimmedLine, 'END;') !== false) {
            $inEvent = false;
            continue; // Pular eventos por enquanto
        }
        
        // Pular linhas dentro de eventos
        if ($inEvent) {
            continue;
        }
        
        // Pular comandos de evento
        if (stripos($trimmedLine, 'DROP EVENT') !== false || 
            stripos($trimmedLine, 'SET GLOBAL event_scheduler') !== false) {
            continue;
        }
        
        $currentStatement .= ' ' . $trimmedLine;
        
        // Se termina com ;, é fim do comando
        if (substr($trimmedLine, -1) === ';') {
            $statements[] = trim($currentStatement);
            $currentStatement = '';
        }
    }
    
    echo "Executando " . count($statements) . " comandos SQL...\n\n";
    
    $success = 0;
    $failed = 0;
    
    foreach ($statements as $statement) {
        if (empty(trim($statement))) continue;
        
        try {
            $conn->exec($statement);
            
            // Extrair nome da tabela do comando
            if (preg_match('/CREATE TABLE.*?(\w+)\s*\(/i', $statement, $matches)) {
                echo "✓ Tabela criada: {$matches[1]}\n";
            } else {
                echo "✓ Comando executado\n";
            }
            
            $success++;
        } catch (PDOException $e) {
            // Ignorar erros de tabela já existente
            if (strpos($e->getMessage(), 'already exists') !== false) {
                if (preg_match('/CREATE TABLE.*?(\w+)\s*\(/i', $statement, $matches)) {
                    echo "○ Tabela já existe: {$matches[1]}\n";
                }
                $success++;
            } else {
                echo "✗ Erro: " . $e->getMessage() . "\n";
                $failed++;
            }
        }
    }
    
    echo "\n=== Resultado ===\n";
    echo "Sucesso: $success\n";
    echo "Falhas: $failed\n";
    
    if ($failed === 0) {
        echo "\n✓ Migration concluída com sucesso!\n";
    } else {
        echo "\n⚠ Migration concluída com alguns erros\n";
    }
    
} catch (Exception $e) {
    echo "✗ Erro fatal: " . $e->getMessage() . "\n";
    exit(1);
}

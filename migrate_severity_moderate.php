<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();
    $pdo->exec("UPDATE incident_record SET severity_level = 'moderate' WHERE severity_level = 'medium'");
    $pdo->exec("UPDATE incident_record SET severity_level = 'low' WHERE severity_level IS NULL OR severity_level = ''");
    // MySQL implicitly commits around ALTER TABLE, so this migration must not
    // wrap the data update and schema change in one PDO transaction.
    $pdo->exec("ALTER TABLE incident_record MODIFY COLUMN severity_level ENUM('low', 'moderate', 'high', 'critical') DEFAULT 'low'");

    echo "Severity migration successful.\n";
    $column = $pdo->query(
        "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'incident_record'
           AND COLUMN_NAME = 'severity_level'"
    )->fetchColumn();
    echo "Enum: {$column}\n";
    $rows = $pdo->query(
        'SELECT severity_level, COUNT(*) AS total
         FROM incident_record
         GROUP BY severity_level
         ORDER BY severity_level'
    )->fetchAll();
    foreach ($rows as $row) {
        echo "{$row['severity_level']}: {$row['total']}\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Severity migration failed: {$e->getMessage()}\n");
    exit(1);
}
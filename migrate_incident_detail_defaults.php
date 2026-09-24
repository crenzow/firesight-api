<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();
    $columnStmt = $pdo->prepare(
        "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'incident_record'
           AND COLUMN_NAME = :column_name"
    );

    foreach (['incident_type', 'severity_level'] as $columnName) {
        $columnStmt->execute(['column_name' => $columnName]);
        $columnType = $columnStmt->fetchColumn();
        if (!$columnType) {
            throw new RuntimeException("Column not found: {$columnName}");
        }
        $pdo->exec("ALTER TABLE incident_record MODIFY COLUMN `{$columnName}` {$columnType} NULL DEFAULT NULL");
    }

    echo "Incident detail defaults removed successfully.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Incident detail migration failed: {$e->getMessage()}\n");
    exit(1);
}

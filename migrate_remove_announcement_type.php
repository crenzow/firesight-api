<?php
require_once __DIR__ . '/config/database.php';

echo "=== FireSight: Remove announcement_type Migration ===\n";

try {
    $pdo = getDbConnection();

    // Check if announcement_type column exists
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = :dbname 
          AND TABLE_NAME = 'announcement' 
          AND COLUMN_NAME = 'announcement_type'
    ");
    $stmt->execute(['dbname' => DB_NAME]);
    $columnExists = ((int) $stmt->fetch()['cnt']) > 0;

    if ($columnExists) {
        echo "Found column 'announcement_type' in table 'announcement'. Dropping column...\n";
        $pdo->exec("ALTER TABLE announcement DROP COLUMN announcement_type");
        echo "Successfully dropped 'announcement_type' from table 'announcement'.\n";
    } else {
        echo "Column 'announcement_type' does not exist in table 'announcement' (already removed).\n";
    }

    echo "\nCurrent table structure for 'announcement':\n";
    $describe = $pdo->query("DESCRIBE announcement")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($describe as $col) {
        echo sprintf(" - %-20s %-25s Null: %-5s Default: %s\n", 
            $col['Field'], 
            $col['Type'], 
            $col['Null'], 
            $col['Default'] ?? 'NULL'
        );
    }

    echo "\nMigration completed successfully.\n";
} catch (Exception $e) {
    echo "ERROR during migration: " . $e->getMessage() . "\n";
    exit(1);
}

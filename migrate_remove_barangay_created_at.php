<?php
require_once __DIR__ . '/config/database.php';

echo "=== FireSight: Remove created_at from barangay table ===\n";

try {
    $pdo = getDbConnection();

    // Check if created_at column exists in barangay table
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = :dbname 
          AND TABLE_NAME = 'barangay' 
          AND COLUMN_NAME = 'created_at'
    ");
    $stmt->execute(['dbname' => DB_NAME]);
    $columnExists = ((int) $stmt->fetch()['cnt']) > 0;

    if ($columnExists) {
        echo "Found column 'created_at' in table 'barangay'. Dropping column...\n";
        $pdo->exec("ALTER TABLE barangay DROP COLUMN created_at");
        echo "Successfully dropped 'created_at' from table 'barangay'.\n";
    } else {
        echo "Column 'created_at' does not exist in table 'barangay' (already removed).\n";
    }

    echo "\nCurrent table structure for 'barangay':\n";
    $describe = $pdo->query("DESCRIBE barangay")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($describe as $col) {
        echo sprintf(" - %-20s %-25s Null: %-5s Default: %s\n", 
            $col['Field'], 
            $col['Type'], 
            $col['Null'], 
            $col['Default'] ?? 'NULL'
        );
    }

    echo "\nTotal rows in barangay: " . $pdo->query("SELECT COUNT(*) FROM barangay")->fetchColumn() . "\n";
    echo "\nMigration completed successfully.\n";
} catch (Exception $e) {
    echo "ERROR during migration: " . $e->getMessage() . "\n";
    exit(1);
}

<?php
require_once __DIR__ . '/config/database.php';

echo "=== FireSight: Match bfp_personnel_details table structure ===\n";

try {
    $pdo = getDbConnection();

    // 1. Check and drop foreign key constraint if it exists (since user_id is becoming bigint unsigned)
    echo "1. Checking foreign key constraints...\n";
    $stmt = $pdo->prepare("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = :dbname 
          AND TABLE_NAME = 'bfp_personnel_details' 
          AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $stmt->execute(['dbname' => DB_NAME]);
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($constraints as $c) {
        $cName = $c['CONSTRAINT_NAME'];
        echo " - Dropping FK constraint {$cName}...\n";
        $pdo->exec("ALTER TABLE bfp_personnel_details DROP FOREIGN KEY `{$cName}`");
    }

    // 2. Add duty_group column if missing, and alter columns to match specification
    echo "2. Altering bfp_personnel_details schema...\n";

    // Check if duty_group column exists
    $chk = $pdo->prepare("
        SELECT COUNT(*) AS cnt 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = :dbname 
          AND TABLE_NAME = 'bfp_personnel_details' 
          AND COLUMN_NAME = 'duty_group'
    ");
    $chk->execute(['dbname' => DB_NAME]);
    if (((int) $chk->fetch()['cnt']) === 0) {
        $pdo->exec("ALTER TABLE bfp_personnel_details ADD COLUMN duty_group varchar(20) DEFAULT NULL");
        echo " - Added duty_group column\n";
    }

    $pdo->exec("
        ALTER TABLE bfp_personnel_details
        MODIFY COLUMN details_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN user_id bigint(20) UNSIGNED NOT NULL,
        MODIFY COLUMN `rank` varchar(100) NOT NULL,
        MODIFY COLUMN station_assigned varchar(150) NOT NULL DEFAULT 'BFP Lian Fire Station',
        MODIFY COLUMN employee_number varchar(50) NOT NULL,
        MODIFY COLUMN duty_group varchar(20) DEFAULT NULL,
        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    echo " - Altered columns and converted to utf8mb4_unicode_ci\n";

    echo "\nUpdated SHOW CREATE TABLE bfp_personnel_details:\n";
    $show = $pdo->query("SHOW CREATE TABLE bfp_personnel_details")->fetch(PDO::FETCH_ASSOC);
    echo $show['Create Table'] . "\n\n";

    $count = $pdo->query("SELECT COUNT(*) FROM bfp_personnel_details")->fetchColumn();
    echo "Total rows preserved in bfp_personnel_details: " . $count . "\n";
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "ERROR during migration: " . $e->getMessage() . "\n";
    exit(1);
}

<?php
require_once __DIR__ . '/config/database.php';

echo "=== FireSight: Match announcement table structure ===\n";

try {
    $pdo = getDbConnection();

    // 1. Drop announcement_type if it exists
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = :dbname 
          AND TABLE_NAME = 'announcement' 
          AND COLUMN_NAME = 'announcement_type'
    ");
    $stmt->execute(['dbname' => DB_NAME]);
    if (((int) $stmt->fetch()['cnt']) > 0) {
        $pdo->exec("ALTER TABLE announcement DROP COLUMN announcement_type");
        echo " - Dropped column 'announcement_type'\n";
    }

    // 2. Modify columns to match requested structure
    $pdo->exec("
        ALTER TABLE announcement 
        MODIFY COLUMN announcement_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN created_by bigint(20) UNSIGNED NOT NULL,
        MODIFY COLUMN title varchar(150) NOT NULL,
        MODIFY COLUMN content text NOT NULL,
        MODIFY COLUMN created_at timestamp NOT NULL DEFAULT current_timestamp(),
        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    echo " - Altered columns and converted charset/collation to utf8mb4_unicode_ci\n";

    echo "\nUpdated SHOW CREATE TABLE announcement:\n";
    $show = $pdo->query("SHOW CREATE TABLE announcement")->fetch(PDO::FETCH_ASSOC);
    echo $show['Create Table'] . "\n\n";

    $count = $pdo->query("SELECT COUNT(*) FROM announcement")->fetchColumn();
    echo "Total rows preserved: " . $count . "\n";
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

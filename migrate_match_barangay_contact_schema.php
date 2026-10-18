<?php
require_once __DIR__ . '/config/database.php';

echo "=== FireSight: Match barangay_contact table structure ===\n";

try {
    $pdo = getDbConnection();

    // Modify barangay_contact columns and collation
    $pdo->exec("
        ALTER TABLE barangay_contact 
        MODIFY COLUMN contact_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN barangay_id bigint(20) UNSIGNED NOT NULL,
        MODIFY COLUMN name varchar(100) NOT NULL,
        MODIFY COLUMN role varchar(50) NOT NULL,
        MODIFY COLUMN phone_number varchar(20) NOT NULL,
        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    echo " - Altered columns and converted collation to utf8mb4_unicode_ci\n";

    echo "\nUpdated SHOW CREATE TABLE barangay_contact:\n";
    $show = $pdo->query("SHOW CREATE TABLE barangay_contact")->fetch(PDO::FETCH_ASSOC);
    echo $show['Create Table'] . "\n\n";

    $count = $pdo->query("SELECT COUNT(*) FROM barangay_contact")->fetchColumn();
    echo "Total rows preserved in barangay_contact: " . $count . "\n";
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "ERROR during migration: " . $e->getMessage() . "\n";
    exit(1);
}

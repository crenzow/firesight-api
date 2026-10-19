<?php
require_once __DIR__ . '/config/database.php';

echo "=== FireSight: Update resident_address Table Schema ===\n";

try {
    $pdo = getDbConnection();

    // 1. Ensure municipality and province are populated
    echo "1. Populating default municipality and province if needed...\n";
    $pdo->exec("UPDATE resident_address SET municipality = 'Lian' WHERE municipality IS NULL OR municipality = ''");
    $pdo->exec("UPDATE resident_address SET province = 'Batangas' WHERE province IS NULL OR province = ''");

    // 2. Alter columns to match exact requested specification
    echo "2. Altering resident_address table columns...\n";
    $pdo->exec("
        ALTER TABLE `resident_address`
        MODIFY COLUMN `address_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `user_id` bigint(20) UNSIGNED DEFAULT NULL,
        MODIFY COLUMN `house_no_street` varchar(150) DEFAULT NULL,
        MODIFY COLUMN `barangay_id` bigint(20) UNSIGNED DEFAULT NULL,
        MODIFY COLUMN `municipality` varchar(100) NOT NULL DEFAULT 'Lian',
        MODIFY COLUMN `province` varchar(100) NOT NULL DEFAULT 'Batangas',
        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    echo " - resident_address table successfully updated.\n\n";

    echo "Updated SHOW CREATE TABLE resident_address:\n";
    echo $pdo->query("SHOW CREATE TABLE resident_address")->fetch(PDO::FETCH_ASSOC)['Create Table'] . "\n\n";

    $count = $pdo->query("SELECT COUNT(*) FROM resident_address")->fetchColumn();
    echo "Total preserved rows in resident_address: {$count}\n";

    $rows = $pdo->query("SELECT * FROM resident_address")->fetchAll(PDO::FETCH_ASSOC);
    echo "Preserved addresses:\n";
    foreach ($rows as $r) {
        echo " - ID: {$r['address_id']} | User: {$r['user_id']} | Brgy: {$r['barangay_id']} | Street: {$r['house_no_street']} | {$r['municipality']}, {$r['province']}\n";
    }

    echo "\nMigration finished successfully!\n";
} catch (Exception $e) {
    echo "ERROR during migration: " . $e->getMessage() . "\n";
    exit(1);
}

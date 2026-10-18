<?php
require_once __DIR__ . '/config/database.php';

echo "=== FireSight: Match emergency_contact and fire_education_content schemas ===\n";

try {
    $pdo = getDbConnection();

    // 1. Update emergency_contact
    echo "1. Altering emergency_contact table...\n";
    $pdo->exec("
        ALTER TABLE emergency_contact 
        MODIFY COLUMN contact_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN name varchar(150) DEFAULT NULL,
        MODIFY COLUMN category enum('fire','police','medical','disaster','other') NOT NULL DEFAULT 'other',
        MODIFY COLUMN phone_number varchar(20) DEFAULT NULL,
        MODIFY COLUMN description varchar(255) DEFAULT NULL,
        MODIFY COLUMN is_primary tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Shown in the top \"In Case of Fire\" quick-dial card',
        MODIFY COLUMN sort_order int(11) NOT NULL DEFAULT 0,
        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    echo " - emergency_contact updated successfully.\n";

    // 2. Update fire_education_content
    echo "2. Altering fire_education_content table...\n";
    $pdo->exec("
        ALTER TABLE fire_education_content 
        MODIFY COLUMN content_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN title varchar(255) DEFAULT NULL,
        MODIFY COLUMN category enum('prevention','emergency_response','awareness') NOT NULL DEFAULT 'prevention',
        MODIFY COLUMN summary varchar(255) DEFAULT NULL,
        MODIFY COLUMN body text DEFAULT NULL,
        MODIFY COLUMN image_path varchar(255) DEFAULT NULL,
        MODIFY COLUMN is_featured tinyint(1) NOT NULL DEFAULT 0,
        MODIFY COLUMN created_at timestamp NOT NULL DEFAULT current_timestamp(),
        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    echo " - fire_education_content updated successfully.\n";

    echo "\nUpdated SHOW CREATE TABLE emergency_contact:\n";
    echo $pdo->query("SHOW CREATE TABLE emergency_contact")->fetch(PDO::FETCH_ASSOC)['Create Table'] . "\n\n";

    echo "Updated SHOW CREATE TABLE fire_education_content:\n";
    echo $pdo->query("SHOW CREATE TABLE fire_education_content")->fetch(PDO::FETCH_ASSOC)['Create Table'] . "\n\n";

    $countContacts = $pdo->query("SELECT COUNT(*) FROM emergency_contact")->fetchColumn();
    $countEdu = $pdo->query("SELECT COUNT(*) FROM fire_education_content")->fetchColumn();
    echo "Preserved rows: emergency_contact = {$countContacts}, fire_education_content = {$countEdu}\n";

    echo "\nMigration completed successfully!\n";
} catch (Exception $e) {
    echo "ERROR during migration: " . $e->getMessage() . "\n";
    exit(1);
}

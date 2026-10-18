<?php
require_once __DIR__ . '/config/database.php';

echo "=== FireSight: Match barangay table structure ===\n";

try {
    $pdo = getDbConnection();

    // 1. Temporarily drop the 5 foreign key constraints
    echo "1. Dropping foreign key constraints...\n";
    $pdo->exec("ALTER TABLE barangay_contact DROP FOREIGN KEY barangay_contact_ibfk_1");
    $pdo->exec("ALTER TABLE community_report DROP FOREIGN KEY community_report_ibfk_2");
    $pdo->exec("ALTER TABLE incident_record DROP FOREIGN KEY incident_record_ibfk_2");
    $pdo->exec("ALTER TABLE resident_address DROP FOREIGN KEY resident_address_ibfk_2");
    $pdo->exec("ALTER TABLE risk_assessment DROP FOREIGN KEY risk_assessment_ibfk_1");
    echo " - Foreign keys dropped successfully.\n";

    // 2. Modify barangay table
    echo "2. Updating barangay table schema...\n";
    $pdo->exec("
        ALTER TABLE barangay 
        MODIFY COLUMN barangay_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN barangay_name varchar(100) NOT NULL,
        MODIFY COLUMN centroid_lat decimal(10,8) DEFAULT NULL,
        MODIFY COLUMN centroid_lng decimal(11,8) DEFAULT NULL,
        MODIFY COLUMN boundary_geojson longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`boundary_geojson`)),
        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    echo " - barangay table altered successfully.\n";

    // 3. Update referencing foreign key columns to bigint(20) unsigned
    echo "3. Updating referencing columns in child tables to bigint(20) unsigned...\n";
    $pdo->exec("ALTER TABLE barangay_contact MODIFY COLUMN barangay_id bigint(20) UNSIGNED NOT NULL");
    $pdo->exec("ALTER TABLE community_report MODIFY COLUMN barangay_id bigint(20) UNSIGNED DEFAULT NULL");
    $pdo->exec("ALTER TABLE incident_record MODIFY COLUMN barangay_id bigint(20) UNSIGNED DEFAULT NULL");
    $pdo->exec("ALTER TABLE resident_address MODIFY COLUMN barangay_id bigint(20) UNSIGNED NOT NULL");
    $pdo->exec("ALTER TABLE risk_assessment MODIFY COLUMN barangay_id bigint(20) UNSIGNED NOT NULL");
    echo " - Child columns updated successfully.\n";

    // 4. Re-add foreign key constraints
    echo "4. Re-adding foreign key constraints...\n";
    $pdo->exec("ALTER TABLE barangay_contact ADD CONSTRAINT barangay_contact_ibfk_1 FOREIGN KEY (barangay_id) REFERENCES barangay (barangay_id) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE community_report ADD CONSTRAINT community_report_ibfk_2 FOREIGN KEY (barangay_id) REFERENCES barangay (barangay_id) ON DELETE SET NULL");
    $pdo->exec("ALTER TABLE incident_record ADD CONSTRAINT incident_record_ibfk_2 FOREIGN KEY (barangay_id) REFERENCES barangay (barangay_id) ON DELETE SET NULL");
    $pdo->exec("ALTER TABLE resident_address ADD CONSTRAINT resident_address_ibfk_2 FOREIGN KEY (barangay_id) REFERENCES barangay (barangay_id) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE risk_assessment ADD CONSTRAINT risk_assessment_ibfk_1 FOREIGN KEY (barangay_id) REFERENCES barangay (barangay_id) ON DELETE CASCADE");
    echo " - Foreign keys re-added successfully.\n";

    echo "\nUpdated SHOW CREATE TABLE barangay:\n";
    $show = $pdo->query("SHOW CREATE TABLE barangay")->fetch(PDO::FETCH_ASSOC);
    echo $show['Create Table'] . "\n\n";

    $count = $pdo->query("SELECT COUNT(*) FROM barangay")->fetchColumn();
    echo "Total rows preserved in barangay: " . $count . "\n";
    echo "\nMigration completed successfully!\n";
} catch (Exception $e) {
    echo "ERROR during migration: " . $e->getMessage() . "\n";
    exit(1);
}

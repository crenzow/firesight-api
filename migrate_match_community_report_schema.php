<?php
require_once __DIR__ . '/config/database.php';

echo "=== FireSight: Match community_report table structure ===\n";

try {
    $pdo = getDbConnection();

    // 1. Drop foreign keys referencing community_report(report_id)
    echo "1. Dropping foreign key constraints referencing community_report...\n";
    $pdo->exec("ALTER TABLE incident_record DROP FOREIGN KEY incident_record_ibfk_1");
    $pdo->exec("ALTER TABLE report_evidence DROP FOREIGN KEY report_evidence_ibfk_1");
    $pdo->exec("ALTER TABLE report_link DROP FOREIGN KEY report_link_ibfk_1");
    $pdo->exec("ALTER TABLE report_link DROP FOREIGN KEY report_link_ibfk_2");
    $pdo->exec("ALTER TABLE report_status_history DROP FOREIGN KEY report_status_history_ibfk_1");
    echo " - Referencing child FKs dropped.\n";

    // 2. Drop community_report's own FK to user if present (since user_id is becoming bigint unsigned)
    echo "2. Dropping FK to user table if present...\n";
    $stmt = $pdo->prepare("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = :dbname 
          AND TABLE_NAME = 'community_report' 
          AND REFERENCED_TABLE_NAME = 'user'
    ");
    $stmt->execute(['dbname' => DB_NAME]);
    $userFks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($userFks as $ufk) {
        $cName = $ufk['CONSTRAINT_NAME'];
        $pdo->exec("ALTER TABLE community_report DROP FOREIGN KEY `{$cName}`");
        echo " - Dropped FK `{$cName}` to user table.\n";
    }

    // 3. Drop community_report's FK to barangay temporarily
    echo "3. Temporarily dropping FK to barangay...\n";
    $stmt = $pdo->prepare("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = :dbname 
          AND TABLE_NAME = 'community_report' 
          AND REFERENCED_TABLE_NAME = 'barangay'
    ");
    $stmt->execute(['dbname' => DB_NAME]);
    $bgyFks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($bgyFks as $bfk) {
        $cName = $bfk['CONSTRAINT_NAME'];
        $pdo->exec("ALTER TABLE community_report DROP FOREIGN KEY `{$cName}`");
        echo " - Dropped FK `{$cName}` to barangay.\n";
    }

    // 4. Update community_report table schema
    echo "4. Altering community_report table...\n";
    $pdo->exec("
        ALTER TABLE community_report 
        MODIFY COLUMN report_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN user_id bigint(20) UNSIGNED NOT NULL,
        MODIFY COLUMN reporter_name varchar(150) NOT NULL,
        MODIFY COLUMN contact_number varchar(20) NOT NULL,
        MODIFY COLUMN description text DEFAULT NULL,
        MODIFY COLUMN report_image varchar(255) DEFAULT NULL COMMENT 'Storage disk path, not a raw blob',
        MODIFY COLUMN ai_fire_label varchar(20) DEFAULT NULL,
        MODIFY COLUMN ai_fire_confidence decimal(5,4) DEFAULT NULL,
        MODIFY COLUMN ai_verified_at datetime DEFAULT NULL,
        MODIFY COLUMN latitude decimal(10,8) NOT NULL,
        MODIFY COLUMN longitude decimal(11,8) NOT NULL,
        MODIFY COLUMN barangay_id bigint(20) UNSIGNED DEFAULT NULL,
        MODIFY COLUMN location_accuracy_m int(11) DEFAULT NULL,
        MODIFY COLUMN device_latitude decimal(10,8) DEFAULT NULL,
        MODIFY COLUMN device_longitude decimal(11,8) DEFAULT NULL,
        MODIFY COLUMN status enum('pending','accepted','dispatched','resolved','invalid') NOT NULL DEFAULT 'pending',
        MODIFY COLUMN created_at timestamp NOT NULL DEFAULT current_timestamp(),
        MODIFY COLUMN updated_at timestamp NULL DEFAULT NULL,
        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    echo " - community_report table schema updated.\n";

    // 5. Update referencing child table columns to bigint(20) unsigned
    echo "5. Updating referencing columns in child tables to bigint(20) unsigned...\n";
    $pdo->exec("ALTER TABLE incident_record MODIFY COLUMN report_id bigint(20) UNSIGNED DEFAULT NULL");
    $pdo->exec("ALTER TABLE report_evidence MODIFY COLUMN report_id bigint(20) UNSIGNED NOT NULL");
    $pdo->exec("ALTER TABLE report_link MODIFY COLUMN main_report_id bigint(20) UNSIGNED NOT NULL");
    $pdo->exec("ALTER TABLE report_link MODIFY COLUMN related_report_id bigint(20) UNSIGNED NOT NULL");
    $pdo->exec("ALTER TABLE report_status_history MODIFY COLUMN report_id bigint(20) UNSIGNED NOT NULL");
    echo " - Referencing child columns updated to bigint(20) unsigned.\n";

    // 6. Re-add FKs referencing community_report(report_id) and barangay(barangay_id)
    echo "6. Re-linking foreign key constraints...\n";
    $pdo->exec("ALTER TABLE incident_record ADD CONSTRAINT incident_record_ibfk_1 FOREIGN KEY (report_id) REFERENCES community_report (report_id) ON DELETE SET NULL");
    $pdo->exec("ALTER TABLE report_evidence ADD CONSTRAINT report_evidence_ibfk_1 FOREIGN KEY (report_id) REFERENCES community_report (report_id) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE report_link ADD CONSTRAINT report_link_ibfk_1 FOREIGN KEY (main_report_id) REFERENCES community_report (report_id) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE report_link ADD CONSTRAINT report_link_ibfk_2 FOREIGN KEY (related_report_id) REFERENCES community_report (report_id) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE report_status_history ADD CONSTRAINT report_status_history_ibfk_1 FOREIGN KEY (report_id) REFERENCES community_report (report_id) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE community_report ADD CONSTRAINT community_report_ibfk_2 FOREIGN KEY (barangay_id) REFERENCES barangay (barangay_id) ON DELETE SET NULL");
    echo " - Foreign keys re-established successfully.\n";

    echo "\nUpdated SHOW CREATE TABLE community_report:\n";
    $show = $pdo->query("SHOW CREATE TABLE community_report")->fetch(PDO::FETCH_ASSOC);
    echo $show['Create Table'] . "\n\n";

    $count = $pdo->query("SELECT COUNT(*) FROM community_report")->fetchColumn();
    echo "Total reports preserved in community_report: " . $count . "\n";
    echo "\nMigration completed successfully!\n";
} catch (Exception $e) {
    echo "ERROR during migration: " . $e->getMessage() . "\n";
    exit(1);
}

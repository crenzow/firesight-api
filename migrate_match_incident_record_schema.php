<?php
require_once __DIR__ . '/config/database.php';

echo "=== FireSight: Match incident_record table structure ===\n";

try {
    $pdo = getDbConnection();

    // 1. Fill any NULL barangay_id using community_report.barangay_id (or 1) before applying NOT NULL
    echo "1. Populating NULL barangay_ids...\n";
    $pdo->exec("
        UPDATE incident_record i
        LEFT JOIN community_report r ON r.report_id = i.report_id
        SET i.barangay_id = COALESCE(r.barangay_id, 1)
        WHERE i.barangay_id IS NULL
    ");

    // 2. Drop existing foreign key constraints on incident_record temporarily
    echo "2. Dropping FK constraints on incident_record...\n";
    $stmt = $pdo->prepare("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = :dbname 
          AND TABLE_NAME = 'incident_record' 
          AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $stmt->execute(['dbname' => DB_NAME]);
    $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fks as $fk) {
        $cName = $fk['CONSTRAINT_NAME'];
        echo " - Dropping FK `{$cName}`...\n";
        $pdo->exec("ALTER TABLE incident_record DROP FOREIGN KEY `{$cName}`");
    }

    // 3. Check if incident_datetime column exists; if not, add it and copy from data_time
    echo "3. Migrating data_time to incident_datetime...\n";
    $chkTime = $pdo->prepare("
        SELECT COUNT(*) AS cnt 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = :dbname 
          AND TABLE_NAME = 'incident_record' 
          AND COLUMN_NAME = 'incident_datetime'
    ");
    $chkTime->execute(['dbname' => DB_NAME]);
    if (((int) $chkTime->fetch()['cnt']) === 0) {
        $pdo->exec("ALTER TABLE incident_record ADD COLUMN incident_datetime datetime DEFAULT NULL AFTER barangay_id");
        $pdo->exec("UPDATE incident_record SET incident_datetime = data_time WHERE data_time IS NOT NULL");
        echo " - Copied data_time values into incident_datetime.\n";
    }

    // 4. Check if alarm_level column exists; if not, add it and map from severity_level
    echo "4. Migrating severity_level to alarm_level...\n";
    $chkAlarm = $pdo->prepare("
        SELECT COUNT(*) AS cnt 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = :dbname 
          AND TABLE_NAME = 'incident_record' 
          AND COLUMN_NAME = 'alarm_level'
    ");
    $chkAlarm->execute(['dbname' => DB_NAME]);
    if (((int) $chkAlarm->fetch()['cnt']) === 0) {
        $pdo->exec("
            ALTER TABLE incident_record 
            ADD COLUMN alarm_level enum('1st_alarm','2nd_alarm','3rd_alarm','4th_alarm','5th_alarm','task_force_alpha','task_force_bravo','task_force_charlie','task_force_delta','task_force_echo','task_force_hotel','task_force_india','general_alarm') DEFAULT NULL 
            AFTER incident_type
        ");
        $pdo->exec("
            UPDATE incident_record SET alarm_level = CASE 
                WHEN severity_level = 'critical' THEN '4th_alarm'
                WHEN severity_level = 'high' THEN '3rd_alarm'
                WHEN severity_level = 'moderate' THEN '2nd_alarm'
                ELSE '1st_alarm'
            END
        ");
        echo " - Mapped severity_level into alarm_level.\n";
    }

    // 5. Drop old columns if they still exist
    $chkOldCols = $pdo->query("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
          AND TABLE_NAME = 'incident_record' 
          AND COLUMN_NAME IN ('data_time', 'severity_level')
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('data_time', $chkOldCols)) {
        $pdo->exec("ALTER TABLE incident_record DROP COLUMN data_time");
        echo " - Dropped old column `data_time`.\n";
    }
    if (in_array('severity_level', $chkOldCols)) {
        $pdo->exec("ALTER TABLE incident_record DROP COLUMN severity_level");
        echo " - Dropped old column `severity_level`.\n";
    }

    // 6. Alter columns to match exact requested structure
    echo "6. Applying column alterations and collation...\n";
    $pdo->exec("
        ALTER TABLE incident_record 
        MODIFY COLUMN incident_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN report_id bigint(20) UNSIGNED NOT NULL,
        MODIFY COLUMN barangay_id bigint(20) UNSIGNED NOT NULL,
        MODIFY COLUMN incident_datetime datetime DEFAULT NULL,
        MODIFY COLUMN incident_type enum('residential_fire','commercial_fire','vehicular_fire','storage_fire','rubbish_fire','others') DEFAULT NULL,
        MODIFY COLUMN alarm_level enum('1st_alarm','2nd_alarm','3rd_alarm','4th_alarm','5th_alarm','task_force_alpha','task_force_bravo','task_force_charlie','task_force_delta','task_force_echo','task_force_hotel','task_force_india','general_alarm') DEFAULT NULL,
        MODIFY COLUMN cause_of_fire varchar(150) DEFAULT NULL,
        MODIFY COLUMN casualties int(10) UNSIGNED DEFAULT NULL,
        MODIFY COLUMN notes text DEFAULT NULL,
        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    echo " - Columns updated to requested schema.\n";

    // 7. Re-add foreign key constraints
    echo "7. Re-establishing foreign keys to community_report and barangay...\n";
    $pdo->exec("ALTER TABLE incident_record ADD CONSTRAINT incident_record_ibfk_1 FOREIGN KEY (report_id) REFERENCES community_report (report_id) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE incident_record ADD CONSTRAINT incident_record_ibfk_2 FOREIGN KEY (barangay_id) REFERENCES barangay (barangay_id) ON DELETE CASCADE");
    echo " - Foreign keys re-established.\n";

    echo "\nUpdated SHOW CREATE TABLE incident_record:\n";
    $show = $pdo->query("SHOW CREATE TABLE incident_record")->fetch(PDO::FETCH_ASSOC);
    echo $show['Create Table'] . "\n\n";

    $count = $pdo->query("SELECT COUNT(*) FROM incident_record")->fetchColumn();
    echo "Total rows preserved in incident_record: " . $count . "\n";
    echo "\nMigration completed successfully!\n";
} catch (Exception $e) {
    echo "ERROR during migration: " . $e->getMessage() . "\n";
    exit(1);
}

<?php
require_once __DIR__ . '/config/database.php';

echo "=== FireSight: Update notification Table Schema ===\n";

try {
    $pdo = getDbConnection();

    // 1. Drop foreign key constraints on notification if any exist
    echo "1. Checking and dropping foreign key constraints...\n";
    $stmt = $pdo->prepare("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = :dbname 
          AND TABLE_NAME = 'notification' 
          AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $stmt->execute(['dbname' => DB_NAME]);
    $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fks as $fk) {
        $cName = $fk['CONSTRAINT_NAME'];
        echo " - Dropping FK `{$cName}`...\n";
        $pdo->exec("ALTER TABLE notification DROP FOREIGN KEY `{$cName}`");
    }

    // 2. Temporarily modify notification_type to varchar(50) to allow mapping existing enum values
    echo "2. Temporarily converting notification_type to varchar(50)...\n";
    $pdo->exec("ALTER TABLE notification MODIFY COLUMN notification_type varchar(50) NOT NULL DEFAULT 'system'");

    // 3. Map existing values: alert -> incident_alert, update -> status_update
    echo "3. Mapping existing notification_type values...\n";
    $pdo->exec("UPDATE notification SET notification_type = 'incident_alert' WHERE notification_type = 'alert'");
    $pdo->exec("UPDATE notification SET notification_type = 'status_update' WHERE notification_type = 'update'");
    $pdo->exec("UPDATE notification SET notification_type = 'system' WHERE notification_type NOT IN ('incident_alert', 'status_update', 'system', 'reminder')");

    // 4. Alter notification table to match the user's exact requested schema
    echo "4. Altering notification table structure...\n";
    $pdo->exec("
        ALTER TABLE `notification` 
        MODIFY COLUMN `notification_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `user_id` bigint(20) UNSIGNED NOT NULL,
        MODIFY COLUMN `title` varchar(150) NOT NULL,
        MODIFY COLUMN `message` text NOT NULL,
        MODIFY COLUMN `notification_type` enum('incident_alert','status_update','system','reminder') NOT NULL DEFAULT 'system',
        MODIFY COLUMN `is_read` tinyint(1) NOT NULL DEFAULT 0,
        MODIFY COLUMN `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    echo " - notification table successfully updated.\n\n";

    echo "Updated SHOW CREATE TABLE notification:\n";
    echo $pdo->query("SHOW CREATE TABLE notification")->fetch(PDO::FETCH_ASSOC)['Create Table'] . "\n\n";

    $count = $pdo->query("SELECT COUNT(*) FROM notification")->fetchColumn();
    echo "Total preserved rows in notification: {$count}\n";

    $typeBreakdown = $pdo->query("SELECT notification_type, COUNT(*) as cnt FROM notification GROUP BY notification_type")->fetchAll(PDO::FETCH_ASSOC);
    echo "Notification types breakdown:\n";
    foreach ($typeBreakdown as $tb) {
        echo " - {$tb['notification_type']}: {$tb['cnt']}\n";
    }

    echo "\nMigration finished successfully!\n";
} catch (Exception $e) {
    echo "ERROR during migration: " . $e->getMessage() . "\n";
    exit(1);
}

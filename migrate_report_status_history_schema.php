<?php
require_once __DIR__ . '/config/database.php';

echo "=== FireSight: Update report_status_history Table Schema ===\n";

try {
    $pdo = getDbConnection();

    // 1. Alter columns to match exact requested specification
    echo "1. Altering report_status_history table columns...\n";
    $pdo->exec("
        ALTER TABLE `report_status_history`
        MODIFY COLUMN `history_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `report_id` bigint(20) UNSIGNED NOT NULL,
        MODIFY COLUMN `status` enum('pending','accepted','dispatched','resolved','invalid') DEFAULT NULL,
        MODIFY COLUMN `notes` varchar(255) DEFAULT NULL,
        MODIFY COLUMN `changed_by` bigint(20) UNSIGNED DEFAULT NULL,
        MODIFY COLUMN `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    echo " - report_status_history table successfully updated.\n\n";

    echo "Updated SHOW CREATE TABLE report_status_history:\n";
    echo $pdo->query("SHOW CREATE TABLE report_status_history")->fetch(PDO::FETCH_ASSOC)['Create Table'] . "\n\n";

    $count = $pdo->query("SELECT COUNT(*) FROM report_status_history")->fetchColumn();
    echo "Total preserved rows in report_status_history: {$count}\n";

    $sample = $pdo->query("SELECT history_id, report_id, status, notes, changed_by, created_at FROM report_status_history LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "Sample rows:\n";
    foreach ($sample as $s) {
        echo " - ID: {$s['history_id']} | Report: {$s['report_id']} | Status: {$s['status']} | Notes: {$s['notes']} | ChangedBy: {$s['changed_by']} | At: {$s['created_at']}\n";
    }

    echo "\nMigration finished successfully!\n";
} catch (Exception $e) {
    echo "ERROR during migration: " . $e->getMessage() . "\n";
    exit(1);
}

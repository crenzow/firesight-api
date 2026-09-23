<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    // 1. Alter community_report
    $pdo->exec("ALTER TABLE community_report MODIFY COLUMN status ENUM('pending', 'accepted', 'dispatched', 'resolved', 'invalid') DEFAULT 'pending'");
    $pdo->exec("UPDATE community_report SET status = 'accepted' WHERE status = 'verified'");

    // 2. Alter report_status_history
    $pdo->exec("ALTER TABLE report_status_history MODIFY COLUMN status ENUM('pending', 'accepted', 'dispatched', 'resolved', 'invalid') DEFAULT NULL");
    $pdo->exec("UPDATE report_status_history SET status = 'accepted' WHERE status = 'verified'");

    echo "Migration successful!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>

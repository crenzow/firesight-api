<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();
    
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM community_report LIKE 'device_latitude'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE community_report 
                    ADD COLUMN device_latitude DECIMAL(10,8) DEFAULT NULL AFTER location_accuracy_m, 
                    ADD COLUMN device_longitude DECIMAL(11,8) DEFAULT NULL AFTER device_latitude");
        echo "Successfully added device_latitude and device_longitude to community_report.\n";
    } else {
        echo "Columns already exist.\n";
    }

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>

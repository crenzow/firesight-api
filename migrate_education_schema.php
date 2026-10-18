<?php
require_once __DIR__ . '/config/database.php';

echo "=== FireSight: Update fire_education_content Schema ===\n";

try {
    $pdo = getDbConnection();

    echo "Altering fire_education_content columns...\n";
    
    // Modify content_id to int(11) AUTO_INCREMENT
    $pdo->exec("ALTER TABLE fire_education_content MODIFY COLUMN content_id int(11) NOT NULL AUTO_INCREMENT");
    echo " - content_id set to int(11) NOT NULL AUTO_INCREMENT\n";

    // Modify category to enum with default 'prevention'
    $pdo->exec("ALTER TABLE fire_education_content MODIFY COLUMN category enum('prevention','emergency_response','awareness') DEFAULT 'prevention'");
    echo " - category set to enum('prevention','emergency_response','awareness') DEFAULT 'prevention'\n";

    // Modify is_featured to tinyint(1) DEFAULT 0
    $pdo->exec("ALTER TABLE fire_education_content MODIFY COLUMN is_featured tinyint(1) DEFAULT 0");
    echo " - is_featured set to tinyint(1) DEFAULT 0\n";

    // Modify created_at to datetime DEFAULT current_timestamp()
    $pdo->exec("ALTER TABLE fire_education_content MODIFY COLUMN created_at datetime DEFAULT current_timestamp()");
    echo " - created_at set to datetime DEFAULT current_timestamp()\n";

    // Ensure read_minutes column is dropped if present
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = :dbname 
          AND TABLE_NAME = 'fire_education_content' 
          AND COLUMN_NAME = 'read_minutes'
    ");
    $stmt->execute(['dbname' => DB_NAME]);
    if (((int) $stmt->fetch()['cnt']) > 0) {
        $pdo->exec("ALTER TABLE fire_education_content DROP COLUMN read_minutes");
        echo " - read_minutes dropped\n";
    } else {
        echo " - read_minutes is confirmed absent\n";
    }

    echo "\nUpdated CREATE TABLE:\n";
    $stmt = $pdo->query("SHOW CREATE TABLE fire_education_content");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row['Create Table'] . "\n";

    echo "\nVerification of row count:\n";
    $count = $pdo->query("SELECT COUNT(*) FROM fire_education_content")->fetchColumn();
    echo "Total articles in table: " . $count . "\n";

    echo "\nSchema update completed successfully!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

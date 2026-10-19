<?php
require_once __DIR__ . '/config/database.php';

echo "=== FireSight: Update risk_assessment Table Schema ===\n";

try {
    $pdo = getDbConnection();

    // 1. Check for any NULLs in date
    echo "1. Populating any NULL dates or scores...\n";
    $pdo->exec("UPDATE risk_assessment SET `date` = CURDATE() WHERE `date` IS NULL");
    $pdo->exec("UPDATE risk_assessment SET `prediction_score` = 0 WHERE `prediction_score` IS NULL");

    // 2. Normalize prediction_score to decimal(5,4) range (0.0000 - 1.0000)
    // If scores are stored as percentages (e.g. 78.40), scale them to 0.7840
    echo "2. Normalizing prediction_score to 0.0000 - 1.0000 range...\n";
    $pdo->exec("UPDATE risk_assessment SET prediction_score = prediction_score / 100 WHERE prediction_score > 1.0");

    // 3. Drop FK temporarily if needed, alter columns
    echo "3. Altering risk_assessment table columns...\n";
    $pdo->exec("
        ALTER TABLE `risk_assessment`
        MODIFY COLUMN `risk_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `barangay_id` bigint(20) UNSIGNED NOT NULL,
        MODIFY COLUMN `date` date NOT NULL,
        MODIFY COLUMN `prediction_score` decimal(5,4) NOT NULL,
        MODIFY COLUMN `risk_level` enum('mild','moderate','high') NOT NULL,
        MODIFY COLUMN `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    echo " - risk_assessment table successfully updated.\n\n";

    echo "Updated SHOW CREATE TABLE risk_assessment:\n";
    echo $pdo->query("SHOW CREATE TABLE risk_assessment")->fetch(PDO::FETCH_ASSOC)['Create Table'] . "\n\n";

    $count = $pdo->query("SELECT COUNT(*) FROM risk_assessment")->fetchColumn();
    echo "Total preserved rows in risk_assessment: {$count}\n";

    $sample = $pdo->query("SELECT risk_id, barangay_id, `date`, prediction_score, risk_level, generated_at FROM risk_assessment LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "Sample rows:\n";
    foreach ($sample as $s) {
        echo " - ID: {$s['risk_id']} | Brgy: {$s['barangay_id']} | Date: {$s['date']} | Score: {$s['prediction_score']} | Level: {$s['risk_level']} | Gen: {$s['generated_at']}\n";
    }

    echo "\nMigration finished successfully!\n";
} catch (Exception $e) {
    echo "ERROR during migration: " . $e->getMessage() . "\n";
    exit(1);
}

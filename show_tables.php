<?php
require_once __DIR__ . '/config/database.php';
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SHOW CREATE TABLE community_report");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row['Create Table'] . "\n";
} catch (Exception $e) {
    echo "Failed: " . $e->getMessage();
}
?>

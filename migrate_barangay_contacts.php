<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();
    
    // Create the barangay_contact table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `barangay_contact` (
          `contact_id` int(11) NOT NULL AUTO_INCREMENT,
          `barangay_id` int(11) NOT NULL,
          `name` varchar(100) NOT NULL,
          `role` varchar(50) NOT NULL,
          `phone_number` varchar(20) NOT NULL,
          PRIMARY KEY (`contact_id`),
          KEY `barangay_id` (`barangay_id`),
          CONSTRAINT `barangay_contact_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangay` (`barangay_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    echo "Table 'barangay_contact' created successfully.\n";

    // Insert dummy contacts for a few barangays just for testing
    // E.g., Barangay 1 (Poblacion), 2 (Kapito), etc.
    // Fetch all barangays to populate
    $stmt = $pdo->query("SELECT barangay_id, barangay_name FROM barangay LIMIT 5");
    $barangays = $stmt->fetchAll();

    $insertStmt = $pdo->prepare("
        INSERT INTO barangay_contact (barangay_id, name, role, phone_number)
        VALUES (:barangay_id, :name, :role, :phone)
    ");

    foreach ($barangays as $b) {
        // Dummy Captain
        $insertStmt->execute([
            'barangay_id' => $b['barangay_id'],
            'name' => 'Hon. Captain of ' . $b['barangay_name'],
            'role' => 'Barangay Captain',
            'phone' => '09123456789'
        ]);
        // Dummy Councilor
        $insertStmt->execute([
            'barangay_id' => $b['barangay_id'],
            'name' => 'Kagawad ' . $b['barangay_name'],
            'role' => 'Barangay Councilor',
            'phone' => '09987654321'
        ]);
    }

    echo "Sample contacts inserted successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

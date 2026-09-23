<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();
    
    // Clear existing contacts
    $pdo->exec("TRUNCATE TABLE `barangay_contact`");
    echo "Cleared existing barangay contacts.\n";

    // Fetch all barangays
    $stmt = $pdo->query("SELECT barangay_id, barangay_name FROM barangay");
    $barangays = $stmt->fetchAll();

    $insertStmt = $pdo->prepare("
        INSERT INTO barangay_contact (barangay_id, name, role, phone_number)
        VALUES (:barangay_id, :name, :role, :phone)
    ");

    $count = 0;
    foreach ($barangays as $b) {
        // Captain
        $insertStmt->execute([
            'barangay_id' => $b['barangay_id'],
            'name' => 'Hon. Captain ' . $b['barangay_name'],
            'role' => 'Barangay Captain',
            'phone' => '09' . mt_rand(100000000, 999999999) // Generate random PH mobile number
        ]);
        
        // Councilor
        $insertStmt->execute([
            'barangay_id' => $b['barangay_id'],
            'name' => 'Kagawad ' . $b['barangay_name'],
            'role' => 'Barangay Councilor',
            'phone' => '09' . mt_rand(100000000, 999999999)
        ]);
        
        $count++;
    }

    echo "Successfully seeded contacts for {$count} barangays.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

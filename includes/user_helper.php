<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Builds the exact user object shape expected by the frontend's AppUser
 * type (services/api/types.ts) — including the joined barangay/address info.
 * Used by auth/login.php, auth/register.php, and profile/get.php so all
 * three endpoints stay in sync.
 */
function getUserWithAddress(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM user WHERE user_id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return null;
    }

    $addressStmt = $pdo->prepare(
        'SELECT ra.house_no_street, ra.barangay_id, ra.municipality, ra.province, b.barangay_name
         FROM resident_address ra
         LEFT JOIN barangay b ON b.barangay_id = ra.barangay_id
         WHERE ra.user_id = :id
         LIMIT 1'
    );
    $addressStmt->execute(['id' => $userId]);
    $address = $addressStmt->fetch();

    // For personnel users, fetch BFP-specific details (rank, station, employee number)
    $personnelDetails = null;
    if ($user['role'] === 'personnel') {
        $pdStmt = $pdo->prepare(
            'SELECT rank, station_assigned, employee_number
             FROM bfp_personnel_details WHERE user_id = :id LIMIT 1'
        );
        $pdStmt->execute(['id' => $userId]);
        $row = $pdStmt->fetch();
        if ($row) {
            $personnelDetails = [
                'rank' => $row['rank'],
                'station_assigned' => $row['station_assigned'],
                'employee_number' => $row['employee_number'],
            ];
        }
    }

    return [
        'user_id' => (int) $user['user_id'],
        'role' => $user['role'],
        'first_name' => $user['first_name'],
        'middle_name' => $user['middle_name'],
        'last_name' => $user['last_name'],
        'suffix' => $user['suffix'],
        'contact_number' => $user['contact_number'],
        'email' => $user['email'],
        'profile_image' => $user['profile_image'],
        'is_verified' => (bool) $user['is_verified'],
        'address' => $address ? [
            'house_no_street' => $address['house_no_street'],
            'barangay_id' => $address['barangay_id'] !== null ? (int) $address['barangay_id'] : null,
            'barangay_name' => $address['barangay_name'],
            'municipality' => $address['municipality'],
            'province' => $address['province'],
        ] : null,
        'personnel_details' => $personnelDetails,
    ];
}
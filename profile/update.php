<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/user_helper.php';

/**
 * PUT /profile/update.php  (requires auth)
 * Body (all optional, matches UpdateProfilePayload):
 *   first_name, middle_name, last_name, suffix, contact_number,
 *   house_no_street, barangay_id
 * Returns the updated AppUser.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    sendError('Method not allowed.', 405);
}

$user = requireAuth();
$body = getJsonBody();
$pdo = getDbConnection();

if (isset($body['contact_number']) && $body['contact_number'] !== '' && !isValidPHMobile($body['contact_number'])) {
    sendError('Please enter a valid Philippine mobile number.', 422);
}

if (isset($body['password']) && $body['password'] !== '') {
    if (strlen((string) $body['password']) < 8) {
        sendError('Password must be at least 8 characters.', 422);
    }
}

$userFields = ['first_name', 'middle_name', 'last_name', 'suffix', 'contact_number'];
$userUpdates = [];
$userParams = ['user_id' => $user['user_id']];

foreach ($userFields as $field) {
    if (array_key_exists($field, $body)) {
        $userUpdates[] = "$field = :$field";
        $userParams[$field] = sanitizeText($body[$field]) ?: null;
    }
}

if (!empty($body['password'])) {
    $userUpdates[] = 'password = :password';
    $userParams['password'] = password_hash((string) $body['password'], PASSWORD_DEFAULT);
}

try {
    $pdo->beginTransaction();

    if (!empty($userUpdates)) {
        $sql = 'UPDATE user SET ' . implode(', ', $userUpdates) . ' WHERE user_id = :user_id';
        $pdo->prepare($sql)->execute($userParams);
    }

    if (array_key_exists('house_no_street', $body) || array_key_exists('barangay_id', $body)) {
        $existing = $pdo->prepare('SELECT address_id FROM resident_address WHERE user_id = :user_id LIMIT 1');
        $existing->execute(['user_id' => $user['user_id']]);
        $addressRow = $existing->fetch();

        if ($addressRow) {
            $addressUpdates = [];
            $addressParams = ['address_id' => $addressRow['address_id']];
            if (array_key_exists('house_no_street', $body)) {
                $addressUpdates[] = 'house_no_street = :house_no_street';
                $addressParams['house_no_street'] = sanitizeText($body['house_no_street']) ?: null;
            }
            if (array_key_exists('barangay_id', $body)) {
                $addressUpdates[] = 'barangay_id = :barangay_id';
                $addressParams['barangay_id'] = (int) $body['barangay_id'];
            }
            if (!empty($addressUpdates)) {
                $sql = 'UPDATE resident_address SET ' . implode(', ', $addressUpdates) . ' WHERE address_id = :address_id';
                $pdo->prepare($sql)->execute($addressParams);
            }
        } else {
            // Resident had no address row yet (shouldn't normally happen post-registration,
            // but handled defensively) — create one.
            $insert = $pdo->prepare(
                'INSERT INTO resident_address (user_id, house_no_street, barangay_id, municipality, province)
                 VALUES (:user_id, :house_no_street, :barangay_id, \'Lian\', \'Batangas\')'
            );
            $insert->execute([
                'user_id' => $user['user_id'],
                'house_no_street' => sanitizeText($body['house_no_street'] ?? '') ?: null,
                'barangay_id' => (int) ($body['barangay_id'] ?? 0) ?: null,
            ]);
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    sendError('Unable to save changes right now. Please try again.', 500);
}

sendSuccess(getUserWithAddress($pdo, (int) $user['user_id']));
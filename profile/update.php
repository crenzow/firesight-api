<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/user_helper.php';

/**
 * PUT or POST /profile/update.php  (requires auth)
 * Supports JSON or multipart/form-data.
 * Body (all optional, matches UpdateProfilePayload):
 *   first_name, middle_name, last_name, suffix, contact_number,
 *   house_no_street, barangay_id, password
 * Files:
 *   profile_image (or photo)
 * Returns the updated AppUser.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed.', 405);
}

$user = requireAuth();
$pdo = getDbConnection();

// Merge JSON body and $_POST so both Content-Type headers are supported
$json = getJsonBody() ?: [];
$body = array_merge($json, $_POST);

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

// --- Handle Profile Photo Upload if provided ---
$uploadedFile = $_FILES['profile_image'] ?? $_FILES['photo'] ?? null;
if ($uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK) {
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
    $maxSizeBytes = 10 * 1024 * 1024; // 10MB

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        sendError('Unsupported image format. Please upload JPG, PNG, or WEBP.', 422);
    }
    if ($uploadedFile['size'] > $maxSizeBytes) {
        sendError('Image is too large. Maximum size is 10MB.', 422);
    }

    $extensionMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
    ];
    $extension = $extensionMap[$mimeType] ?? 'jpg';
    $fileName = 'profile_' . $user['user_id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

    $uploadDir = __DIR__ . '/../uploads/profiles/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!move_uploaded_file($uploadedFile['tmp_name'], $uploadDir . $fileName)) {
        sendError('Unable to save profile photo. Please try again.', 500);
    }

    $userUpdates[] = 'profile_image = :profile_image';
    $userParams['profile_image'] = 'uploads/profiles/' . $fileName;
} elseif (!empty($body['remove_photo']) && ($body['remove_photo'] === true || $body['remove_photo'] === 'true' || $body['remove_photo'] === '1' || $body['remove_photo'] === 1)) {
    $userUpdates[] = 'profile_image = NULL';
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
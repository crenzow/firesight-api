<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/user_helper.php';

/**
 * POST /auth/register.php
 * Body matches RegisterPayload (services/api/types.ts):
 *   first_name, middle_name?, last_name, suffix?, mobile_number,
 *   house_no_street?, barangay_id, municipality, province, email, password
 * Returns AuthResponse: { token, user }
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed.', 405);
}

$body = getJsonBody();
requireFields($body, ['first_name', 'last_name', 'mobile_number', 'barangay_id', 'municipality', 'province', 'email', 'password']);

$firstName = sanitizeText($body['first_name']);
$middleName = sanitizeText($body['middle_name'] ?? '');
$lastName = sanitizeText($body['last_name']);
$suffix = sanitizeText($body['suffix'] ?? '');
$mobileNumber = sanitizeText($body['mobile_number']);
$houseNoStreet = sanitizeText($body['house_no_street'] ?? '');
$barangayId = (int) $body['barangay_id'];
$municipality = sanitizeText($body['municipality']);
$province = sanitizeText($body['province']);
$email = strtolower(trim($body['email']));
$password = (string) $body['password'];

if (!isValidEmail($email)) {
    sendError('Please enter a valid email address.', 422);
}
if (!isValidPHMobile($mobileNumber)) {
    sendError('Please enter a valid Philippine mobile number.', 422);
}
if (!isStrongEnoughPassword($password)) {
    sendError('Password must be at least 8 characters.', 422);
}

$pdo = getDbConnection();

// Reject duplicate emails up front with a friendly message instead of a raw
// SQL unique-constraint error further down.
$existing = $pdo->prepare('SELECT user_id FROM user WHERE email = :email LIMIT 1');
$existing->execute(['email' => $email]);
if ($existing->fetch()) {
    sendError('An account with this email already exists. Try signing in instead.', 409);
}

$barangayCheck = $pdo->prepare('SELECT barangay_id FROM barangay WHERE barangay_id = :id LIMIT 1');
$barangayCheck->execute(['id' => $barangayId]);
if (!$barangayCheck->fetch()) {
    sendError('Please select a valid barangay.', 422);
}

$userId = null;
$token = null;

try {
    $pdo->beginTransaction();

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    $insertUser = $pdo->prepare(
        'INSERT INTO user (role, first_name, middle_name, last_name, suffix, contact_number, email, password, is_verified, is_active)
         VALUES (\'resident\', :first_name, :middle_name, :last_name, :suffix, :mobile, :email, :password, 0, 1)'
    );
    $insertUser->execute([
        'first_name' => $firstName,
        'middle_name' => $middleName ?: null,
        'last_name' => $lastName,
        'suffix' => $suffix ?: null,
        'mobile' => $mobileNumber,
        'email' => $email,
        'password' => $passwordHash,
    ]);
    $userId = (int) $pdo->lastInsertId();

    $insertAddress = $pdo->prepare(
        'INSERT INTO resident_address (user_id, house_no_street, barangay_id, municipality, province)
         VALUES (:user_id, :house_no_street, :barangay_id, :municipality, :province)'
    );
    $insertAddress->execute([
        'user_id' => $userId,
        'house_no_street' => $houseNoStreet ?: null,
        'barangay_id' => $barangayId,
        'municipality' => $municipality,
        'province' => $province,
    ]);

    $token = generateSecureToken();
    $insertToken = $pdo->prepare(
        'INSERT INTO auth_token (user_id, token, device_info, expires_at)
         VALUES (:user_id, :token, :device_info, DATE_ADD(NOW(), INTERVAL 90 DAY))'
    );
    $insertToken->execute([
        'user_id' => $userId,
        'token' => $token,
        'device_info' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    ]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    sendError('Unable to create your account right now. Please try again.', 500);
}

sendSuccess([
    'token' => $token,
    'user' => getUserWithAddress($pdo, $userId),
], 201);
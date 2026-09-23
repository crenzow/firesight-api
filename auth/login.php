<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/user_helper.php';

/**
 * POST /auth/login.php
 * Body: { email, password }
 * Returns AuthResponse: { token, user }
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed.', 405);
}

$body = getJsonBody();
requireFields($body, ['email', 'password']);

$loginInput = trim($body['email'] ?? $body['login'] ?? '');
$password = (string) ($body['password'] ?? '');

if (empty($loginInput)) {
    sendError('Please enter your email address or mobile number.', 422);
}

$pdo = getDbConnection();

// Check if input is an email address (contains '@')
if (strpos($loginInput, '@') !== false) {
    $stmt = $pdo->prepare('SELECT * FROM user WHERE LOWER(email) = :email LIMIT 1');
    $stmt->execute(['email' => strtolower($loginInput)]);
    $user = $stmt->fetch();
} else {
    // Mobile number lookup
    $digitsOnly = preg_replace('/\D/', '', $loginInput);
    if (strlen($digitsOnly) >= 7) {
        $lastDigits = substr($digitsOnly, -9);
        $stmt = $pdo->prepare(
            'SELECT * FROM user 
             WHERE contact_number = :login_raw 
                OR (contact_number IS NOT NULL AND REPLACE(REPLACE(contact_number, "-", ""), " ", "") LIKE :login_digits)
             LIMIT 1'
        );
        $stmt->execute([
            'login_raw' => $loginInput,
            'login_digits' => '%' . $lastDigits,
        ]);
        $user = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare('SELECT * FROM user WHERE contact_number = :login_raw LIMIT 1');
        $stmt->execute(['login_raw' => $loginInput]);
        $user = $stmt->fetch();
    }
}

// Deliberately generic message for both "no such user" and "wrong password"
if (!$user || !$user['password'] || !password_verify($password, $user['password'])) {
    sendError('Incorrect credentials. Please check your email or mobile number.', 401);
}

if ((int) $user['is_active'] === 0) {
    sendError('This account has been deactivated. Please contact BFP Lian.', 403);
}

$token = generateSecureToken();
$insertToken = $pdo->prepare(
    'INSERT INTO auth_token (user_id, token, device_info, expires_at)
     VALUES (:user_id, :token, :device_info, DATE_ADD(NOW(), INTERVAL 90 DAY))'
);
$insertToken->execute([
    'user_id' => $user['user_id'],
    'token' => $token,
    'device_info' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
]);

sendSuccess([
    'token' => $token,
    'user' => getUserWithAddress($pdo, (int) $user['user_id']),
]);
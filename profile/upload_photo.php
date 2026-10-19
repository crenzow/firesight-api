<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/user_helper.php';

/**
 * POST /profile/upload_photo.php  (requires auth)
 * Multipart body:
 *   profile_image (file)
 * Returns the updated AppUser.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed.', 405);
}

$user = requireAuth();
$pdo = getDbConnection();

$file = $_FILES['profile_image'] ?? $_FILES['photo'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    sendError('Please provide an image file to upload.', 422);
}

$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
$maxSizeBytes = 10 * 1024 * 1024; // 10MB

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);

if (!in_array($mimeType, $allowedMimeTypes, true)) {
    sendError('Unsupported image format. Please upload JPG, PNG, or WEBP.', 422);
}
if ($file['size'] > $maxSizeBytes) {
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

if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
    sendError('Unable to save profile photo. Please try again.', 500);
}

$relativePath = 'uploads/profiles/' . $fileName;

$stmt = $pdo->prepare('UPDATE users SET profile_image = :image WHERE user_id = :user_id');
$stmt->execute([
    'image' => $relativePath,
    'user_id' => $user['user_id'],
]);

sendSuccess(getUserWithAddress($pdo, (int) $user['user_id']));

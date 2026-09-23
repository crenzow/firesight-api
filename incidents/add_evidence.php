<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

$user = requireAuth();
if ($user['role'] !== 'personnel') {
    sendError('Unauthorized.', 403);
}

$reportId = $_POST['report_id'] ?? null;
if (!$reportId) {
    sendError('Missing report_id.', 400);
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    sendError('A photo is required.', 422);
}

$file = $_FILES['photo'];
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/heic', 'image/heif'];
$maxSizeBytes = 10 * 1024 * 1024; // 10MB

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);

if (!in_array($mimeType, $allowedMimeTypes, true)) {
    sendError('Unsupported image type. Please use JPG, PNG, or HEIC.', 422);
}
if ($file['size'] > $maxSizeBytes) {
    sendError('Image is too large. Maximum size is 10MB.', 422);
}

$extensionMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/heic' => 'heic', 'image/heif' => 'heif'];
$extension = $extensionMap[$mimeType] ?? 'jpg';
$fileName = 'evidence_' . $reportId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

$uploadDir = __DIR__ . '/../uploads/evidence/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
    sendError('Unable to save the uploaded photo.', 500);
}

$relativePath = 'uploads/evidence/' . $fileName;

$pdo = getDbConnection();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO report_evidence (report_id, image_path, uploaded_by) VALUES (:report_id, :image_path, :uploaded_by)'
    );
    $stmt->execute([
        'report_id' => $reportId,
        'image_path' => $relativePath,
        'uploaded_by' => $user['user_id']
    ]);
    
    sendSuccess([
        'message' => 'Evidence added successfully.',
        'image_path' => $relativePath
    ]);
} catch (PDOException $e) {
    @unlink($uploadDir . $fileName);
    sendError('Database error: ' . $e->getMessage(), 500);
}

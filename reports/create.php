<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/notification_helper.php';
require_once __DIR__ . '/../includes/report_helper.php';
require_once __DIR__ . '/../includes/geo_helper.php';
require_once __DIR__ . '/../includes/fire_verification_helper.php';

/**
 * POST /reports/create.php  (requires Authorization: Bearer <token>)
 * multipart/form-data body (matches reportService.create in the frontend):
 *   description, latitude, longitude, location_accuracy_m?, barangay_id,
 *   report_image (file)
 * Returns the created CommunityReport.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed.', 405);
}

$user = requireAuth();

$description = trim($_POST['description'] ?? '');
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;
$deviceLatitude = $_POST['device_latitude'] ?? null;
$deviceLongitude = $_POST['device_longitude'] ?? null;
$accuracy = $_POST['location_accuracy_m'] ?? null;
$barangayId = $_POST['barangay_id'] ?? null;

if ($latitude === null || $longitude === null) {
    sendError('Missing required field(s): latitude, longitude', 422);
}

$pdo = getDbConnection();

// --- Point-in-Polygon Check ---
// The exact location overrides the resident's registered barangay.
// If outside Lian (null), we leave it as null so there are no local officials.
$pipBarangayId = findBarangayByLocation($latitude, $longitude, $pdo);
$barangayId = $pipBarangayId;

if ($barangayId !== null) {
    $barangayCheck = $pdo->prepare('SELECT barangay_id FROM barangay WHERE barangay_id = :id LIMIT 1');
    $barangayCheck->execute(['id' => (int) $barangayId]);
    if (!$barangayCheck->fetch()) {
        $barangayId = null;
    }
}

// --- Handle the uploaded photo ---
if (!isset($_FILES['report_image']) || $_FILES['report_image']['error'] !== UPLOAD_ERR_OK) {
    sendError('A photo is required to submit a fire report.', 422);
}

$file = $_FILES['report_image'];
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/heic', 'image/heif'];
$maxSizeBytes = 10 * 1024 * 1024; // 10MB

// Note: finfo_close() is intentionally omitted — it's deprecated as of
// PHP 8.5 because the finfo resource/object is now cleaned up
// automatically once it goes out of scope, same as other modern PHP
// resource-to-object conversions (e.g. mysqli).
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
$fileName = 'report_' . $user['user_id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

$uploadDir = __DIR__ . '/../uploads/reports/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
    sendError('Unable to save the uploaded photo. Please try again.', 500);
}

$relativePath = 'uploads/reports/' . $fileName;
$absolutePath = $uploadDir . $fileName;

// --- Fire image verification (CNN via inference service) ---
// Non-blocking: if the service is unreachable, $aiResult['label'] stays
// null and the report is still saved normally, just without an AI flag.
$aiResult = verifyFireImage($absolutePath);
$aiLabel = $aiResult['label'];           // 'fire' | 'non_fire' | null
$aiConfidence = $aiResult['confidence']; // float | null
$aiVerifiedAt = $aiLabel !== null ? date('Y-m-d H:i:s') : null;

try {
    $pdo->beginTransaction();

    $reporterName = trim($user['first_name'] . ' ' . $user['last_name']);

    $insert = $pdo->prepare(
        'INSERT INTO community_report
            (user_id, reporter_name, contact_number, description, report_image, latitude, longitude, device_latitude, device_longitude, location_accuracy_m, barangay_id, status, ai_fire_label, ai_fire_confidence, ai_verified_at)
         VALUES
            (:user_id, :reporter_name, :contact_number, :description, :report_image, :latitude, :longitude, :device_latitude, :device_longitude, :accuracy, :barangay_id, \'pending\', :ai_fire_label, :ai_fire_confidence, :ai_verified_at)'
    );
    $insert->execute([
        'user_id' => $user['user_id'],
        'reporter_name' => $reporterName,
        'contact_number' => $user['contact_number'],
        'description' => $description,
        'report_image' => $relativePath,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'device_latitude' => $deviceLatitude,
        'device_longitude' => $deviceLongitude,
        'accuracy' => $accuracy,
        'barangay_id' => $barangayId === null ? null : (int) $barangayId,
        'ai_fire_label' => $aiLabel,
        'ai_fire_confidence' => $aiConfidence,
        'ai_verified_at' => $aiVerifiedAt,
    ]);
    $reportId = (int) $pdo->lastInsertId();

    $insertHistory = $pdo->prepare(
        'INSERT INTO report_status_history (report_id, status, notes, changed_by)
         VALUES (:report_id, \'pending\', \'Report submitted by resident.\', NULL)'
    );
    $insertHistory->execute(['report_id' => $reportId]);

    // Notify the resident and every active BFP personnel account.
    $insertNotification = $pdo->prepare(
        'INSERT INTO notification (user_id, title, message, notification_type, is_read)
         VALUES (:user_id, \'Report Submitted\', \'Your fire report has been received and is under review by BFP Lian.\', \'update\', 0)'
    );
    $insertNotification->execute(['user_id' => $user['user_id']]);

    notifyPersonnelOfNewReport($pdo, $reportId);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    @unlink($uploadDir . $fileName);
    error_log('[reports/create.php] Transaction failed: ' . $e->getMessage());
    sendError('Unable to submit your report right now. Please try again.', 500);
}

$stmt = $pdo->prepare(
    'SELECT r.*, b.barangay_name FROM community_report r
     LEFT JOIN barangay b ON b.barangay_id = r.barangay_id
     WHERE r.report_id = :id'
);
$stmt->execute(['id' => $reportId]);
$row = $stmt->fetch();

sendSuccess(formatReportRow($row), 201);
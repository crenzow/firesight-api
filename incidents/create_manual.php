<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/incident_helper.php';

$user = requireAuth();
if ($user['role'] !== 'personnel') {
    sendError('Unauthorized.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed.', 405);
}

// --- Validate required fields from multipart form-data ---
$barangayId  = $_POST['barangay_id']    ?? null;
$latitude    = $_POST['latitude']       ?? null;
$longitude   = $_POST['longitude']      ?? null;
$description = trim($_POST['description'] ?? '');
$alarmLevel  = $_POST['alarm_level']    ?? null;
$validAlarmLevels = [
    '1st_alarm', '2nd_alarm', '3rd_alarm', '4th_alarm', '5th_alarm',
    'task_force_alpha', 'task_force_bravo', 'task_force_charlie',
    'task_force_delta', 'task_force_echo', 'task_force_hotel',
    'task_force_india', 'general_alarm'
];
if ($alarmLevel && !in_array($alarmLevel, $validAlarmLevels, true)) {
    $alarmLevel = null;
}
$dataTime    = $_POST['incident_datetime'] ?? $_POST['data_time'] ?? null;
$causeOfFire = trim($_POST['cause_of_fire'] ?? '') ?: null;
$casualties  = isset($_POST['casualties']) ? (int) $_POST['casualties'] : 0;
$notes       = trim($_POST['notes'] ?? '') ?: null;

if (!$barangayId || !$latitude || !$longitude || !$description || !$incidentType) {
    sendError('barangay_id, latitude, longitude, description, and incident_type are required.', 422);
}

if (!in_array($incidentType, ['residential_fire', 'commercial_fire', 'vehicular_fire', 'storage_fire', 'rubbish_fire', 'others'], true)) {
    sendError('Invalid incident_type.', 422);
}

// Normalise the datetime from ISO-8601 (sent by JS) to MySQL DATETIME
$dataTimeMysql = null;
if ($dataTime) {
    try {
        $dt = new DateTime($dataTime);
        $dataTimeMysql = $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        $dataTimeMysql = null;
    }
}

// --- Handle optional photo upload ---
$reportImagePath = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['photo'];
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/heic', 'image/heif'];
    $maxSizeBytes = 10 * 1024 * 1024; // 10 MB

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        sendError('Unsupported image type. Use JPG, PNG, or HEIC.', 422);
    }
    if ($file['size'] > $maxSizeBytes) {
        sendError('Photo exceeds the 10 MB limit.', 422);
    }

    $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/heic' => 'heic', 'image/heif' => 'heif'];
    $ext      = $extMap[$mimeType] ?? 'jpg';
    $fileName = 'manual_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $uploadDir = __DIR__ . '/../uploads/reports/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
        $reportImagePath = 'uploads/reports/' . $fileName;
    }
}

$pdo = getDbConnection();

try {
    $pdo->beginTransaction();

    // 1. Insert into community_report (personnel-created, no resident user_id)
    $stmt = $pdo->prepare("
        INSERT INTO community_report
            (user_id, reporter_name, description, report_image, latitude, longitude, barangay_id, status)
        VALUES
            (:user_id, :reporter_name, :description, :report_image, :latitude, :longitude, :barangay_id, 'accepted')
    ");
    $stmt->execute([
        'user_id'       => $user['user_id'],
        'reporter_name' => $user['first_name'] . ' ' . $user['last_name'] . ' (BFP)',
        'description'   => $description,
        'report_image'  => $reportImagePath,
        'latitude'      => $latitude,
        'longitude'     => $longitude,
        'barangay_id'   => $barangayId,
    ]);
    $reportId = (int) $pdo->lastInsertId();

    // 2. Insert into incident_record (pre-verified since it's BFP-entered)
    $stmt = $pdo->prepare("
        INSERT INTO incident_record
            (report_id, barangay_id, incident_type, alarm_level, cause_of_fire, casualties, notes, incident_datetime)
        VALUES
            (:report_id, :barangay_id, :incident_type, :alarm_level, :cause_of_fire, :casualties, :notes, :incident_datetime)
    ");
    $stmt->execute([
        'report_id'        => $reportId,
        'barangay_id'      => $barangayId,
        'incident_type'    => $incidentType,
        'alarm_level'      => $alarmLevel,
        'cause_of_fire'    => $causeOfFire,
        'casualties'       => $casualties,
        'notes'            => $notes,
        'incident_datetime'=> $dataTimeMysql,
    ]);

    // 3. Log the initial status in report_status_history
    $stmt = $pdo->prepare("
        INSERT INTO report_status_history (report_id, status, notes, changed_by)
        VALUES (:report_id, 'accepted', 'Manually entered by BFP personnel.', :changed_by)
    ");
    $stmt->execute([
        'report_id'  => $reportId,
        'changed_by' => $user['user_id'],
    ]);

    $pdo->commit();

    $incident = fetchIncident($pdo, $reportId);
    sendSuccess($incident, 201);

} catch (PDOException $e) {
    $pdo->rollBack();
    // Clean up uploaded file if DB insert failed
    if ($reportImagePath && file_exists(__DIR__ . '/../' . $reportImagePath)) {
        @unlink(__DIR__ . '/../' . $reportImagePath);
    }
    sendError('Database error: ' . $e->getMessage(), 500);
}

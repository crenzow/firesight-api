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

$pdo = getDbConnection();

$status = $_GET['status'] ?? null;
$barangay_id = $_GET['barangay_id'] ?? null;
$year = $_GET['year'] ?? null;
$search = $_GET['search'] ?? null;

$sql = "
    SELECT 
        r.report_id,
        r.reporter_name,
        r.contact_number,
        r.description,
        r.report_image,
        r.latitude,
        r.longitude,
        r.location_accuracy_m,
        r.status,
        r.created_at,
        r.ai_fire_label,
        r.ai_fire_confidence,
        COALESCE(i.barangay_id, r.barangay_id) AS barangay_id,
        i.incident_type,
        i.severity_level,
        i.cause_of_fire,
        i.casualties,
        i.notes,
        b.barangay_name
    FROM community_report r
    LEFT JOIN incident_record i ON i.report_id = r.report_id
    LEFT JOIN barangay b ON b.barangay_id = COALESCE(i.barangay_id, r.barangay_id)
    WHERE 1=1
";
$params = [];

if ($status) {
    $sql .= " AND r.status = :status";
    $params['status'] = $status;
}

if ($barangay_id) {
    $sql .= " AND COALESCE(i.barangay_id, r.barangay_id) = :barangay_id";
    $params['barangay_id'] = $barangay_id;
}

if ($year) {
    $sql .= " AND YEAR(COALESCE(i.data_time, r.created_at)) = :year";
    $params['year'] = $year;
}

if ($search) {
    $sql .= " AND (r.reporter_name LIKE :search OR r.description LIKE :search OR b.barangay_name LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

$sql .= " ORDER BY r.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $incidents = array_map('formatIncidentRow', $rows);
    sendSuccess($incidents);
} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}

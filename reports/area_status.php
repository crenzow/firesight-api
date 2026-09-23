<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

/**
 * GET /reports/area_status.php  (requires auth)
 * Powers the Home screen's "Area Status" card: current risk level and this
 * month's incident count for the resident's own registered barangay.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed.', 405);
}

$user = requireAuth();
$pdo = getDbConnection();

$addressStmt = $pdo->prepare(
    'SELECT ra.barangay_id, b.barangay_name FROM resident_address ra
     JOIN barangay b ON b.barangay_id = ra.barangay_id
     WHERE ra.user_id = :user_id LIMIT 1'
);
$addressStmt->execute(['user_id' => $user['user_id']]);
$address = $addressStmt->fetch();

if (!$address) {
    sendError('No registered address found for this account.', 404);
}

$barangayId = (int) $address['barangay_id'];

$riskStmt = $pdo->prepare(
    'SELECT risk_level, prediction_score FROM risk_assessment
     WHERE barangay_id = :barangay_id ORDER BY date DESC LIMIT 1'
);
$riskStmt->execute(['barangay_id' => $barangayId]);
$risk = $riskStmt->fetch();

$incidentStmt = $pdo->prepare(
    'SELECT COUNT(*) AS incident_count FROM incident_record
     WHERE barangay_id = :barangay_id
       AND MONTH(data_time) = MONTH(CURDATE()) AND YEAR(data_time) = YEAR(CURDATE())'
);
$incidentStmt->execute(['barangay_id' => $barangayId]);
$incidentCount = (int) $incidentStmt->fetch()['incident_count'];

// Simple heuristic: any general/emergency announcement posted in the last
// 7 days counts as an "active advisory" banner on Home. A dedicated
// active/expiry flag on `announcement` would be a nice Phase 2 refinement.
$advisoryStmt = $pdo->prepare(
    "SELECT COUNT(*) AS advisory_count FROM announcement
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
);
$advisoryStmt->execute();
$advisoryActive = ((int) $advisoryStmt->fetch()['advisory_count']) > 0;

sendSuccess([
    'barangay_id' => $barangayId,
    'barangay_name' => $address['barangay_name'],
    'risk_level' => $risk['risk_level'] ?? 'low',
    'incidents_this_month' => $incidentCount,
    'advisory_active' => $advisoryActive,
]);
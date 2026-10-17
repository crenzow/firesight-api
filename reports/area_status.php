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
    'SELECT ra.barangay_id, b.barangay_name, ra.municipality, ra.province FROM resident_address ra
     LEFT JOIN barangay b ON b.barangay_id = ra.barangay_id
     WHERE ra.user_id = :user_id LIMIT 1'
);
$addressStmt->execute(['user_id' => $user['user_id']]);
$address = $addressStmt->fetch();

$userMunicipality = trim($address['municipality'] ?? 'Lian');
$isLianResident = (strcasecmp($userMunicipality, 'Lian') === 0) && !empty($address['barangay_id']) && !empty($address['barangay_name']);

if ($isLianResident) {
    $barangayId = (int) $address['barangay_id'];
    $barangayName = $address['barangay_name'];
    $isExternal = false;

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

    $rawRisk = strtolower($risk['risk_level'] ?? 'mild');
} else {
    // Scenario B Option 1: Resident registered outside Lian pilot coverage
    // Calculate municipal average across all barangays in Lian (the active pilot station)
    $barangayId = null;
    $barangayName = 'All Barangays';
    $isExternal = true;

    $riskStmt = $pdo->query(
        'SELECT AVG(r.prediction_score) AS avg_score
         FROM risk_assessment r
         INNER JOIN (
             SELECT barangay_id, MAX(date) AS max_date 
             FROM risk_assessment 
             GROUP BY barangay_id
         ) latest ON r.barangay_id = latest.barangay_id AND r.date = latest.max_date'
    );
    $riskRow = $riskStmt ? $riskStmt->fetch() : null;
    $avgScore = $riskRow ? (float) ($riskRow['avg_score'] ?? 0) : 0;

    $incidentStmt = $pdo->query(
        'SELECT COUNT(*) AS incident_count FROM incident_record
         WHERE MONTH(data_time) = MONTH(CURDATE()) AND YEAR(data_time) = YEAR(CURDATE())'
    );
    $incidentCount = $incidentStmt ? (int) $incidentStmt->fetch()['incident_count'] : 0;

    if ($avgScore >= 0.66) {
        $rawRisk = 'high';
    } elseif ($avgScore >= 0.33) {
        $rawRisk = 'moderate';
    } else {
        $rawRisk = 'mild';
    }
}

// Simple heuristic: any general/emergency announcement posted in the last
// 7 days counts as an "active advisory" banner on Home.
$advisoryStmt = $pdo->prepare(
    "SELECT COUNT(*) AS advisory_count FROM announcement
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
);
$advisoryStmt->execute();
$advisoryActive = ((int) $advisoryStmt->fetch()['advisory_count']) > 0;

if ($rawRisk === 'critical' || $rawRisk === 'high') {
    $normalizedRisk = 'high';
} elseif ($rawRisk === 'moderate' || $rawRisk === 'medium') {
    $normalizedRisk = 'moderate';
} else {
    $normalizedRisk = 'mild';
}

sendSuccess([
    'barangay_id' => $barangayId,
    'barangay_name' => $barangayName,
    'municipality' => $isExternal ? 'Lian' : ($address['municipality'] ?? 'Lian'),
    'province' => $isExternal ? 'Batangas' : ($address['province'] ?? 'Batangas'),
    'registered_municipality' => $address['municipality'] ?? null,
    'registered_province' => $address['province'] ?? null,
    'is_external' => $isExternal,
    'scope_label' => $isExternal ? 'PILOT COVERAGE · MUNICIPAL AVERAGE' : 'AREA STATUS',
    'pilot_station' => 'BFP Lian Fire Station',
    'risk_level' => $normalizedRisk,
    'incidents_this_month' => $incidentCount,
    'advisory_active' => $advisoryActive,
]);
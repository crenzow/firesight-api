<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

/**
 * GET /map/incidents_bfp.php  (BFP personnel only)
 *
 * Richer incident data for the BFP Risk Mapping screen.
 * Returns all verified/dispatched/resolved incidents with full BFP fields:
 * reporter name, contact number, cause of fire, casualties,
 * severity, notes, and status.
 *
 * Optional query params:
 *   - year (int): filter by YEAR(data_time)
 *   - status: verified | dispatched | resolved (defaults to all three)
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed.', 405);
}

$user = requireAuth();
if ($user['role'] !== 'personnel') {
    sendError('Unauthorized.', 403);
}

$pdo = getDbConnection();

$year   = $_GET['year']   ?? null;
$status = $_GET['status'] ?? null;

$sql = "
    SELECT
        i.incident_id,
        r.report_id,
        COALESCE(i.barangay_id, r.barangay_id) AS barangay_id,
        b.barangay_name,
        r.latitude,
        r.longitude,
        r.reporter_name,
        r.contact_number,
        r.description,
        r.status,
        r.created_at,
        i.incident_type,
        i.severity_level,
        COALESCE(i.data_time, r.created_at) AS data_time,
        i.cause_of_fire,
        i.casualties,
        i.notes
    FROM incident_record i
    INNER JOIN community_report r ON r.report_id = i.report_id
    LEFT JOIN barangay b ON b.barangay_id = COALESCE(i.barangay_id, r.barangay_id)
    WHERE r.status IN ('accepted', 'dispatched', 'resolved')
      AND r.latitude IS NOT NULL
      AND r.longitude IS NOT NULL
";

$params = [];

if ($status && in_array($status, ['accepted', 'dispatched', 'resolved'], true)) {
    $sql .= " AND r.status = :status";
    $params['status'] = $status;
}

if ($year) {
    $sql .= " AND YEAR(COALESCE(i.data_time, r.created_at)) = :year";
    $params['year'] = (int) $year;
}

$sql .= " ORDER BY COALESCE(i.data_time, r.created_at) DESC LIMIT 500";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = array_map(function ($row) {
        return [
            'incident_id'      => $row['incident_id'] !== null ? (int) $row['incident_id'] : null,
            'report_id'        => (int) $row['report_id'],
            'barangay_id'      => (int) $row['barangay_id'],
            'barangay_name'    => $row['barangay_name'],
            'latitude'         => (float) $row['latitude'],
            'longitude'        => (float) $row['longitude'],
            'reporter_name'    => $row['reporter_name'],
            'contact_number'   => $row['contact_number'],
            'description'      => $row['description'],
            'status'           => $row['status'],
            'created_at'       => $row['created_at'],
            'incident_type'    => $row['incident_type'],
            'severity_level'   => $row['severity_level'],
            'data_time'        => $row['data_time'],
            'cause_of_fire'    => $row['cause_of_fire'],
            'casualties'       => $row['casualties'] !== null ? (int) $row['casualties'] : null,
            'notes'            => $row['notes'],
        ];
    }, $rows);

    sendSuccess($result);
} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}

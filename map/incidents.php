<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';

/**
 * GET /map/incidents.php  (public — no auth required)
 *
 * Returns confirmed, mapped fire incidents for the resident-facing map.
 * Shows only the location + basic type/severity — no reporter PII.
 *
 * Strategy: pull from incident_record (confirmed events) joined with
 * community_report for the GPS coordinates. Falls back to community_report
 * rows that are verified/resolved even if no incident_record exists yet,
 * so the map is never empty while BFP is still filling in details.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed.', 405);
}

$pdo = getDbConnection();

// Primary: incident_record rows (full detail available)
$sql = "
    SELECT
        i.incident_id,
        COALESCE(i.barangay_id, r.barangay_id) AS barangay_id,
        b.barangay_name,
        r.latitude,
        r.longitude,
        i.incident_type,
        i.severity_level,
        COALESCE(i.data_time, r.created_at) AS data_time,
        i.cause_of_fire,
        i.casualties,
        i.notes,
        r.status
    FROM incident_record i
    JOIN community_report r ON r.report_id = i.report_id
    LEFT JOIN barangay b ON b.barangay_id = COALESCE(i.barangay_id, r.barangay_id)
    WHERE r.status IN ('accepted', 'dispatched', 'resolved')
      AND r.latitude IS NOT NULL
      AND r.longitude IS NOT NULL

    UNION ALL

    -- Fallback: verified/resolved reports WITHOUT an incident_record yet
    SELECT
        NULL AS incident_id,
        r.barangay_id,
        b.barangay_name,
        r.latitude,
        r.longitude,
        'residential_fire' AS incident_type,
        'low' AS severity_level,
        r.created_at AS data_time,
        NULL AS cause_of_fire,
        NULL AS casualties,
        NULL AS notes,
        r.status
    FROM community_report r
    LEFT JOIN barangay b ON b.barangay_id = r.barangay_id
    WHERE r.status IN ('accepted', 'dispatched', 'resolved')
      AND r.latitude IS NOT NULL
      AND r.longitude IS NOT NULL
      AND NOT EXISTS (
          SELECT 1 FROM incident_record i2 WHERE i2.report_id = r.report_id
      )

    ORDER BY data_time DESC
    LIMIT 200
";

try {
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = array_map(function ($row) {
        return [
            'incident_id'    => $row['incident_id'] !== null ? (int) $row['incident_id'] : null,
            'barangay_id'    => (int) $row['barangay_id'],
            'barangay_name'  => $row['barangay_name'],
            'latitude'       => (float) $row['latitude'],
            'longitude'      => (float) $row['longitude'],
            'incident_type'  => $row['incident_type'] ?? 'residential_fire',
            'severity_level' => $row['severity_level'] ?? 'low',
            'data_time'      => $row['data_time'],
            // Resident-visible fields only — no PII
            'cause_of_fire'  => $row['cause_of_fire'],
            'casualties'     => $row['casualties'] !== null ? (int) $row['casualties'] : null,
            'notes'          => null, // residents don't see internal BFP notes
        ];
    }, $rows);

    sendSuccess($result);
} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
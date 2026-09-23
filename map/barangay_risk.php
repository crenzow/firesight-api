<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';

/**
 * GET /map/barangay_risk.php  (public — no auth required, matches
 * mapService.getBarangayRisk() on the frontend)
 * Returns all 19 barangays with their most recent risk_assessment row.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed.', 405);
}

$pdo = getDbConnection();

// For each barangay, join only the single most recent risk_assessment row
// (highest date). Barangays with no assessment yet default to 'low'/0 so
// the map never has to handle a null risk level.
$sql = "
    SELECT
        b.barangay_id,
        b.barangay_name,
        b.centroid_lat,
        b.centroid_lng,
        b.boundary_geojson,
        COALESCE(latest.risk_level, 'low') AS risk_level,
        COALESCE(latest.prediction_score, 0) AS prediction_score
    FROM barangay b
    LEFT JOIN (
        SELECT ra1.barangay_id, ra1.risk_level, ra1.prediction_score
        FROM risk_assessment ra1
        INNER JOIN (
            SELECT barangay_id, MAX(date) AS max_date
            FROM risk_assessment
            GROUP BY barangay_id
        ) ra2 ON ra1.barangay_id = ra2.barangay_id AND ra1.date = ra2.max_date
    ) latest ON latest.barangay_id = b.barangay_id
    ORDER BY b.barangay_name ASC
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll();

$result = array_map(function ($row) {
    return [
        'barangay_id' => (int) $row['barangay_id'],
        'barangay_name' => $row['barangay_name'],
        'centroid_lat' => (float) $row['centroid_lat'],
        'centroid_lng' => (float) $row['centroid_lng'],
        'boundary_geojson' => $row['boundary_geojson'],
        'risk_level' => $row['risk_level'],
        'prediction_score' => (float) $row['prediction_score'],
    ];
}, $rows);

sendSuccess($result);
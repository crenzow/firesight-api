<?php

function formatIncidentRow(array $row): array {
    return [
        'report_id' => (int) $row['report_id'],
        'reporter_name' => $row['reporter_name'],
        'contact_number' => $row['contact_number'],
        'description' => $row['description'],
        'report_image' => $row['report_image'],
        'latitude' => (float) $row['latitude'],
        'longitude' => (float) $row['longitude'],
        'location_accuracy_m' => $row['location_accuracy_m'] !== null ? (float) $row['location_accuracy_m'] : null,
        'barangay_id' => (int) $row['barangay_id'],
        'barangay_name' => $row['barangay_name'] ?? null,
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'ai_fire_label' => $row['ai_fire_label'] ?? null,
        'ai_fire_confidence' => $row['ai_fire_confidence'] !== null ? (float) $row['ai_fire_confidence'] : null,
        'incident_type' => $row['incident_type'] ?? null,
        'severity_level' => $row['severity_level'] ?? null,
        'cause_of_fire' => $row['cause_of_fire'] ?? null,
        'casualties' => isset($row['casualties']) ? (int) $row['casualties'] : null,
        'notes' => $row['notes'] ?? null,
    ];
}

function fetchIncident(PDO $pdo, int $reportId): ?array {
    $sql = "
        SELECT 
            r.*, 
            i.incident_type, i.severity_level, i.cause_of_fire, i.casualties, i.notes,
            b.barangay_name
        FROM community_report r
        LEFT JOIN incident_record i ON r.report_id = i.report_id
        LEFT JOIN barangay b ON r.barangay_id = b.barangay_id
        WHERE r.report_id = :report_id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['report_id' => $reportId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        return null;
    }
    
    return formatIncidentRow($row);
}

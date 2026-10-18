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

$reportId = $_GET['report_id'] ?? null;
if (!$reportId) {
    sendError('Missing report_id.', 400);
}

$body = getJsonBody();
$pdo = getDbConnection();

$incidentType = $body['incident_type'] ?? null;
$validIncidentTypes = ['residential_fire', 'commercial_fire', 'vehicular_fire', 'storage_fire', 'rubbish_fire', 'others'];
if ($incidentType && !in_array($incidentType, $validIncidentTypes, true)) {
    $incidentType = null;
}
$alarmLevel = $body['alarm_level'] ?? null;
$validAlarmLevels = [
    '1st_alarm', '2nd_alarm', '3rd_alarm', '4th_alarm', '5th_alarm',
    'task_force_alpha', 'task_force_bravo', 'task_force_charlie',
    'task_force_delta', 'task_force_echo', 'task_force_hotel',
    'task_force_india', 'general_alarm'
];
if ($alarmLevel && !in_array($alarmLevel, $validAlarmLevels, true)) {
    $alarmLevel = null;
}

try {
    $reportStmt = $pdo->prepare('SELECT status, barangay_id, created_at FROM community_report WHERE report_id = :report_id LIMIT 1');
    $reportStmt->execute(['report_id' => $reportId]);
    $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
    if (!$report) {
        sendError('Report not found.', 404);
    }
    if ($report['status'] !== 'resolved') {
        sendError('Incident details can only be saved after the report is resolved.', 409);
    }

    // Check if incident_record exists
    $stmt = $pdo->prepare("SELECT incident_id FROM incident_record WHERE report_id = :report_id");
    $stmt->execute(['report_id' => $reportId]);
    $exists = $stmt->fetchColumn();
    
    $params = [
        'report_id' => $reportId,
        'incident_type' => $incidentType,
        'alarm_level' => $alarmLevel,
        'cause_of_fire' => $body['cause_of_fire'] ?? null,
        'casualties' => $body['casualties'] ?? 0,
        'notes' => $body['notes'] ?? null,
    ];
    
    if ($exists) {
        $stmt = $pdo->prepare("
            UPDATE incident_record 
            SET incident_type = :incident_type,
                alarm_level = :alarm_level, 
                cause_of_fire = :cause_of_fire, 
                casualties = :casualties, 
                notes = :notes 
            WHERE report_id = :report_id
        ");
        $stmt->execute($params);
    } else {
        $params['barangay_id'] = $report['barangay_id'] ?? 1;
        $params['incident_datetime'] = $report['created_at'];
        $stmt = $pdo->prepare("
            INSERT INTO incident_record (report_id, barangay_id, incident_datetime, incident_type, alarm_level, cause_of_fire, casualties, notes) 
            VALUES (:report_id, :barangay_id, :incident_datetime, :incident_type, :alarm_level, :cause_of_fire, :casualties, :notes)
        ");
        $stmt->execute($params);
    }
    
    $incident = fetchIncident($pdo, (int)$reportId);
    sendSuccess($incident);
} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}

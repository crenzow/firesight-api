<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/notification_helper.php';
require_once __DIR__ . '/incident_helper.php';

$user = requireAuth();
if ($user['role'] !== 'personnel') {
    sendError('Unauthorized.', 403);
}

$pdo = getDbConnection();
$body = getJsonBody();

$report_id = $body['report_id'] ?? null;
$status = $body['status'] ?? null;
$notes = $body['notes'] ?? null;

if (!$report_id || !$status) {
    sendError('Missing report_id or status.', 400);
}

$validStatuses = ['accepted', 'dispatched', 'resolved'];
if (!in_array($status, $validStatuses)) {
    sendError('Invalid status value.', 400);
}

try {
    $pdo->beginTransaction();
    
    // Update status
    $stmt = $pdo->prepare("UPDATE community_report SET status = :status WHERE report_id = :report_id");
    $stmt->execute([
        'status' => $status,
        'report_id' => $report_id
    ]);
    
    if ($stmt->rowCount() === 0) {
        // If rowCount is 0, either report_id doesn't exist or status is already the same. Check if exists.
        $stmtCheck = $pdo->prepare("SELECT 1 FROM community_report WHERE report_id = :report_id");
        $stmtCheck->execute(['report_id' => $report_id]);
        if (!$stmtCheck->fetchColumn()) {
            $pdo->rollBack();
            sendError('Report not found.', 404);
        }
    }

    if ($status === 'resolved') {
        $reportStmt = $pdo->prepare(
            'SELECT barangay_id, created_at FROM community_report WHERE report_id = :report_id LIMIT 1'
        );
        $reportStmt->execute(['report_id' => $report_id]);
        $report = $reportStmt->fetch(PDO::FETCH_ASSOC);

        $incidentStmt = $pdo->prepare('SELECT incident_id FROM incident_record WHERE report_id = :report_id LIMIT 1');
        $incidentStmt->execute(['report_id' => $report_id]);
        if (!$incidentStmt->fetchColumn()) {
            $insertIncident = $pdo->prepare(
                "INSERT INTO incident_record
                    (report_id, barangay_id, data_time, incident_type, severity_level)
                 VALUES
                    (:report_id, :barangay_id, :data_time, 'residential_fire', 'low')"
            );
            $insertIncident->execute([
                'report_id' => $report_id,
                'barangay_id' => $report['barangay_id'],
                'data_time' => $report['created_at'],
            ]);
        } else {
            $syncIncident = $pdo->prepare(
                'UPDATE incident_record
                 SET barangay_id = :barangay_id, data_time = :data_time
                 WHERE report_id = :report_id'
            );
            $syncIncident->execute([
                'report_id' => $report_id,
                'barangay_id' => $report['barangay_id'],
                'data_time' => $report['created_at'],
            ]);
        }
    }
    
    // Insert history
    $stmt = $pdo->prepare("INSERT INTO report_status_history (report_id, status, notes, changed_by) VALUES (:report_id, :status, :notes, :changed_by)");
    $stmt->execute([
        'report_id' => $report_id,
        'status' => $status,
        'notes' => $notes,
        'changed_by' => $user['user_id']
    ]);

    notifyReportStatusChange($pdo, (int) $report_id, $status);
    
    $pdo->commit();
    
    $incident = fetchIncident($pdo, (int)$report_id);
    sendSuccess($incident);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendError('Database error: ' . $e->getMessage(), 500);
}

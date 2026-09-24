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

$report_id = $_GET['report_id'] ?? null;
if (!$report_id) {
    sendError('Missing report_id parameter.', 400);
}

try {
    $incident = fetchIncident($pdo, (int)$report_id);
    if (!$incident) {
        sendError('Incident not found.', 404);
    }
    
    // Attempt to fetch evidence if the table exists
    $evidence = [];
    try {
        $stmt = $pdo->prepare("SELECT evidence_id, image_path, caption FROM report_evidence WHERE report_id = :report_id");
        $stmt->execute(['report_id' => $report_id]);
        $evidence = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table might not exist, safely ignore
    }
    
    $incident['evidence_photos'] = $evidence;
    
    // Fetch barangay contacts
    $contacts = [];
    try {
        if (!empty($incident['barangay_id'])) {
            $stmt = $pdo->prepare("SELECT name, role, phone_number FROM barangay_contact WHERE barangay_id = :barangay_id");
            $stmt->execute(['barangay_id' => $incident['barangay_id']]);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // Table might not exist yet, safely ignore
    }
    $incident['barangay_contacts'] = $contacts;
    
    sendSuccess($incident);
} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}

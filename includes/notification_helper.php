<?php

require_once __DIR__ . '/geo_helper.php';

/**
 * Creates a resident notification for a community report status change.
 * Reports without a resident owner, such as manual BFP entries, are skipped.
 */
function notifyReportStatusChange(PDO $pdo, int $reportId, string $status): void
{
    $reportStmt = $pdo->prepare(
        'SELECT user_id FROM community_report WHERE report_id = :report_id LIMIT 1'
    );
    $reportStmt->execute(['report_id' => $reportId]);
    $userId = $reportStmt->fetchColumn();

    if ($userId === false || $userId === null) {
        return;
    }

    // Map each status to a complete, natural-sounding sentence
    $messages = [
        'accepted'   => 'Your fire report has been accepted by BFP personnel.',
        'dispatched' => 'BFP personnel have been dispatched to your reported location.',
        'resolved'   => 'Your fire report has been marked as resolved by BFP personnel.',
        'invalid'    => 'Your fire report has been marked as false or invalid by BFP personnel.',
    ];

    // Fallback message just in case an unexpected status is passed
    $message = $messages[$status] ?? "The status of your fire report has been updated to: {$status}.";

    $notificationStmt = $pdo->prepare(
        "INSERT INTO notification (user_id, title, message, notification_type, is_read)
         VALUES (:user_id, 'Report Status Updated', :message, 'update', 0)"
    );
    
    $notificationStmt->execute([
        'user_id' => $userId,
        'message' => $message,
    ]);
}

/** Creates a notification for every active BFP personnel account. */
function notifyPersonnelOfNewReport(PDO $pdo, int $reportId): void
{
    $reportStmt = $pdo->prepare(
        'SELECT r.ai_fire_label, r.ai_fire_confidence, r.latitude, r.longitude, b.barangay_name
         FROM community_report r
         LEFT JOIN barangay b ON b.barangay_id = r.barangay_id
         WHERE r.report_id = :report_id LIMIT 1'
    );
    $reportStmt->execute(['report_id' => $reportId]);
    $report = $reportStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $personnelStmt = $pdo->query(
        "SELECT user_id FROM user WHERE role = 'personnel' AND is_active = 1"
    );
    $personnel = $personnelStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$personnel) {
        return;
    }

    $locationName = trim((string) ($report['barangay_name'] ?? ''));
    if ($locationName === '' && $report['latitude'] !== null && $report['longitude'] !== null) {
        $matchedBarangayId = findBarangayByLocation((float) $report['latitude'], (float) $report['longitude'], $pdo);
        if ($matchedBarangayId !== null) {
            $locationStmt = $pdo->prepare('SELECT barangay_name FROM barangay WHERE barangay_id = :id LIMIT 1');
            $locationStmt->execute(['id' => $matchedBarangayId]);
            $locationName = trim((string) ($locationStmt->fetchColumn() ?: ''));
        }
    }
    if ($report['latitude'] !== null && $report['longitude'] !== null) {
        if (function_exists('findReadableLocation')) {
            $locationName = call_user_func(
                'findReadableLocation',
                (float) $report['latitude'],
                (float) $report['longitude'],
                $locationName
            );
        } elseif ($locationName === '') {
            $locationName = 'submitted location';
        }
    }
    $title = "Fire Report near {$locationName}";

    $notificationStmt = $pdo->prepare(
        "INSERT INTO notification (user_id, title, message, notification_type, is_read)
         VALUES (:user_id, :title, :message, 'alert', 0)"
    );
    $label = $report['ai_fire_label'] ?? null;
    $confidence = isset($report['ai_fire_confidence']) && $report['ai_fire_confidence'] !== null
        ? (float) $report['ai_fire_confidence']
        : null;
    if ($label === 'fire') {
        $aiSummary = $confidence !== null
            ? sprintf('The image assessment indicates that fire was detected with %.1f%% confidence.', $confidence * 100)
            : 'The image assessment indicates that fire was detected.';
    } elseif ($label === 'non_fire') {
        $aiSummary = $confidence !== null
            ? sprintf('The image assessment indicates that no fire was detected with %.1f%% confidence.', $confidence * 100)
            : 'The image assessment indicates that no fire was detected.';
    } else {
        $aiSummary = 'The image assessment is currently unavailable.';
    }
    $message = "A new fire report (#{$reportId}) was submitted near {$locationName}. {$aiSummary}";

    foreach ($personnel as $userId) {
        $duplicateStmt = $pdo->prepare(
            'SELECT notification_id FROM notification
             WHERE user_id = :user_id AND notification_type = \'alert\'
               AND message REGEXP CONCAT("(^|[^0-9])#", :report_id, "([^0-9]|$)")
             LIMIT 1'
        );
        $duplicateStmt->execute([
            'user_id' => $userId,
            'report_id' => $reportId,
        ]);
        if ($duplicateStmt->fetchColumn() !== false) {
            continue;
        }

        $notificationStmt->execute([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
        ]);
    }
}
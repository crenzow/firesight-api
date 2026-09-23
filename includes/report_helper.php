<?php
/**
 * Formats a community_report row (optionally joined with barangay_name)
 * into the exact CommunityReport shape the frontend expects
 * (services/api/models.ts).
 */
function formatReportRow(array $row): array
{
    return [
        'report_id' => (int) $row['report_id'],
        'description' => $row['description'],
        'report_image' => $row['report_image'],
        'latitude' => (float) $row['latitude'],
        'longitude' => (float) $row['longitude'],
        'barangay_id' => (int) $row['barangay_id'],
        'barangay_name' => $row['barangay_name'] ?? null,
        'status' => $row['status'],
        'created_at' => $row['created_at'],
    ];
}
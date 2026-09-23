<?php
/**
 * CORS headers — required because the Expo app (running on a phone/emulator,
 * a different "origin" than the PHP server) calls this API directly via
 * fetch(). Without these headers the requests would be blocked.
 *
 * Include this at the very top of every endpoint file, before any output.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Preflight requests: browsers send OPTIONS before the real request when
// custom headers are involved. The mobile app itself doesn't need this, but
// it's harmless to keep for future web/admin dashboard use.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
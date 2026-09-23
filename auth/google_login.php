<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../includes/response.php';

/**
 * POST /auth/google_login.php
 *
 * STUB — the "Continue with Google" button on the Login screen is currently
 * inert on the frontend (no expo-auth-session flow wired up yet), so this
 * endpoint is not reachable in practice. Left here so the contract exists
 * once Google Sign-In is activated:
 *
 *   1. Frontend obtains a Google ID token via expo-auth-session.
 *   2. This endpoint verifies it against Google's tokeninfo endpoint
 *      (or the Google API PHP client), extracts email + name.
 *   3. Finds or creates a `user` row (role='resident', google_id=sub),
 *      issues an auth_token exactly like login.php, and returns
 *      { token, user } in the same shape.
 */

sendError('Google Sign-In is not yet configured on this server.', 501);
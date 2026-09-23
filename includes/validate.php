<?php
/**
 * Shared validation helpers, mirroring the rules already enforced client-side
 * in utils/validators.ts — the backend re-checks everything because client
 * validation can always be bypassed.
 */

function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidPHMobile(string $mobile): bool
{
    $digits = preg_replace('/\D/', '', $mobile);
    return (bool) preg_match('/^(09\d{9}|639\d{9})$/', $digits);
}

function isStrongEnoughPassword(string $password): bool
{
    return strlen($password) >= 8;
}

/** Trims strings and strips tags to reduce stored-XSS risk on free-text fields. */
function sanitizeText(?string $value): string
{
    return trim(strip_tags($value ?? ''));
}
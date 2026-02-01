<?php
/**
 * app/helpers/functions.php
 * Common helper functions extracted from includes/functions.php
 */

/**
 * Basic input sanitization.
 */
function sanitizeInput(string $value): string
{
    return trim($value);
}

/**
 * Validate email format.
 */
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password: at least 8 chars including letters and numbers.
 */
function isValidPassword(string $password): bool
{
    if (strlen($password) < 8) return false;
    $hasLetter = preg_match('/[A-Za-z]/', $password);
    $hasNumber = preg_match('/\d/', $password);
    return (bool) ($hasLetter && $hasNumber);
}

/**
 * Validate phone: digits, +, -, spaces, 7-20 characters.
 */
function isValidPhone(string $phone): bool
{
    if ($phone === '') return true;
    return preg_match('/^[0-9+\-\s]{7,20}$/', $phone) === 1;
}

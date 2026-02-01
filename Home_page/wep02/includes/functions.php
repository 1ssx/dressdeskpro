<?php
/**
 * Common helper functions used by API endpoints.
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
 * Validate password:
 * - At least 8 characters
 * - Contains at least one letter and one number
 */
function isValidPassword(string $password): bool
{
    if (strlen($password) < 8) {
        return false;
    }

    $hasLetter = preg_match('/[A-Za-z]/', $password);
    $hasNumber = preg_match('/\d/', $password);

    return (bool) ($hasLetter && $hasNumber);
}

/**
 * Validate phone:
 * Allow digits, +, -, spaces. Length 7�??20 chars.
 */
function isValidPhone(string $phone): bool
{
    if ($phone === '') {
        // Phone can be optional; adjust if you want it mandatory
        return true;
    }
    return preg_match('/^[0-9+\\-\\s]{7,20}$/', $phone) === 1;
}


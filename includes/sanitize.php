<?php
/**
 * 🔹 sanitize.php
 * Centralized sanitization & validation functions
 */

/**
 * General sanitization for text inputs
 * - Converts to uppercase
 * - Trims spaces
 * - Removes unwanted symbols
 */
function sanitizeText($input) {
    $input = strtoupper(trim((string)$input));
    // ✅ Allow only letters, numbers, spaces, dot, hyphen, slash
    return preg_replace("/[^A-Z0-9\s\.\-\/]/", "", $input);
}

/**
 * Sanitize numeric values (integers & decimals)
 */
function sanitizeNumber($input) {
    $input = trim((string)$input);
    return is_numeric($input) ? $input : 0;
}

/**
 * Sanitize phone numbers
 * - Keeps only digits
 * - Max length 15 (international standard)
 */
function sanitizePhone($input) {
    $input = preg_replace("/[^0-9]/", "", (string)$input);
    return substr($input, 0, 15);
}

/**
 * Secure output (prevent XSS when echoing data)
 */
function safeOutput($input) {
    return htmlspecialchars((string)$input, ENT_QUOTES, 'UTF-8');
}

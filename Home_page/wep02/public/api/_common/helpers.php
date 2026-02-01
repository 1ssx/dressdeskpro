<?php
/**
 * Common Helper Functions
 * PHASE 3 - API Restructuring
 * 
 * Shared utility functions used across multiple APIs
 */

// Prevent direct access unless in test environment
if (!defined('API_COMMON') && !defined('TEST_ENV')) {
    die('Direct access not allowed');
}


/**
 * Sanitize string input
 */
function sanitize($string) {
    return htmlspecialchars(strip_tags(trim($string)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Format currency amount
 */
function formatCurrency($amount, $currency = 'ريال') {
    return number_format((float)$amount, 2, '.', ',') . ' ' . $currency;
}

/**
 * Format date in Arabic format
 */
function formatArabicDate($date) {
    if (empty($date)) {
        return '';
    }
    
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }
    
    $day = date('d', $timestamp);
    $month = date('m', $timestamp);
    $year = date('Y', $timestamp);
    
    $months = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
    ];
    
    return "{$day} {$months[intval($month)]} {$year}";
}

/**
 * Calculate time ago in Arabic (accurate calculation)
 */
function timeAgoArabic($datetime) {
    if (empty($datetime)) {
        return '';
    }
    
    try {
        // Use DateTime for accurate timezone handling
        $created = new DateTime($datetime);
        $now = new DateTime();
        $diff = $now->diff($created);
        
        // If created is in the future (shouldn't happen), return empty
        if ($created > $now) {
            return '';
        }
        
        // Calculate total seconds difference
        $totalSeconds = ($now->getTimestamp() - $created->getTimestamp());
        
        if ($totalSeconds < 60) {
            return 'منذ أقل من دقيقة';
        } elseif ($totalSeconds < 3600) {
            $minutes = floor($totalSeconds / 60);
            return $minutes == 1 ? 'منذ دقيقة واحدة' : "منذ {$minutes} دقائق";
        } elseif ($totalSeconds < 86400) {
            $hours = floor($totalSeconds / 3600);
            return $hours == 1 ? 'منذ ساعة واحدة' : "منذ {$hours} ساعات";
        } else {
            $days = floor($totalSeconds / 86400);
            if ($days == 1) {
                return 'منذ يوم واحد';
            } elseif ($days == 2) {
                return 'منذ يومين';
            } elseif ($days < 7) {
                return "منذ {$days} أيام";
            } else {
                $weeks = floor($days / 7);
                return $weeks == 1 ? 'منذ أسبوع واحد' : "منذ {$weeks} أسابيع";
            }
        }
    } catch (Exception $e) {
        // Fallback to simple calculation
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return '';
        }
        
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'منذ أقل من دقيقة';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes == 1 ? 'منذ دقيقة واحدة' : "منذ {$minutes} دقائق";
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours == 1 ? 'منذ ساعة واحدة' : "منذ {$hours} ساعات";
        } else {
            $days = floor($diff / 86400);
            return $days == 1 ? 'منذ يوم واحد' : "منذ {$days} أيام";
        }
    }
}

/**
 * Validate required fields
 */
function validateRequired($data, $requiredFields) {
    $errors = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[$field] = "Field '{$field}' is required";
        }
    }
    
    if (!empty($errors)) {
        sendValidationError($errors);
    }
}

/**
 * Generate unique invoice number
 */
function generateInvoiceNumber($pdo, $prefix = 'INV-') {
    // Get highest existing invoice number
    $stmt = $pdo->query("SELECT invoice_number FROM invoices ORDER BY invoice_number DESC LIMIT 1");
    $lastInvoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextNum = 1;
    if ($lastInvoice && !empty($lastInvoice['invoice_number'])) {
        $lastNumber = $lastInvoice['invoice_number'];
        if (preg_match('/' . preg_quote($prefix, '/') . '(\d+)/', $lastNumber, $matches)) {
            $nextNum = intval($matches[1]) + 1;
        }
    }
    
    // Check if this number already exists, if so increment
    $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM invoices WHERE invoice_number = ?");
    while (true) {
        $invoiceNumber = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        $checkStmt->execute([$invoiceNumber]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if ($result['count'] == 0) {
            break; // Found unique number
        }
        $nextNum++; // Try next number
    }
    
    return $invoiceNumber;
}

/**
 * Log error (for debugging)
 */
function logError($message, $context = []) {
    error_log(sprintf(
        '[API Error] %s | Context: %s',
        $message,
        json_encode($context)
    ));
}


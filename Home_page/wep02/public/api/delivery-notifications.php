<?php
/**
 * Delivery Notifications API
 * Returns upcoming dress delivery notifications based on invoices.collection_date
 * PHASE 3 - Dedicated endpoint for delivery alerts
 */

// Load common infrastructure (handles output buffering, auth, database, config)
require_once __DIR__ . '/_common/bootstrap.php';

try {
    // Require authentication
    requireAuth();

    // Get configuration constants
    $windowDays = defined('DELIVERY_ALERT_WINDOW_DAYS') ? DELIVERY_ALERT_WINDOW_DAYS : 7;
    $limit = defined('DELIVERY_ALERT_LIMIT') ? DELIVERY_ALERT_LIMIT : 10;

    // ============================================================
    // QUERY UPCOMING DELIVERIES
    // ============================================================
    // Check if collection_date column exists (for backward compatibility)
    try {
        $checkColumn = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'collection_date'");
        $hasCollectionDate = $checkColumn->rowCount() > 0;
    } catch (Exception $e) {
        $hasCollectionDate = false;
    }
    
    $rows = [];
    if ($hasCollectionDate) {
        $sql = "
            SELECT 
                i.id AS invoice_id,
                i.invoice_number,
                i.collection_date,
                i.invoice_status,
                i.deleted_at,
                c.name AS customer_name,
                COALESCE(
                    (SELECT item_name
                     FROM invoice_items
                     WHERE invoice_id = i.id
                       AND item_type IN ('custom_dress', 'product')
                     ORDER BY id ASC
                     LIMIT 1),
                    'فستان'
                ) AS item_name,
                DATEDIFF(i.collection_date, CURDATE()) AS days_until
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE i.collection_date IS NOT NULL
              AND i.collection_date >= CURDATE()
              AND i.collection_date <= DATE_ADD(CURDATE(), INTERVAL :window_days DAY)
              AND i.deleted_at IS NULL
            ORDER BY
                CASE
                    WHEN i.collection_date = CURDATE() THEN 1
                    WHEN DATEDIFF(i.collection_date, CURDATE()) <= 2 THEN 2
                    ELSE 3
                END,
                i.collection_date ASC
            LIMIT :limit
        ";
        
        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':window_days' => $windowDays,
            ':limit' => $limit
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // DEBUG: Log query results
        logError('Delivery Notifications Debug', [
            'window_days' => $windowDays,
            'limit' => $limit,
            'current_date' => date('Y-m-d'),
            'max_date' => date('Y-m-d', strtotime("+{$windowDays} days")),
            'rows_found' => count($rows),
            'rows' => $rows,
            'sql' => $sql
        ]);
    } else {
        logError('Delivery Notifications Debug', [
            'error' => 'collection_date column does not exist in invoices table'
        ]);
    }

    // ============================================================
    // MAP ROWS TO JSON NOTIFICATIONS
    // ============================================================

    $deliveries = [];
    $hasUrgent = false;

    foreach ($rows as $row) {
        $daysUntil = intval($row['days_until']);
        $collectionDate = date('Y-m-d', strtotime($row['collection_date']));
        
        // Determine priority and urgency
        $priority = 'normal';
        $isUrgent = false;
        $dateFormatted = '';

        if ($daysUntil == 0) {
            $priority = 'high';
            $isUrgent = true;
            $dateFormatted = 'اليوم';
            $hasUrgent = true;
        } elseif ($daysUntil == 1) {
            $priority = 'medium';
            $dateFormatted = 'غداً';
        } elseif ($daysUntil == 2) {
            $priority = 'medium';
            $dateFormatted = 'بعد غد';
        } else {
            $priority = 'normal';
            $dateFormatted = formatArabicDate($collectionDate);
        }

        $deliveries[] = [
            'type' => 'delivery',
            'invoice_id' => intval($row['invoice_id']),
            'invoice_number' => $row['invoice_number'],
            'customer_name' => $row['customer_name'] ?? 'غير معروف',
            'dress_name' => $row['item_name'],
            'delivery_date' => $collectionDate,
            'days_until' => $daysUntil,
            'priority' => $priority,
            'is_urgent' => $isUrgent,
            'date_formatted' => $dateFormatted,
            'link' => 'sales.php?invoice=' . intval($row['invoice_id'])
        ];
    }

    // ============================================================
    // BUILD RESPONSE
    // ============================================================

    $data = [
        'deliveries' => $deliveries,
        'count' => count($deliveries),
        'window_days' => $windowDays,
        'generated_at' => date('c') // ISO 8601 format
    ];

    sendSuccess($data);

} catch (Exception $e) {
    logError('Delivery Notifications API Error', ['message' => $e->getMessage()]);
    sendError('Failed to load delivery notifications: ' . $e->getMessage(), 500);
}


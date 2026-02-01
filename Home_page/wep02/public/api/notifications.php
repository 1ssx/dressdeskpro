<?php
/**
 * Notifications API
 * Returns customer notifications (48h) and delivery notifications (7 days)
 * PHASE 3 - Refactored to use common infrastructure
 */

// Load common infrastructure (handles output buffering)
require_once __DIR__ . '/_common/bootstrap.php';

try {

    // ============================================================
    // A) CUSTOMER NOTIFICATIONS (Last 48 hours)
    // ============================================================
    $customerStmt = $pdo->prepare("
        SELECT 
            c.id as customer_id,
            c.name as customer_name,
            c.created_at
        FROM customers c
        WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ORDER BY c.created_at DESC
    ");
    $customerStmt->execute();
    $customerRows = $customerStmt->fetchAll(PDO::FETCH_ASSOC);

    $customerNotifications = [];
    foreach ($customerRows as $row) {
        // Calculate time difference in seconds
        $createdTimestamp = strtotime($row['created_at']);
        $nowTimestamp = time();
        $diffSeconds = $nowTimestamp - $createdTimestamp;
        $hoursAgo = floor($diffSeconds / 3600);
        
        // Format time ago in Arabic (using helper function)
        $timeAgo = timeAgoArabic($row['created_at']);

        $customerNotifications[] = [
            'customer_id' => intval($row['customer_id']),
            'customer_name' => $row['customer_name'],
            'created_at' => $row['created_at'],
            'time_ago' => $timeAgo,
            'hours_ago' => $hoursAgo,
            'is_valid' => $hoursAgo < 48
        ];
    }

    // ============================================================
    // B) DELIVERY NOTIFICATIONS (Next 7 days, prioritized)
    // ============================================================
    // Check if collection_date column exists (for backward compatibility)
    try {
        $checkColumn = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'collection_date'");
        $hasCollectionDate = $checkColumn->rowCount() > 0;
    } catch (Exception $e) {
        $hasCollectionDate = false;
    }
    
    $deliveryRows = [];
    if ($hasCollectionDate) {
        $deliveryStmt = $pdo->prepare("
            SELECT 
                i.id as invoice_id,
                i.invoice_number,
                i.collection_date,
                c.name as customer_name,
                COALESCE(
                    (SELECT item_name 
                     FROM invoice_items 
                     WHERE invoice_id = i.id 
                       AND item_type IN ('custom_dress', 'product')
                     ORDER BY id ASC 
                     LIMIT 1),
                    'فستان'
                ) as item_name,
                DATEDIFF(i.collection_date, CURDATE()) as days_until
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE i.collection_date IS NOT NULL
              AND i.collection_date >= CURDATE()
              AND i.collection_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY 
                CASE 
                    WHEN i.collection_date = CURDATE() THEN 1
                    WHEN DATEDIFF(i.collection_date, CURDATE()) <= 2 THEN 2
                    ELSE 3
                END,
                i.collection_date ASC
        ");
        $deliveryStmt->execute();
        $deliveryRows = $deliveryStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $deliveryNotifications = [];
    foreach ($deliveryRows as $row) {
        $daysUntil = intval($row['days_until']);
        
        // Determine priority
        $priority = 'normal';
        $isUrgent = false;
        
        if ($daysUntil == 0) {
            $priority = 'high';
            $isUrgent = true;
        } elseif ($daysUntil <= 2) {
            $priority = 'medium';
        }

        // Format date in Arabic
        $collectionDate = date('Y-m-d', strtotime($row['collection_date']));
        $dateFormatted = '';
        if ($daysUntil == 0) {
            $dateFormatted = 'اليوم';
        } elseif ($daysUntil == 1) {
            $dateFormatted = 'غداً';
        } elseif ($daysUntil == 2) {
            $dateFormatted = 'بعد غد';
        } else {
            $dateFormatted = formatArabicDate($collectionDate);
        }

        $deliveryNotifications[] = [
            'invoice_id' => intval($row['invoice_id']),
            'invoice_number' => $row['invoice_number'],
            'customer_name' => $row['customer_name'] ?? 'غير معروف',
            'item_name' => $row['item_name'],
            'collection_date' => $collectionDate,
            'date_formatted' => $dateFormatted,
            'days_until' => $daysUntil,
            'priority' => $priority,
            'is_urgent' => $isUrgent
        ];
    }

    // Return combined notifications
    $data = [
        'customer_notifications' => $customerNotifications,
        'delivery_notifications' => $deliveryNotifications,
        'total_customer' => count($customerNotifications),
        'total_delivery' => count($deliveryNotifications),
        'total' => count($customerNotifications) + count($deliveryNotifications)
    ];

    sendSuccess($data);

} catch (Exception $e) {
    logError('Notifications API Error', ['message' => $e->getMessage()]);
    sendError('Failed to load notifications: ' . $e->getMessage(), 500);
}


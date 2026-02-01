<?php
/**
 * Bookings API
 * Manages booking appointments for dress trials, measurements, pickups, etc.
 * PHASE 2 - Bookings Module Implementation
 */

// Load common infrastructure (handles output buffering, auth, database, config)
require_once __DIR__ . '/_common/bootstrap.php';

// Booking types (hardcoded as per plan)
$bookingTypes = ['تجربة', 'قياسات', 'استلام', 'إرجاع', 'تصميم', 'تصوير', 'زفة'];

$action = getQueryParam('action', 'list');

try {
    // Require authentication for all actions
    requireAuth();

    switch ($action) {

        /**
         * GET bookings.php?action=list
         * Returns all bookings with optional filters
         * Filters: date_from, date_to, status, booking_type, customer_id
         */
        case 'list':
            $dateFrom = getQueryParam('date_from');
            $dateTo = getQueryParam('date_to');
            $status = getQueryParam('status');
            $bookingType = getQueryParam('booking_type');
            $customerId = getQueryParam('customer_id');

            $where = ['1=1'];
            $params = [];

            if ($dateFrom) {
                $where[] = 'DATE(b.booking_date) >= :date_from';
                $params[':date_from'] = $dateFrom;
            }

            if ($dateTo) {
                $where[] = 'DATE(b.booking_date) <= :date_to';
                $params[':date_to'] = $dateTo;
            }

            if ($status && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled', 'late'])) {
                $where[] = 'b.status = :status';
                $params[':status'] = $status;
            }

            if ($bookingType) {
                $where[] = 'b.booking_type = :booking_type';
                $params[':booking_type'] = $bookingType;
            }

            if ($customerId) {
                $where[] = 'b.customer_id = :customer_id';
                $params[':customer_id'] = intval($customerId);
            }

            $whereClause = implode(' AND ', $where);

            $stmt = $pdo->prepare("
                SELECT 
                    b.*,
                    c.name AS customer_name,
                    c.phone_1,
                    c.phone_2,
                    i.invoice_number,
                    COALESCE(
                        (SELECT item_name 
                         FROM invoice_items 
                         WHERE invoice_id = i.id 
                           AND item_type IN ('custom_dress', 'product')
                         ORDER BY id ASC 
                         LIMIT 1),
                        NULL
                    ) AS dress_name
                FROM bookings b
                LEFT JOIN customers c ON b.customer_id = c.id
                LEFT JOIN invoices i ON b.invoice_id = i.id
                WHERE {$whereClause}
                ORDER BY b.booking_date ASC, b.id ASC
            ");

            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $bookings = [];
            foreach ($rows as $row) {
                $bookings[] = [
                    'id' => intval($row['id']),
                    'customer_id' => intval($row['customer_id']),
                    'customer_name' => $row['customer_name'] ?? 'غير معروف',
                    'phone_1' => $row['phone_1'] ?? '',
                    'phone_2' => $row['phone_2'] ?? '',
                    'invoice_id' => $row['invoice_id'] ? intval($row['invoice_id']) : null,
                    'invoice_number' => $row['invoice_number'] ?? null,
                    'dress_name' => $row['dress_name'] ?? null,
                    'booking_type' => $row['booking_type'],
                    'status' => $row['status'],
                    'booking_date' => $row['booking_date'],
                    'notes' => $row['notes'] ?? '',
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at']
                ];
            }

            sendSuccess($bookings);
            break;

        /**
         * GET bookings.php?action=get&id=123
         * Returns single booking by ID
         */
        case 'get':
            $id = getQueryParam('id');
            if (!$id) {
                sendError('Booking ID is required', 400);
            }

            $stmt = $pdo->prepare("
                SELECT 
                    b.*,
                    c.name AS customer_name,
                    c.phone_1,
                    c.phone_2,
                    i.invoice_number
                FROM bookings b
                LEFT JOIN customers c ON b.customer_id = c.id
                LEFT JOIN invoices i ON b.invoice_id = i.id
                WHERE b.id = :id
            ");
            $stmt->execute([':id' => intval($id)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                sendError('Booking not found', 404);
            }

            $booking = [
                'id' => intval($row['id']),
                'customer_id' => intval($row['customer_id']),
                'customer_name' => $row['customer_name'] ?? 'غير معروف',
                'phone_1' => $row['phone_1'] ?? '',
                'phone_2' => $row['phone_2'] ?? '',
                'invoice_id' => $row['invoice_id'] ? intval($row['invoice_id']) : null,
                'invoice_number' => $row['invoice_number'] ?? null,
                'booking_type' => $row['booking_type'],
                'status' => $row['status'],
                'booking_date' => $row['booking_date'],
                'notes' => $row['notes'] ?? '',
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];

            sendSuccess($booking);
            break;

        /**
         * POST bookings.php?action=create
         * Creates a new booking
         */
        case 'create':
            requireMethod('POST');
            $data = getJsonInput();

            validateRequired($data, ['customer_id', 'booking_type', 'booking_date']);

            $customerId = intval($data['customer_id']);
            $invoiceId = !empty($data['invoice_id']) ? intval($data['invoice_id']) : null;
            $bookingType = sanitize($data['booking_type']);
            $status = !empty($data['status']) && in_array($data['status'], ['pending', 'confirmed', 'completed', 'cancelled', 'late']) 
                ? $data['status'] 
                : 'pending';
            $bookingDate = sanitize($data['booking_date']);
            $notes = !empty($data['notes']) ? sanitize($data['notes']) : null;

            // Validate booking type
            if (!in_array($bookingType, $bookingTypes)) {
                sendError('Invalid booking type', 400);
            }

            // Validate booking date format (allow past dates for edits, but validate format)
            $bookingTimestamp = strtotime($bookingDate);
            if ($bookingTimestamp === false) {
                sendError('Invalid booking date format', 400);
            }
            
            // Only enforce future date for new bookings (not updates)
            // This check is removed for updates to allow editing past bookings

            // Validate customer exists
            $customerCheck = $pdo->prepare("SELECT id FROM customers WHERE id = :id");
            $customerCheck->execute([':id' => $customerId]);
            if (!$customerCheck->fetch()) {
                sendError('Customer not found', 400);
            }

            // Validate invoice if provided
            if ($invoiceId) {
                $invoiceCheck = $pdo->prepare("SELECT id FROM invoices WHERE id = :id");
                $invoiceCheck->execute([':id' => $invoiceId]);
                if (!$invoiceCheck->fetch()) {
                    sendError('Invoice not found', 400);
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO bookings 
                (customer_id, invoice_id, booking_type, status, booking_date, notes)
                VALUES (:customer_id, :invoice_id, :booking_type, :status, :booking_date, :notes)
            ");

            $stmt->execute([
                ':customer_id' => $customerId,
                ':invoice_id' => $invoiceId,
                ':booking_type' => $bookingType,
                ':status' => $status,
                ':booking_date' => $bookingDate,
                ':notes' => $notes
            ]);

            $bookingId = $pdo->lastInsertId();

            sendSuccess(['id' => intval($bookingId), 'message' => 'تم إنشاء الحجز بنجاح']);
            break;

        /**
         * POST bookings.php?action=update&id=123
         * Updates an existing booking
         */
        case 'update':
            requireMethod('POST');
            $id = getQueryParam('id');
            if (!$id) {
                sendError('Booking ID is required', 400);
            }

            $data = getJsonInput();

            // Check if booking exists
            $checkStmt = $pdo->prepare("SELECT id FROM bookings WHERE id = :id");
            $checkStmt->execute([':id' => intval($id)]);
            if (!$checkStmt->fetch()) {
                sendError('Booking not found', 404);
            }

            $updates = [];
            $params = [':id' => intval($id)];

            if (isset($data['customer_id'])) {
                $customerId = intval($data['customer_id']);
                $customerCheck = $pdo->prepare("SELECT id FROM customers WHERE id = :id");
                $customerCheck->execute([':id' => $customerId]);
                if (!$customerCheck->fetch()) {
                    sendError('Customer not found', 400);
                }
                $updates[] = 'customer_id = :customer_id';
                $params[':customer_id'] = $customerId;
            }

            if (isset($data['invoice_id'])) {
                $invoiceId = !empty($data['invoice_id']) ? intval($data['invoice_id']) : null;
                if ($invoiceId) {
                    $invoiceCheck = $pdo->prepare("SELECT id FROM invoices WHERE id = :id");
                    $invoiceCheck->execute([':id' => $invoiceId]);
                    if (!$invoiceCheck->fetch()) {
                        sendError('Invoice not found', 400);
                    }
                }
                $updates[] = 'invoice_id = :invoice_id';
                $params[':invoice_id'] = $invoiceId;
            }

            if (isset($data['booking_type'])) {
                $bookingType = sanitize($data['booking_type']);
                if (!in_array($bookingType, $bookingTypes)) {
                    sendError('Invalid booking type', 400);
                }
                $updates[] = 'booking_type = :booking_type';
                $params[':booking_type'] = $bookingType;
            }

            if (isset($data['status'])) {
                $status = $data['status'];
                if (!in_array($status, ['pending', 'confirmed', 'completed', 'cancelled', 'late'])) {
                    sendError('Invalid status', 400);
                }
                $updates[] = 'status = :status';
                $params[':status'] = $status;
            }

            if (isset($data['booking_date'])) {
                $bookingDate = sanitize($data['booking_date']);
                $bookingTimestamp = strtotime($bookingDate);
                if ($bookingTimestamp === false) {
                    sendError('Invalid booking date format', 400);
                }
                $updates[] = 'booking_date = :booking_date';
                $params[':booking_date'] = $bookingDate;
            }

            if (isset($data['notes'])) {
                $updates[] = 'notes = :notes';
                $params[':notes'] = !empty($data['notes']) ? sanitize($data['notes']) : null;
            }

            if (empty($updates)) {
                sendError('No fields to update', 400);
            }

            $updates[] = 'updated_at = CURRENT_TIMESTAMP';
            $updateClause = implode(', ', $updates);

            $stmt = $pdo->prepare("
                UPDATE bookings 
                SET {$updateClause}
                WHERE id = :id
            ");

            $stmt->execute($params);

            sendSuccess(['message' => 'تم تحديث الحجز بنجاح']);
            break;

        /**
         * DELETE bookings.php?action=delete&id=123
         * Deletes a booking
         */
        case 'delete':
            requireMethod('DELETE');
            $id = getQueryParam('id');
            if (!$id) {
                sendError('Booking ID is required', 400);
            }

            // Check if booking exists
            $checkStmt = $pdo->prepare("SELECT id FROM bookings WHERE id = :id");
            $checkStmt->execute([':id' => intval($id)]);
            if (!$checkStmt->fetch()) {
                sendError('Booking not found', 404);
            }

            $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = :id");
            $stmt->execute([':id' => intval($id)]);

            sendSuccess(['message' => 'تم حذف الحجز بنجاح']);
            break;

        /**
         * GET bookings.php?action=calendar
         * Returns bookings formatted for FullCalendar.js
         */
        case 'calendar':
            $dateFrom = getQueryParam('start'); // FullCalendar start date
            $dateTo = getQueryParam('end');     // FullCalendar end date

            $where = ['1=1'];
            $params = [];

            if ($dateFrom) {
                $where[] = 'DATE(b.booking_date) >= :date_from';
                $params[':date_from'] = $dateFrom;
            }

            if ($dateTo) {
                $where[] = 'DATE(b.booking_date) <= :date_to';
                $params[':date_to'] = $dateTo;
            }

            $whereClause = implode(' AND ', $where);

            $stmt = $pdo->prepare("
                SELECT 
                    b.id,
                    b.booking_date,
                    b.booking_type,
                    b.status,
                    c.name AS customer_name,
                    i.invoice_number
                FROM bookings b
                LEFT JOIN customers c ON b.customer_id = c.id
                LEFT JOIN invoices i ON b.invoice_id = i.id
                WHERE {$whereClause}
                ORDER BY b.booking_date ASC
            ");

            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $events = [];
            foreach ($rows as $row) {
                // Determine color based on status
                $color = '#3498db'; // default blue
                if ($row['status'] === 'completed') {
                    $color = '#27ae60'; // green
                } elseif ($row['status'] === 'cancelled') {
                    $color = '#95a5a6'; // gray
                } elseif ($row['status'] === 'late') {
                    $color = '#e74c3c'; // red
                } elseif ($row['status'] === 'confirmed') {
                    $color = '#f39c12'; // orange
                }

                // Format title
                $title = $row['customer_name'] . ' - ' . $row['booking_type'];

                $events[] = [
                    'id' => intval($row['id']),
                    'title' => $title,
                    'start' => $row['booking_date'],
                    'color' => $color,
                    'extendedProps' => [
                        'booking_id' => intval($row['id']),
                        'customer_name' => $row['customer_name'],
                        'booking_type' => $row['booking_type'],
                        'status' => $row['status'],
                        'invoice_number' => $row['invoice_number']
                    ]
                ];
            }

            sendSuccess($events);
            break;

        /**
         * GET bookings.php?action=stats
         * Returns booking statistics
         */
        case 'stats':
            $today = date('Y-m-d');
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd = date('Y-m-d', strtotime('sunday this week'));

            // Today's bookings count - Use CURDATE() for accurate server date comparison
            $todayStmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM bookings
                WHERE DATE(booking_date) = CURDATE()
            ");
            $todayStmt->execute();
            $todayData = $todayStmt->fetch(PDO::FETCH_ASSOC);

            // Week's bookings count
            $weekStmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM bookings
                WHERE DATE(booking_date) >= DATE(:week_start)
                  AND DATE(booking_date) <= DATE(:week_end)
            ");
            $weekStmt->execute([
                ':week_start' => $weekStart,
                ':week_end' => $weekEnd
            ]);
            $weekData = $weekStmt->fetch(PDO::FETCH_ASSOC);

            // Cancelled count
            $cancelledStmt = $pdo->query("
                SELECT COUNT(*) as count
                FROM bookings
                WHERE status = 'cancelled'
            ");
            $cancelledData = $cancelledStmt->fetch(PDO::FETCH_ASSOC);

            // Late count (past bookings with status pending or confirmed)
            $lateStmt = $pdo->query("
                SELECT COUNT(*) as count
                FROM bookings
                WHERE booking_date < NOW()
                  AND status IN ('pending', 'confirmed')
            ");
            $lateData = $lateStmt->fetch(PDO::FETCH_ASSOC);

            $stats = [
                'today_count' => intval($todayData['count'] ?? 0),
                'week_count' => intval($weekData['count'] ?? 0),
                'cancelled_count' => intval($cancelledData['count'] ?? 0),
                'late_count' => intval($lateData['count'] ?? 0)
            ];

            sendSuccess($stats);
            break;

        default:
            sendError('Unknown action', 400);
            break;
    }

} catch (Exception $e) {
    logError('Bookings API Error', ['message' => $e->getMessage(), 'action' => $action]);
    sendError('Failed to process request: ' . $e->getMessage(), 500);
}


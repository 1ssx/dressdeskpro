<?php
/**
 * Double Booking Checker
 * 
 * منع حجز نفس الفستان لفترات متداخلة
 * - التحقق من توفر المنتج في فترة معينة
 * - كشف الحجوزات المتعارضة
 * - دعم استثناء فاتورة معينة (للتعديل)
 */

class DoubleBookingChecker {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * التحقق من توفر المنتج في فترة معينة
     * 
     * @param int $productId معرف المنتج
     * @param string $collectionDate تاريخ الاستلام (بداية الفترة)
     * @param string $returnDate تاريخ الإرجاع (نهاية الفترة)
     * @param int|null $excludeInvoiceId استثناء فاتورة معينة (للتعديل)
     * @return array ['available' => bool, 'conflicts' => array]
     */
    public function checkAvailability($productId, $collectionDate, $returnDate, $excludeInvoiceId = null) {
        try {
            // التحقق من صحة التواريخ
            if (empty($collectionDate) || empty($returnDate)) {
                return [
                    'available' => true,
                    'conflicts' => [],
                    'message' => 'التواريخ غير محددة'
                ];
            }
            
            // التحقق من أن تاريخ الإرجاع بعد تاريخ الاستلام
            if (strtotime($returnDate) <= strtotime($collectionDate)) {
                return [
                    'available' => false,
                    'conflicts' => [],
                    'message' => 'تاريخ الإرجاع يجب أن يكون بعد تاريخ الاستلام'
                ];
            }
            
            // البحث عن الحجوزات المتعارضة
            $conflicts = $this->getConflictingBookings($productId, $collectionDate, $returnDate, $excludeInvoiceId);
            
            $available = empty($conflicts);
            
            $message = $available 
                ? 'الفستان متاح في هذه الفترة' 
                : 'الفستان محجوز في هذه الفترة';
            
            return [
                'available' => $available,
                'conflicts' => $conflicts,
                'message' => $message,
                'conflict_count' => count($conflicts)
            ];
            
        } catch (Exception $e) {
            error_log("Double Booking Check Error: " . $e->getMessage());
            return [
                'available' => false,
                'conflicts' => [],
                'message' => 'خطأ في التحقق من التوفر: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * جلب الحجوزات المتعارضة
     */
    public function getConflictingBookings($productId, $collectionDate, $returnDate, $excludeInvoiceId = null) {
        // التحقق من وجود المنتج
        if (!$this->productExists($productId)) {
            return [];
        }
        
        $sql = "
            SELECT 
                i.id as invoice_id,
                i.invoice_number,
                i.invoice_status,
                i.collection_date,
                i.return_date,
                i.wedding_date,
                c.name as customer_name,
                c.phone_1 as customer_phone,
                ii.item_name as dress_name
            FROM invoice_items ii
            JOIN invoices i ON ii.invoice_id = i.id
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE ii.product_id = ?
              AND i.deleted_at IS NULL
              AND i.invoice_status IN ('reserved', 'out_with_customer')
              AND i.operation_type IN ('rent', 'design-rent')
              AND i.collection_date IS NOT NULL
              AND i.return_date IS NOT NULL
              AND (
                  -- الفترة الجديدة تبدأ أثناء فترة موجودة
                  (? >= i.collection_date AND ? < i.return_date)
                  OR
                  -- الفترة الجديدة تنتهي أثناء فترة موجودة
                  (? > i.collection_date AND ? <= i.return_date)
                  OR
                  -- الفترة الجديدة تحيط بالفترة الموجودة
                  (? <= i.collection_date AND ? >= i.return_date)
              )
        ";
        
        $params = [
            $productId,
            $collectionDate, $collectionDate, // للشرط الأول
            $returnDate, $returnDate,         // للشرط الثاني
            $collectionDate, $returnDate      // للشرط الثالث
        ];
        
        // استثناء فاتورة معينة (عند التعديل)
        if ($excludeInvoiceId) {
            $sql .= " AND i.id != ?";
            $params[] = $excludeInvoiceId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * التحقق من توفر المنتج في فترة معينة (نسخة مبسطة)
     */
    public function isAvailable($productId, $startDate, $endDate, $excludeInvoiceId = null) {
        $result = $this->checkAvailability($productId, $startDate, $endDate, $excludeInvoiceId);
        return $result['available'];
    }
    
    /**
     * التحقق من توفر عدة منتجات في نفس الفترة
     */
    public function checkMultipleProducts($productIds, $collectionDate, $returnDate, $excludeInvoiceId = null) {
        $results = [];
        $allAvailable = true;
        
        foreach ($productIds as $productId) {
            $check = $this->checkAvailability($productId, $collectionDate, $returnDate, $excludeInvoiceId);
            $results[$productId] = $check;
            
            if (!$check['available']) {
                $allAvailable = false;
            }
        }
        
        return [
            'all_available' => $allAvailable,
            'products' => $results
        ];
    }
    
    /**
     * جلب الفترات المحجوزة لمنتج معين
     */
    public function getProductBookedPeriods($productId, $startDate = null, $endDate = null) {
        $sql = "
            SELECT 
                i.id as invoice_id,
                i.invoice_number,
                i.collection_date,
                i.return_date,
                i.invoice_status,
                c.name as customer_name
            FROM invoice_items ii
            JOIN invoices i ON ii.invoice_id = i.id
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE ii.product_id = ?
              AND i.deleted_at IS NULL
              AND i.invoice_status IN ('reserved', 'out_with_customer')
              AND i.operation_type IN ('rent', 'design-rent')
              AND i.collection_date IS NOT NULL
              AND i.return_date IS NOT NULL
        ";
        
        $params = [$productId];
        
        if ($startDate) {
            $sql .= " AND i.return_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND i.collection_date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY i.collection_date ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * جلب الأيام المتوفرة لمنتج في شهر معين
     * (للتقويم)
     */
    public function getProductAvailabilityCalendar($productId, $year, $month) {
        $firstDay = date('Y-m-01', strtotime("$year-$month-01"));
        $lastDay = date('Y-m-t', strtotime("$year-$month-01"));
        
        $bookedPeriods = $this->getProductBookedPeriods($productId, $firstDay, $lastDay);
        
        // إنشاء array بجميع أيام الشهر
        $daysInMonth = date('t', strtotime($firstDay));
        $calendar = [];
        
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = date('Y-m-d', strtotime("$year-$month-$day"));
            $calendar[$date] = [
                'date' => $date,
                'available' => true,
                'bookings' => []
            ];
        }
        
        // تحديد الأيام المحجوزة
        foreach ($bookedPeriods as $period) {
            $start = strtotime($period['collection_date']);
            $end = strtotime($period['return_date']);
            
            for ($current = $start; $current <= $end; $current = strtotime('+1 day', $current)) {
                $dateKey = date('Y-m-d', $current);
                if (isset($calendar[$dateKey])) {
                    $calendar[$dateKey]['available'] = false;
                    $calendar[$dateKey]['bookings'][] = [
                        'invoice_number' => $period['invoice_number'],
                        'customer_name' => $period['customer_name'],
                        'status' => $period['invoice_status']
                    ];
                }
            }
        }
        
        return $calendar;
    }
    
    /**
     * التحقق من وجود المنتج
     */
    private function productExists($productId) {
        $stmt = $this->pdo->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * التحقق من فاتورة كاملة (جميع المنتجات)
     */
    public function validateInvoice($invoiceId, $collectionDate, $returnDate) {
        // جلب جميع المنتجات في الفاتورة
        $stmt = $this->pdo->prepare("
            SELECT product_id, item_name
            FROM invoice_items
            WHERE invoice_id = ? AND product_id IS NOT NULL
        ");
        $stmt->execute([$invoiceId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            return [
                'valid' => true,
                'message' => 'لا توجد منتجات مرتبطة للتحقق منها'
            ];
        }
        
        $conflicts = [];
        
        foreach ($items as $item) {
            $check = $this->checkAvailability(
                $item['product_id'],
                $collectionDate,
                $returnDate,
                $invoiceId // استثناء هذه الفاتورة نفسها
            );
            
            if (!$check['available']) {
                $conflicts[] = [
                    'product_id' => $item['product_id'],
                    'item_name' => $item['item_name'],
                    'conflicts' => $check['conflicts']
                ];
            }
        }
        
        $valid = empty($conflicts);
        
        return [
            'valid' => $valid,
            'conflicts' => $conflicts,
            'message' => $valid 
                ? 'جميع المنتجات متوفرة في هذه الفترة'
                : 'بعض المنتجات محجوزة في هذه الفترة'
        ];
    }
}

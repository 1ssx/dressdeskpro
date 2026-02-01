<?php
/**
 * Payment Manager
 * 
 * إدارة المدفوعات المتعددة للفواتير
 * - إضافة دفعات جديدة
 * - حساب الإجماليات
 * - تحديث حالة الدفع تلقائياً
 * - إدارة المرتجعات والغرامات
 */

class PaymentManager {
    private $pdo;
    
    const TYPE_PAYMENT = 'payment';
    const TYPE_REFUND = 'refund';
    const TYPE_PENALTY = 'penalty';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * إضافة دفعة جديدة
     */
    public function addPayment($invoiceId, $amount, $method, $type = self::TYPE_PAYMENT, $notes = null, $userId = null, $paymentDate = null) {
        try {
            // التحقق من وجود الفاتورة
            $invoice = $this->getInvoice($invoiceId);
            if (!$invoice) {
                throw new Exception("الفاتورة غير موجودة");
            }
            
            // التحقق من الفاتورة ليست ملغاة
            if ($invoice['invoice_status'] === 'canceled') {
                throw new Exception("لا يمكن إضافة دفعات لفاتورة ملغاة");
            }
            
            // التحقق من المبلغ
            if ($amount <= 0) {
                throw new Exception("المبلغ يجب أن يكون أكبر من صفر");
            }
            
            // التحقق من عدم تجاوز المبلغ المتبقي (للدفعات فقط)
            if ($type === self::TYPE_PAYMENT) {
                $currentRemaining = $this->calculateRemainingBalance($invoiceId);
                // Note: calculateRemainingBalance currently handles legacy deposit correctly.
                // But for the NEW payment, we want to ensure we don't 'lose' the legacy deposit when we start adding rows to 'payments'.
                
                if ($amount > $currentRemaining + 0.01) { // margin for floating point
                    throw new Exception("المبلغ أكبر من المبلغ المتبقي ({$currentRemaining} ريال)");
                }
            }
            
            $this->pdo->beginTransaction();

            // ✨ CRITICAL FIX: Transfer 'deposit_amount' to 'payments' table if this is the first payment
            // Check if there are existing payments
            $checkStmt = $this->pdo->prepare("SELECT COUNT(*) FROM payments WHERE invoice_id = ?");
            $checkStmt->execute([$invoiceId]);
            $count = $checkStmt->fetchColumn();

            if ($count == 0 && floatval($invoice['deposit_amount'] ?? 0) > 0) {
                // This is the first payment being added, but there is a legacy deposit.
                // We MUST migrate the legacy deposit to the payments table first, 
                // otherwise calculateTotalPaid() will stop counting the legacy deposit once a new payment exists.
                
                $stmtMigrate = $this->pdo->prepare("
                    INSERT INTO payments (
                        invoice_id, payment_date, amount, method, type, notes, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtMigrate->execute([
                    $invoiceId,
                    $invoice['invoice_date'] ?: date('Y-m-d H:i:s'), // Use invoice date as payment date
                    $invoice['deposit_amount'],
                    'cash', // Assumption: Legacy deposits were cash
                    'payment',
                    'عربون (تم نقله تلقائياً)',
                    $invoice['created_by'] ?? ($userId ?? ($_SESSION['user_id'] ?? null))
                ]);
                // We don't need to log this migration explicitly as it's an internal fix, 
                // but the payment count will now be 1, so the logic below works correctly.
            }
            
            // إضافة الدفعة الجديدة
            $stmt = $this->pdo->prepare("
                INSERT INTO payments (
                    invoice_id, payment_date, amount, method, type, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $invoiceId,
                $paymentDate ?? date('Y-m-d H:i:s'),
                $amount,
                $method,
                $type,
                $notes,
                $userId ?? ($_SESSION['user_id'] ?? null)
            ]);
            
            $paymentId = $this->pdo->lastInsertId();
            
            // تحديث حالة الدفع في الفاتورة (يتم تلقائياً عبر Trigger، لكن نتأكد)
            $this->updateInvoicePaymentStatus($invoiceId);
            
            // تسجيل في store_logs
            $this->logAction(
                $userId ?? ($_SESSION['user_id'] ?? null),
                'add_payment',
                'payment',
                $paymentId,
                "إضافة دفعة بمبلغ {$amount} ريال - نوع: {$type} - فاتورة رقم: {$invoice['invoice_number']}"
            );
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'تم إضافة الدفعة بنجاح',
                'payment_id' => $paymentId,
                'new_remaining' => $this->calculateRemainingBalance($invoiceId)
            ];
            
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
    
    /**
     * حذف دفعة (يتطلب صلاحيات عالية)
     */
    public function deletePayment($paymentId, $userId, $reason) {
        try {
            // جلب الدفعة
            $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE id = ?");
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                throw new Exception("الدفعة غير موجودة");
            }
            
            // التحقق من الصلاحيات (فقط admin يمكنه حذف الدفعات)
            if (!$this->canDeletePayment($userId)) {
                throw new Exception("ليس لديك صلاحية حذف الدفعات");
            }
            
            $this->pdo->beginTransaction();
            
            // حذف الدفعة
            $stmt = $this->pdo->prepare("DELETE FROM payments WHERE id = ?");
            $stmt->execute([$paymentId]);
            
            // تحديث حالة الدفع في الفاتورة
            $this->updateInvoicePaymentStatus($payment['invoice_id']);
            
            // تسجيل في store_logs
            $this->logAction(
                $userId,
                'delete_payment',
                'payment',
                $paymentId,
                "حذف دفعة بمبلغ {$payment['amount']} ريال - السبب: {$reason} - فاتورة ID: {$payment['invoice_id']}"
            );
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'تم حذف الدفعة بنجاح'
            ];
            
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
    
    /**
     * جلب جميع دفعات فاتورة معينة
     * يشمل العربون القديم كسجل افتراضي للتوافق العكسي
     */
    public function getInvoicePayments($invoiceId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.*,
                u.full_name as created_by_name
            FROM payments p
            LEFT JOIN users u ON p.created_by = u.id
            WHERE p.invoice_id = ?
            ORDER BY p.payment_date DESC, p.created_at DESC
        ");
        $stmt->execute([$invoiceId]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // إذا لم توجد دفعات، نتحقق من العربون القديم
        if (empty($payments)) {
            $invoice = $this->getInvoice($invoiceId);
            if ($invoice && isset($invoice['deposit_amount']) && floatval($invoice['deposit_amount']) > 0) {
                // إنشاء سجل دفعة افتراضي من العربون القديم
                $payments[] = [
                    'id' => 'legacy_deposit',
                    'invoice_id' => $invoiceId,
                    'payment_date' => $invoice['invoice_date'] ?: $invoice['created_at'],
                    'amount' => floatval($invoice['deposit_amount']),
                    'method' => 'cash',
                    'type' => 'payment',
                    'notes' => 'عربون (من النظام القديم)',
                    'created_by' => $invoice['created_by'],
                    'created_by_name' => null,
                    'is_legacy' => true
                ];
            }
        }
        
        return $payments;
    }
    
    /**
     * حساب إجمالي المدفوعات
     * يشمل:
     * - المدفوعات من جدول payments
     * - العربون القديم من جدول invoices (للتوافق العكسي)
     */
    public function calculateTotalPaid($invoiceId) {
        // 1. جلب المدفوعات من جدول payments
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(
                CASE 
                    WHEN type = 'payment' THEN amount
                    WHEN type = 'refund' THEN -amount
                    ELSE 0
                END
            ), 0) as total_paid
            FROM payments
            WHERE invoice_id = ?
        ");
        $stmt->execute([$invoiceId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $paymentsTotal = floatval($result['total_paid'] ?? 0);
        
        // 2. التحقق من العربون القديم في جدول الفواتير (للتوافق العكسي)
        // إذا لم توجد أي دفعات في جدول payments، نضيف العربون القديم
        if ($paymentsTotal == 0) {
            $invoice = $this->getInvoice($invoiceId);
            if ($invoice && isset($invoice['deposit_amount']) && floatval($invoice['deposit_amount']) > 0) {
                $legacyDeposit = floatval($invoice['deposit_amount']);
                return $legacyDeposit;
            }
        }
        
        return $paymentsTotal;
    }

    /**
     * حساب إجمالي الغرامات
     */
    public function calculateTotalPenalties($invoiceId) {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_penalties
            FROM payments
            WHERE invoice_id = ? AND type = 'penalty'
        ");
        $stmt->execute([$invoiceId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return floatval($result['total_penalties'] ?? 0);
    }
    
    /**
     * حساب المبلغ المتبقي
     */
    public function calculateRemainingBalance($invoiceId) {
        $invoice = $this->getInvoice($invoiceId);
        if (!$invoice) {
            return 0;
        }
        
        $totalPrice = floatval($invoice['total_price']);
        $totalPaid = $this->calculateTotalPaid($invoiceId);
        $totalPenalties = $this->calculateTotalPenalties($invoiceId);
        
        // المتبقي = (السعر الأصلي + الغرامات) - المدفوع الصافي
        return max(0, ($totalPrice + $totalPenalties) - $totalPaid);
    }
    
    /**
     * تحديث حالة الدفع في الفاتورة
     */
    public function updateInvoicePaymentStatus($invoiceId) {
        $invoice = $this->getInvoice($invoiceId);
        if (!$invoice) {
            return false;
        }
        
        $totalPrice = floatval($invoice['total_price']);
        $totalPaid = $this->calculateTotalPaid($invoiceId);
        $totalPenalties = $this->calculateTotalPenalties($invoiceId);
        
        $remaining = max(0, ($totalPrice + $totalPenalties) - $totalPaid);
        
        // تحديد حالة الدفع
        $paymentStatus = 'unpaid';
        if ($remaining <= 0.01) { // paid
            $paymentStatus = 'paid';
        } elseif ($totalPaid > 0) { // partial
            $paymentStatus = 'partial';
        }
        
        // تحديث الفاتورة
        $stmt = $this->pdo->prepare("
            UPDATE invoices 
            SET 
                remaining_balance = ?,
                payment_status = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$remaining, $paymentStatus, $invoiceId]);
        
        return true;
    }
    
    /**
     * ملخص المدفوعات لفاتورة معينة
     * يشمل العربون القديم للتوافق العكسي
     */
    public function getPaymentSummary($invoiceId) {
        $invoice = $this->getInvoice($invoiceId);
        if (!$invoice) {
            return null;
        }
        
        // حساب التفاصيل من جدول payments
        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END), 0) as total_payments,
                COALESCE(SUM(CASE WHEN type = 'refund' THEN amount ELSE 0 END), 0) as total_refunds,
                COALESCE(SUM(CASE WHEN type = 'penalty' THEN amount ELSE 0 END), 0) as total_penalties,
                COUNT(CASE WHEN type = 'payment' THEN 1 END) as payments_count,
                COUNT(CASE WHEN type = 'refund' THEN 1 END) as refunds_count,
                COUNT(CASE WHEN type = 'penalty' THEN 1 END) as penalties_count
            FROM payments
            WHERE invoice_id = ?
        ");
        $stmt->execute([$invoiceId]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalPrice = floatval($invoice['total_price']);
        $totalPayments = floatval($summary['total_payments']);
        $totalRefunds = floatval($summary['total_refunds']);
        $totalPenalties = floatval($summary['total_penalties']);
        $paymentsCount = intval($summary['payments_count']);
        
        // التوافق العكسي: إذا لم توجد دفعات في جدول payments، نستخدم العربون القديم
        $legacyDeposit = 0;
        if ($totalPayments == 0 && isset($invoice['deposit_amount']) && floatval($invoice['deposit_amount']) > 0) {
            $legacyDeposit = floatval($invoice['deposit_amount']);
            $totalPayments = $legacyDeposit;
            // نعتبره كدفعة واحدة قديمة
            $paymentsCount = 1;
        }
        
        $netPaid = $totalPayments - $totalRefunds; // Exclude penalties from paid
        $remaining = max(0, ($totalPrice + $totalPenalties) - $netPaid);
        
        // تحديد حالة الدفع الفعلية بناءً على الحسابات
        $actualPaymentStatus = 'unpaid';
        if ($remaining <= 0.01) {
            $actualPaymentStatus = 'paid';
        } elseif ($netPaid > 0) {
            $actualPaymentStatus = 'partial';
        }
        
        return [
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoice['invoice_number'],
            'total_price' => $totalPrice,
            'total_payments' => $totalPayments,
            'total_refunds' => $totalRefunds,
            'total_penalties' => $totalPenalties,
            'net_paid' => $netPaid,
            'remaining_balance' => $remaining,
            'payment_status' => $actualPaymentStatus, // استخدام الحالة المحسوبة
            'payments_count' => $paymentsCount,
            'refunds_count' => intval($summary['refunds_count']),
            'penalties_count' => intval($summary['penalties_count']),
            'has_legacy_deposit' => $legacyDeposit > 0 // علامة للتوافق العكسي
        ];
    }
    
    /**
     * إضافة مرتجع (عكس الدفعة)
     */
    public function addRefund($invoiceId, $amount, $reason, $userId = null) {
        return $this->addPayment(
            $invoiceId,
            $amount,
            'cash', // default method
            self::TYPE_REFUND,
            "مرتجع - {$reason}",
            $userId
        );
    }
    
    /**
     * إضافة غرامة (ضرر، تأخير، إلخ)
     */
    public function addPenalty($invoiceId, $amount, $reason, $userId = null) {
        return $this->addPayment(
            $invoiceId,
            $amount,
            'cash', // default method
            self::TYPE_PENALTY,
            "غرامة - {$reason}",
            $userId
        );
    }
    
    /**
     * التحقق من صلاحية حذف الدفعات
     */
    private function canDeletePayment($userId) {
        if (!$userId) {
            return false;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT role FROM users WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user && $user['role'] === 'admin';
    }
    
    /**
     * جلب بيانات الفاتورة
     */
    private function getInvoice($invoiceId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM invoices WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$invoiceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * تسجيل في store_logs
     */
    private function logAction($userId, $actionType, $relatedType, $relatedId, $description) {
        if (!file_exists(__DIR__ . '/logger.php')) {
            return; // fallback if logger doesn't exist yet
        }
        require_once __DIR__ . '/logger.php';
        $logger = new StoreLogger($this->pdo);
        $logger->log($userId, $actionType, $relatedType, $relatedId, $description);
    }
    
    /**
     * تقرير المدفوعات لفترة معينة
     */
    public function getPaymentsReport($startDate = null, $endDate = null, $type = null) {
        $sql = "
            SELECT 
                p.*,
                i.invoice_number,
                i.total_price as invoice_total,
                c.name as customer_name,
                u.full_name as created_by_name
            FROM payments p
            JOIN invoices i ON p.invoice_id = i.id
            LEFT JOIN customers c ON i.customer_id = c.id
            LEFT JOIN users u ON p.created_by = u.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($startDate) {
            $sql .= " AND DATE(p.payment_date) >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND DATE(p.payment_date) <= ?";
            $params[] = $endDate;
        }
        
        if ($type) {
            $sql .= " AND p.type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY p.payment_date DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

<?php
/**
 * Invoice Lifecycle Manager
 * 
 * إدارة دورة حياة الفاتورة من البداية للنهاية
 * - تغيير الحالات (Status Transitions)
 * - التسليم والإرجاع (Delivery & Return)
 * - الإقفال والإلغاء (Close & Cancel)
 * - التحقق من صلاحية التغييرات (Validation)
 */

class InvoiceLifecycleManager {
    private $pdo;
    
    // حالات الفاتورة المسموح بها
    const STATUS_DRAFT = 'draft';
    const STATUS_RESERVED = 'reserved';
    const STATUS_OUT_WITH_CUSTOMER = 'out_with_customer';
    const STATUS_RETURNED = 'returned';
    const STATUS_CLOSED = 'closed';
    const STATUS_CANCELED = 'canceled';
    
    // انتقالات الحالات المسموح بها
    // State Machine: Strict transitions only
    private $allowed_transitions = [
        self::STATUS_DRAFT => [self::STATUS_RESERVED, self::STATUS_CANCELED],
        self::STATUS_RESERVED => [self::STATUS_OUT_WITH_CUSTOMER, self::STATUS_CANCELED],
        self::STATUS_OUT_WITH_CUSTOMER => [self::STATUS_RETURNED], // لا يمكن الإقفال مباشرة - يجب الإرجاع أولاً
        self::STATUS_RETURNED => [self::STATUS_CLOSED, self::STATUS_OUT_WITH_CUSTOMER], // يمكن إعادة التسليم أو الإقفال
        self::STATUS_CLOSED => [], // لا يمكن تغيير الفاتورة المقفلة
        self::STATUS_CANCELED => [] // لا يمكن تغيير الفاتورة الملغاة
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * تغيير حالة الفاتورة
     */
    public function changeStatus($invoiceId, $newStatus, $userId, $notes = null) {
        try {
            // جلب الفاتورة الحالية
            $invoice = $this->getInvoice($invoiceId);
            if (!$invoice) {
                throw new Exception("الفاتورة غير موجودة");
            }
            
            $currentStatus = $invoice['invoice_status'];
            
            // التحقق من صلاحية الانتقال
            if (!$this->validateTransition($currentStatus, $newStatus)) {
                throw new Exception("لا يمكن تغيير حالة الفاتورة من {$currentStatus} إلى {$newStatus}");
            }
            
            $this->pdo->beginTransaction();
            
            // تحديث حالة الفاتورة
            $stmt = $this->pdo->prepare("
                UPDATE invoices 
                SET invoice_status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $invoiceId]);
            
            // تسجيل التغيير في السجل
            $this->logStatusChange(
                $invoiceId,
                $currentStatus,
                $newStatus,
                $invoice['payment_status'],
                $invoice['payment_status'],
                $userId,
                $notes
            );
            
            // تسجيل في store_logs
            $this->logAction(
                $userId,
                'change_invoice_status',
                'invoice',
                $invoiceId,
                "تغيير حالة الفاتورة من {$currentStatus} إلى {$newStatus}"
            );
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'تم تغيير حالة الفاتورة بنجاح',
                'old_status' => $currentStatus,
                'new_status' => $newStatus
            ];
            
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
    
    /**
     * تأكيد تسليم الفستان للعميل
     */
    public function deliverInvoice($invoiceId, $userId, $notes = null) {
        try {
            $invoice = $this->getInvoice($invoiceId);
            if (!$invoice) {
                throw new Exception("الفاتورة غير موجودة");
            }
            
            // التحقق من الحالة المطلوبة
            if ($invoice['invoice_status'] !== self::STATUS_RESERVED) {
                throw new Exception("يجب أن تكون الفاتورة في حالة 'محجوز' لتأكيد التسليم");
            }
            
            $this->pdo->beginTransaction();
            
            // تحديث بيانات التسليم
            $stmt = $this->pdo->prepare("
                UPDATE invoices 
                SET invoice_status = ?,
                    delivered_at = CURRENT_TIMESTAMP,
                    delivered_by = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([self::STATUS_OUT_WITH_CUSTOMER, $userId, $invoiceId]);
            
            // تسجيل التغيير
            $this->logStatusChange(
                $invoiceId,
                self::STATUS_RESERVED,
                self::STATUS_OUT_WITH_CUSTOMER,
                $invoice['payment_status'],
                $invoice['payment_status'],
                $userId,
                $notes ?? 'تم تسليم الفستان للعميل'
            );
            
            // تحديث حالة المنتجات المرتبطة
            $this->updateLinkedProductsStatus($invoiceId, 'rented');
            
            // تسجيل في store_logs
            $this->logAction(
                $userId,
                'deliver_invoice',
                'invoice',
                $invoiceId,
                "تم تسليم الفستان للعميل - فاتورة رقم: {$invoice['invoice_number']}"
            );
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'تم تأكيد تسليم الفستان بنجاح',
                'delivered_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
    
    /**
     * تأكيد إرجاع الفستان من العميل
     */
    public function returnInvoice($invoiceId, $userId, $condition, $notes = null) {
        try {
            $invoice = $this->getInvoice($invoiceId);
            if (!$invoice) {
                throw new Exception("الفاتورة غير موجودة");
            }
            
            // التحقق من الحالة المطلوبة
            if ($invoice['invoice_status'] !== self::STATUS_OUT_WITH_CUSTOMER) {
                throw new Exception("يجب أن تكون الفاتورة في حالة 'مع العميل' لتأكيد الإرجاع");
            }
            
            // التحقق من صلاحية حالة الإرجاع
            $valid_conditions = ['excellent', 'good', 'needs_cleaning', 'damaged', 'missing_items'];
            if (!in_array($condition, $valid_conditions)) {
                throw new Exception("حالة الإرجاع غير صحيحة");
            }
            
            $this->pdo->beginTransaction();
            
            // تحديث بيانات الإرجاع
            $stmt = $this->pdo->prepare("
                UPDATE invoices 
                SET invoice_status = ?,
                    returned_at = CURRENT_TIMESTAMP,
                    returned_by = ?,
                    return_condition = ?,
                    return_notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                self::STATUS_RETURNED,
                $userId,
                $condition,
                $notes,
                $invoiceId
            ]);
            
            // تسجيل التغيير
            $this->logStatusChange(
                $invoiceId,
                self::STATUS_OUT_WITH_CUSTOMER,
                self::STATUS_RETURNED,
                $invoice['payment_status'],
                $invoice['payment_status'],
                $userId,
                "تم إرجاع الفستان - الحالة: {$condition}. " . ($notes ?? '')
            );
            
            // تحديث حالة المنتجات المرتبطة
            // إذا كانت الحالة ممتازة أو جيدة -> available
            // وإلا -> needs_cleaning أو out_of_stock حسب الحالة
            $newProductStatus = ($condition === 'excellent' || $condition === 'good') 
                ? 'available' 
                : 'out_of_stock';
            $this->updateLinkedProductsStatus($invoiceId, $newProductStatus);
            
            // تسجيل في store_logs
            $this->logAction(
                $userId,
                'return_invoice',
                'invoice',
                $invoiceId,
                "تم إرجاع الفستان - الحالة: {$condition} - فاتورة رقم: {$invoice['invoice_number']}"
            );
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'تم تأكيد إرجاع الفستان بنجاح',
                'returned_at' => date('Y-m-d H:i:s'),
                'condition' => $condition
            ];
            
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
    
    /**
     * إقفال الفاتورة (منتهية تماماً)
     */
    public function closeInvoice($invoiceId, $userId, $notes = null) {
        try {
            $invoice = $this->getInvoice($invoiceId);
            if (!$invoice) {
                throw new Exception("الفاتورة غير موجودة");
            }
            
            // التحقق من أن الفاتورة ليست مقفلة أو ملغاة مسبقاً
            if ($invoice['invoice_status'] === self::STATUS_CLOSED) {
                throw new Exception("الفاتورة مقفلة مسبقاً");
            }
            if ($invoice['invoice_status'] === self::STATUS_CANCELED) {
                throw new Exception("لا يمكن إقفال فاتورة ملغاة");
            }
            
            // التحقق من الشروط المطلوبة للإقفال
            // 1. يجب أن تكون مدفوعة بالكامل - نستخدم PaymentManager للحساب الفعلي
            $paymentManager = new PaymentManager($this->pdo);
            $remaining = $paymentManager->calculateRemainingBalance($invoiceId);
            
            if ($remaining > 0.01) {
                // توفير رسالة خطأ واضحة مع المبلغ المتبقي
                throw new Exception("لا يمكن إقفال الفاتورة، المبلغ المتبقي: " . number_format($remaining, 2) . " ريال");
            }
            
            // 2. التحقق من حالة الفاتورة المناسبة للإقفال
            $operationType = $invoice['operation_type'] ?? 'rent';
            $currentStatus = $invoice['invoice_status'];
            
            // للإيجار والتصميم-إيجار: يجب أن تكون في حالة 'مرتجع'
            if (in_array($operationType, ['rent', 'design-rent'])) {
                if ($currentStatus !== self::STATUS_RETURNED) {
                    // رسالة خطأ واضحة حسب الحالة الحالية
                    if ($currentStatus === self::STATUS_OUT_WITH_CUSTOMER) {
                        throw new Exception("لا يمكن إقفال الفاتورة - الفستان لا زال مع العميل، يجب تأكيد الإرجاع أولاً");
                    } elseif ($currentStatus === self::STATUS_RESERVED) {
                        throw new Exception("لا يمكن إقفال الفاتورة - لم يتم تسليم الفستان للعميل بعد");
                    } else {
                        throw new Exception("لا يمكن إقفال فاتورة الإيجار قبل إرجاع الفستان");
                    }
                }
            } else {
                // للبيع والتصميم-بيع: يمكن الإقفال من حالة 'محجوز' أو 'مرتجع'
                if (!in_array($currentStatus, [self::STATUS_RESERVED, self::STATUS_RETURNED, self::STATUS_OUT_WITH_CUSTOMER])) {
                    throw new Exception("لا يمكن إقفال الفاتورة من الحالة الحالية: " . $currentStatus);
                }
            }
            

            $this->pdo->beginTransaction();
            
            // إقفال الفاتورة
            $stmt = $this->pdo->prepare("
                UPDATE invoices 
                SET invoice_status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([self::STATUS_CLOSED, $invoiceId]);
            
            // تسجيل التغيير
            $this->logStatusChange(
                $invoiceId,
                $invoice['invoice_status'],
                self::STATUS_CLOSED,
                $invoice['payment_status'],
                $invoice['payment_status'],
                $userId,
                $notes ?? 'تم إقفال الفاتورة بشكل نهائي'
            );
            
            // فك قفل المنتجات المرتبطة (إذا لم تكن مباعة)
            if (!in_array($operationType, ['sale', 'design-sale'])) {
                $this->updateLinkedProductsStatus($invoiceId, 'available', true); // unlock
            }
            
            // تسجيل في store_logs
            $this->logAction(
                $userId,
                'close_invoice',
                'invoice',
                $invoiceId,
                "تم إقفال الفاتورة - رقم: {$invoice['invoice_number']}"
            );
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'تم إقفال الفاتورة بنجاح'
            ];
            
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
    
    /**
     * إلغاء الفاتورة (Soft Delete)
     */
    public function cancelInvoice($invoiceId, $userId, $reason) {
        try {
            $invoice = $this->getInvoice($invoiceId);
            if (!$invoice) {
                throw new Exception("الفاتورة غير موجودة");
            }
            
            // لا يمكن إلغاء فاتورة مقفلة
            if ($invoice['invoice_status'] === self::STATUS_CLOSED) {
                throw new Exception("لا يمكن إلغاء فاتورة مقفلة");
            }
            
            $this->pdo->beginTransaction();
            
            // إلغاء الفاتورة (Soft Delete)
            $stmt = $this->pdo->prepare("
                UPDATE invoices 
                SET invoice_status = ?,
                    deleted_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([self::STATUS_CANCELED, $invoiceId]);
            
            // تسجيل التغيير
            $this->logStatusChange(
                $invoiceId,
                $invoice['invoice_status'],
                self::STATUS_CANCELED,
                $invoice['payment_status'],
                $invoice['payment_status'],
                $userId,
                "تم إلغاء الفاتورة - السبب: {$reason}"
            );
            
            // فك قفل المنتجات المرتبطة
            $this->updateLinkedProductsStatus($invoiceId, 'available', true);
            
            // تسجيل في store_logs
            $this->logAction(
                $userId,
                'cancel_invoice',
                'invoice',
                $invoiceId,
                "تم إلغاء الفاتورة - رقم: {$invoice['invoice_number']} - السبب: {$reason}"
            );
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'تم إلغاء الفاتورة بنجاح'
            ];
            
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
    
    /**
     * التحقق من صلاحية الانتقال بين الحالات
     */
    private function validateTransition($from, $to) {
        if (!isset($this->allowed_transitions[$from])) {
            return false;
        }
        return in_array($to, $this->allowed_transitions[$from]);
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
     * تسجيل تغيير في سجل الحالات
     */
    private function logStatusChange($invoiceId, $statusFrom, $statusTo, $paymentFrom, $paymentTo, $userId, $notes) {
        $stmt = $this->pdo->prepare("
            INSERT INTO invoice_status_history (
                invoice_id, status_from, status_to, 
                payment_status_from, payment_status_to,
                changed_by, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $invoiceId,
            $statusFrom,
            $statusTo,
            $paymentFrom,
            $paymentTo,
            $userId,
            $notes
        ]);
    }
    
    /**
     * تسجيل في store_logs
     */
    private function logAction($userId, $actionType, $relatedType, $relatedId, $description) {
        require_once __DIR__ . '/logger.php';
        $logger = new StoreLogger($this->pdo);
        $logger->log($userId, $actionType, $relatedType, $relatedId, $description);
    }
    
    /**
     * تحديث حالة المنتجات المرتبطة بالفاتورة
     */
    private function updateLinkedProductsStatus($invoiceId, $newStatus, $unlock = false) {
        // جلب جميع المنتجات المرتبطة
        $stmt = $this->pdo->prepare("
            SELECT product_id 
            FROM invoice_items 
            WHERE invoice_id = ? AND product_id IS NOT NULL
        ");
        $stmt->execute([$invoiceId]);
        $products = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($products)) {
            return;
        }
        
        // تحديث حالة المنتجات
        $placeholders = implode(',', array_fill(0, count($products), '?'));
        $sql = "UPDATE products SET status = ?";
        $params = [$newStatus];
        
        if ($unlock) {
            // Check if is_locked column exists (multi-tenant schema compatibility)
            try {
                $columnsStmt = $this->pdo->query("SHOW COLUMNS FROM products LIKE 'is_locked'");
                if ($columnsStmt->rowCount() > 0) {
                    $sql .= ", is_locked = 0";
                }
            } catch (Exception $e) {
                // Column doesn't exist - skip is_locked update
            }
        }
        
        $sql .= " WHERE id IN ($placeholders)";
        $params = array_merge($params, $products);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }
    
    /**
     * الحصول على سجل تغييرات الفاتورة
     */
    public function getInvoiceHistory($invoiceId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                h.*,
                u.full_name as changed_by_name
            FROM invoice_status_history h
            LEFT JOIN users u ON h.changed_by = u.id
            WHERE h.invoice_id = ?
            ORDER BY h.changed_at DESC
        ");
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

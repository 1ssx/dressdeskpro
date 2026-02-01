<?php
/**
 * Permissions Helper
 * 
 * إدارة الصلاحيات للمستخدمين في المتجر
 * - التحقق من صلاحيات الإجراءات
 * - صلاحيات حسب الدور (admin, manager, staff)
 */

class PermissionsManager {
    private $pdo;
    private $userId;
    private $userRole;
    
    // الأدوار
    const ROLE_ADMIN = 'admin';
    const ROLE_MANAGER = 'manager';
    const ROLE_STAFF = 'staff';
    
    public function __construct($pdo, $userId = null) {
        $this->pdo = $pdo;
        $this->userId = $userId ?? ($_SESSION['user_id'] ?? null);
        
        if ($this->userId) {
            $this->loadUserRole();
        }
    }
    
    /**
     * تحميل دور المستخدم
     */
    private function loadUserRole() {
        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->userRole = $user['role'] ?? null;
    }
    
    /**
     * التحقق من أن المستخدم Admin
     */
    public function isAdmin() {
        return $this->userRole === self::ROLE_ADMIN;
    }
    
    /**
     * التحقق من أن المستخدم Manager أو أعلى
     */
    public function isManagerOrAbove() {
        return in_array($this->userRole, [self::ROLE_ADMIN, self::ROLE_MANAGER]);
    }
    
    /**
     * التحقق من صلاحية إلغاء الفواتير
     */
    public function canCancelInvoice() {
        return $this->isManagerOrAbove();
    }
    
    /**
     * التحقق من صلاحية حذف الدفعات
     */
    public function canDeletePayment() {
        return $this->isAdmin();
    }
    
    /**
     * التحقق من صلاحية تعديل الدفعات السابقة
     */
    public function canEditPayment() {
        return $this->isAdmin();
    }
    
    /**
     * التحقق من صلاحية عرض تكلفة المنتجات
     */
    public function canViewProductCost() {
        return $this->isManagerOrAbove();
    }
    
    /**
     * التحقق من صلاحية عرض تقارير الأرباح
     */
    public function canViewProfitReports() {
        return $this->isManagerOrAbove();
    }
    
    /**
     * التحقق من صلاحية إضافة/تعديل المستخدمين
     */
    public function canManageUsers() {
        return $this->isAdmin();
    }
    
    /**
     * التحقق من صلاحية تعديل إعدادات المتجر
     */
    public function canManageStoreSettings() {
        return $this->isAdmin();
    }
    
    /**
     * التحقق من صلاحية حذف العملاء
     */
    public function canDeleteCustomer() {
        return $this->isManagerOrAbove();
    }
    
    /**
     * التحقق من صلاحية حذف المنتجات
     */
    public function canDeleteProduct() {
        return $this->isManagerOrAbove();
    }
    
    /**
     * التحقق من صلاحية تعديل المنتجات المقفلة
     */
    public function canEditLockedProduct() {
        return $this->isAdmin();
    }
    
    /**
     * التحقق من صلاحية إضافة مصروفات
     */
    public function canAddExpense() {
        return $this->isManagerOrAbove();
    }
    
    /**
     * التحقق من صلاحية حذف/تعديل المصروفات
     */
    public function canEditExpense() {
        return $this->isManagerOrAbove();
    }
    
    /**
     * التحقق من صلاحية عرض سجل العمليات (Logs)
     */
    public function canViewLogs() {
        return $this->isAdmin();
    }
    
    /**
     * التحقق من صلاحية تصدير البيانات
     */
    public function canExportData() {
        return $this->isManagerOrAbove();
    }
    
    /**
     * التحقق من صلاحية إقفال الفواتير
     */
    public function canCloseInvoice() {
        return $this->isManagerOrAbove();
    }
    
    /**
     * التحقق من صلاحية تأكيد التسليم
     */
    public function canDeliverInvoice() {
        // جميع المستخدمين يمكنهم تأكيد التسليم
        return $this->userId !== null;
    }
    
    /**
     * التحقق من صلاحية تأكيد الإرجاع
     */
    public function canReturnInvoice() {
        // جميع المستخدمين يمكنهم تأكيد الإرجاع
        return $this->userId !== null;
    }
    

    
    /**
     * التحقق من صلاحية إعطاء خصومات كبيرة
     * (مثلاً أكثر من 20% من السعر الأصلي)
     */
    public function canGiveLargeDiscount($discountPercent = 20) {
        if ($discountPercent <= 10) {
            return true; // الجميع يمكنهم إعطاء خصم حتى 10%
        }
        if ($discountPercent <= 20 && $this->isManagerOrAbove()) {
            return true; // Manager يمكنه حتى 20%
        }
        if ($this->isAdmin()) {
            return true; // Admin ليس له حد
        }
        return false;
    }
    
    /**
     * التحقق من صلاحية تغيير حالة الفاتورة
     */
    public function canChangeInvoiceStatus() {
        // الجميع يمكنهم تغيير الحالات العادية
        // لكن فقط Manager+ يمكنه إرجاع الحالة للخلف
        return true;
    }
    
    /**
     * التحقق من صلاحية إضافة مدفوعات
     */
    public function canAddPayment() {
        // جميع المستخدمين المسجلين يمكنهم إضافة مدفوعات
        return $this->userId !== null;
    }

    
    /**
     * قائمة بجميع الصلاحيات حسب الدور
     */
    public function getUserPermissions() {
        $permissions = [
            // الصلاحيات الأساسية (متاحة للجميع)
            'view_invoices' => true,
            'create_invoice' => true,
            'edit_invoice' => true,
            'view_customers' => true,
            'create_customer' => true,
            'edit_customer' => true,
            'view_products' => true,
            'create_product' => true,
            'edit_product' => true,
            'view_bookings' => true,
            'create_booking' => true,
            'edit_booking' => true,
            'add_payment' => true,
            'view_reports' => true,
            'deliver_invoice' => true,
            'return_invoice' => true,
            'give_discount_small' => true, // خصم حتى 10%
            
            // الصلاحيات المتوسطة (Manager+)
            'cancel_invoice' => $this->isManagerOrAbove(),
            'delete_customer' => $this->isManagerOrAbove(),
            'delete_product' => $this->isManagerOrAbove(),
            'view_product_cost' => $this->isManagerOrAbove(),
            'view_profit_reports' => $this->isManagerOrAbove(),
            'add_expense' => $this->isManagerOrAbove(),
            'edit_expense' => $this->isManagerOrAbove(),
            'give_discount_medium' => $this->isManagerOrAbove(), // خصم حتى 20%
            'close_invoice' => $this->isManagerOrAbove(),
            'export_data' => $this->isManagerOrAbove(),
            
            // الصلاحيات العالية (Admin فقط)
            'delete_payment' => $this->isAdmin(),
            'edit_payment' => $this->isAdmin(),
            'manage_users' => $this->isAdmin(),
            'manage_store_settings' => $this->isAdmin(),
            'edit_locked_product' => $this->isAdmin(),
            'view_logs' => $this->isAdmin(),
            'give_discount_large' => $this->isAdmin(), // خصم بدون حد
        ];
        
        return $permissions;
    }
    
    /**
     * التحقق من صلاحية معينة
     */
    public function can($permission) {
        $permissions = $this->getUserPermissions();
        return $permissions[$permission] ?? false;
    }
    
    /**
     * رفع استثناء إذا لم تكن هناك صلاحية
     */
    public function requirePermission($permission, $message = null) {
        if (!$this->can($permission)) {
            $defaultMessage = "ليس لديك صلاحية لهذا الإجراء";
            throw new Exception($message ?? $defaultMessage);
        }
    }
    
    /**
     * الحصول على اسم الدور بالعربية
     */
    public function getRoleNameArabic() {
        $roles = [
            self::ROLE_ADMIN => 'مدير',
            self::ROLE_MANAGER => 'مشرف',
            self::ROLE_STAFF => 'موظف'
        ];
        
        return $roles[$this->userRole] ?? 'غير معروف';
    }
    
    /**
     * التحقق من أن المستخدم يمكنه الوصول لبيانات مستخدم آخر
     */
    public function canAccessUserData($targetUserId) {
        // المستخدم يمكنه الوصول لبياناته الخاصة
        if ($this->userId == $targetUserId) {
            return true;
        }
        
        // Admin يمكنه الوصول لبيانات الجميع
        if ($this->isAdmin()) {
            return true;
        }
        
        // Manager يمكنه الوصول لبيانات Staff فقط
        if ($this->isManagerOrAbove()) {
            $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($targetUser && $targetUser['role'] === self::ROLE_STAFF) {
                return true;
            }
        }
        
        return false;
    }
}

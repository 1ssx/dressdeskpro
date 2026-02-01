<?php
/**
 * CustomerHelper.php
 * مساعد لإدارة العملاء وربطهم مع الفواتير تلقائياً
 */

class CustomerHelper {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * البحث عن عميل بواسطة رقم الهاتف
     * @param string $phone1 رقم الهاتف الأساسي
     * @param string $phone2 رقم الهاتف الثانوي (اختياري)
     * @return array|null بيانات العميل أو null
     */
    public function findCustomerByPhone($phone1, $phone2 = null) {
        // تنظيف أرقام الهواتف
        $phone1 = $this->cleanPhone($phone1);
        $phone2 = $this->cleanPhone($phone2);
        
        // البحث بالهاتف الأساسي أولاً
        $stmt = $this->pdo->prepare("
            SELECT * FROM customers 
            WHERE phone_1 = ? OR phone_2 = ?
            LIMIT 1
        ");
        $stmt->execute([$phone1, $phone1]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // إذا لم نجد، نبحث بالهاتف الثانوي
        if (!$customer && !empty($phone2)) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM customers 
                WHERE phone_1 = ? OR phone_2 = ?
                LIMIT 1
            ");
            $stmt->execute([$phone2, $phone2]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return $customer ?: null;
    }
    
    /**
     * إنشاء عميل جديد أو إرجاع العميل الموجود
     * @param array $data بيانات العميل
     * @return array بيانات العميل مع customer_id
     */
    public function createOrGetCustomer($data) {
        error_log("CustomerHelper: createOrGetCustomer started for phone: " . ($data['phone_1'] ?? 'null'));
        
        // التحقق من البيانات المطلوبة
        if (empty($data['name']) || empty($data['phone_1'])) {
            error_log("CustomerHelper: Name or Phone missing");
            throw new Exception('اسم العميل ورقم الهاتف مطلوبان');
        }
        
        // البحث عن العميل
        $existingCustomer = $this->findCustomerByPhone(
            $data['phone_1'], 
            $data['phone_2'] ?? null
        );
        
        // إذا كان موجوداً، نقوم بتحديث بياناته
        if ($existingCustomer) {
            error_log("CustomerHelper: Customer found (ID: " . $existingCustomer['id'] . ")");
            return $this->updateCustomerIfNeeded($existingCustomer, $data);
        }
        
        error_log("CustomerHelper: Customer not found, creating new one");
        
        // إنشاء عميل جديد
        return $this->createNewCustomer($data);
    }
    
    /**
     * إنشاء عميل جديد
     */
    private function createNewCustomer($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO customers (
                    name, phone_1, phone_2, address, type, notes
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['name'],
                $this->cleanPhone($data['phone_1']),
                $this->cleanPhone($data['phone_2'] ?? null),
                $data['address'] ?? null,
                'new', // الحالة الافتراضية
                $data['notes'] ?? null
            ]);
            
            $customerId = $this->pdo->lastInsertId();
            error_log("CustomerHelper: New customer created with ID: $customerId");
            
            // إرجاع بيانات العميل
            $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$customerId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("CustomerHelper: Error creating customer: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * تحديث بيانات العميل إذا لزم الأمر
     */
    private function updateCustomerIfNeeded($existingCustomer, $newData) {
        $needsUpdate = false;
        $updates = [];
        
        // التحقق من الحقول التي تحتاج تحديث
        if (!empty($newData['name']) && $existingCustomer['name'] !== $newData['name']) {
            $updates[] = "name = ?";
            $needsUpdate = true;
        }
        
        if (!empty($newData['phone_2']) && empty($existingCustomer['phone_2'])) {
            $updates[] = "phone_2 = ?";
            $needsUpdate = true;
        }
        
        if (!empty($newData['address']) && empty($existingCustomer['address'])) {
            $updates[] = "address = ?";
            $needsUpdate = true;
        }
        
        // إذا كان هناك تحديثات
        if ($needsUpdate && !empty($updates)) {
            error_log("CustomerHelper: Updating customer ID: " . $existingCustomer['id']);
            $sql = "UPDATE customers SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            
            $params = [];
            if (!empty($newData['name']) && $existingCustomer['name'] !== $newData['name']) {
                $params[] = $newData['name'];
            }
            if (!empty($newData['phone_2']) && empty($existingCustomer['phone_2'])) {
                $params[] = $this->cleanPhone($newData['phone_2']);
            }
            if (!empty($newData['address']) && empty($existingCustomer['address'])) {
                $params[] = $newData['address'];
            }
            $params[] = $existingCustomer['id'];
            
            $stmt->execute($params);
            
            // إعادة جلب البيانات المحدثة
            $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$existingCustomer['id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return $existingCustomer;
    }
    
    /**
     * تنظيف رقم الهاتف من المسافات والرموز
     */
    private function cleanPhone($phone) {
        if (empty($phone)) {
            return null;
        }
        // إزالة المسافات والرموز الخاصة
        return preg_replace('/[^0-9+]/', '', trim($phone));
    }
    
    /**
     * الحصول على بيانات العميل بواسطة ID
     */
    public function getCustomerById($customerId) {
        $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * الحصول على عدد الفواتير للعميل
     */
    public function getCustomerInvoicesCount($customerId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM invoices 
            WHERE customer_id = ?
        ");
        $stmt->execute([$customerId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['count'];
    }
    
    /**
     * التحقق من إمكانية حذف العميل
     */
    public function canDeleteCustomer($customerId) {
        $invoiceCount = $this->getCustomerInvoicesCount($customerId);
        return $invoiceCount === 0;
    }
}
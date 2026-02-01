<?php
/**
 * Store Logger
 * 
 * نظام تسجيل شامل لجميع العمليات في المتجر
 * - تسجيل الإجراءات (Actions)
 * - حفظ البيانات قبل وبعد التغيير
 * - تسجيل معلومات المستخدم والجهاز
 */

class StoreLogger {
    private $pdo;
    
    // أنواع الإجراءات
    const ACTION_CREATE_INVOICE = 'create_invoice';
    const ACTION_UPDATE_INVOICE = 'update_invoice';
    const ACTION_DELETE_INVOICE = 'delete_invoice';
    const ACTION_CANCEL_INVOICE = 'cancel_invoice';
    const ACTION_CHANGE_INVOICE_STATUS = 'change_invoice_status';
    const ACTION_DELIVER_INVOICE = 'deliver_invoice';
    const ACTION_RETURN_INVOICE = 'return_invoice';
    const ACTION_CLOSE_INVOICE = 'close_invoice';
    
    const ACTION_ADD_PAYMENT = 'add_payment';
    const ACTION_DELETE_PAYMENT = 'delete_payment';
    
    const ACTION_CREATE_CUSTOMER = 'create_customer';
    const ACTION_UPDATE_CUSTOMER = 'update_customer';
    const ACTION_DELETE_CUSTOMER = 'delete_customer';
    
    const ACTION_CREATE_PRODUCT = 'create_product';
    const ACTION_UPDATE_PRODUCT = 'update_product';
    const ACTION_DELETE_PRODUCT = 'delete_product';
    
    const ACTION_CREATE_BOOKING = 'create_booking';
    const ACTION_UPDATE_BOOKING = 'update_booking';
    const ACTION_DELETE_BOOKING = 'delete_booking';
    
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_FAILED_LOGIN = 'failed_login';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * تسجيل إجراء
     */
    public function log($userId, $actionType, $relatedType = null, $relatedId = null, $description = null, $dataBefore = null, $dataAfter = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO store_logs (
                    user_id,
                    action_type,
                    related_type,
                    related_id,
                    description,
                    data_before,
                    data_after,
                    ip_address,
                    user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $actionType,
                $relatedType,
                $relatedId,
                $description,
                $dataBefore ? json_encode($dataBefore, JSON_UNESCAPED_UNICODE) : null,
                $dataAfter ? json_encode($dataAfter, JSON_UNESCAPED_UNICODE) : null,
                $this->getClientIP(),
                $this->getUserAgent()
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (Exception $e) {
            error_log("Store Logger Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * تسجيل إجراء على فاتورة
     */
    public function logInvoice($userId, $action, $invoiceId, $description = null, $dataBefore = null, $dataAfter = null) {
        return $this->log(
            $userId,
            $action,
            'invoice',
            $invoiceId,
            $description,
            $dataBefore,
            $dataAfter
        );
    }
    
    /**
     * تسجيل إجراء على دفعة
     */
    public function logPayment($userId, $action, $paymentId, $description = null, $dataBefore = null, $dataAfter = null) {
        return $this->log(
            $userId,
            $action,
            'payment',
            $paymentId,
            $description,
            $dataBefore,
            $dataAfter
        );
    }
    
    /**
     * تسجيل إجراء على عميل
     */
    public function logCustomer($userId, $action, $customerId, $description = null, $dataBefore = null, $dataAfter = null) {
        return $this->log(
            $userId,
            $action,
            'customer',
            $customerId,
            $description,
            $dataBefore,
            $dataAfter
        );
    }
    
    /**
     * تسجيل إجراء على منتج
     */
    public function logProduct($userId, $action, $productId, $description = null, $dataBefore = null, $dataAfter = null) {
        return $this->log(
            $userId,
            $action,
            'product',
            $productId,
            $description,
            $dataBefore,
            $dataAfter
        );
    }
    
    /**
     * تسجيل إجراء على حجز
     */
    public function logBooking($userId, $action, $bookingId, $description = null, $dataBefore = null, $dataAfter = null) {
        return $this->log(
            $userId,
            $action,
            'booking',
            $bookingId,
            $description,
            $dataBefore,
            $dataAfter
        );
    }
    
    /**
     * جلب سجلات مستخدم معين
     */
    public function getUserLogs($userId, $limit = 50, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT 
                sl.*,
                u.full_name as user_name
            FROM store_logs sl
            LEFT JOIN users u ON sl.user_id = u.id
            WHERE sl.user_id = ?
            ORDER BY sl.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * جلب سجلات مرتبطة بكيان معين
     */
    public function getRelatedLogs($relatedType, $relatedId, $limit = 50, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT 
                sl.*,
                u.full_name as user_name
            FROM store_logs sl
            LEFT JOIN users u ON sl.user_id = u.id
            WHERE sl.related_type = ? AND sl.related_id = ?
            ORDER BY sl.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$relatedType, $relatedId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * جلب سجلات حسب نوع الإجراء
     */
    public function getActionLogs($actionType, $limit = 50, $offset = 0, $startDate = null, $endDate = null) {
        $sql = "
            SELECT 
                sl.*,
                u.full_name as user_name
            FROM store_logs sl
            LEFT JOIN users u ON sl.user_id = u.id
            WHERE sl.action_type = ?
        ";
        
        $params = [$actionType];
        
        if ($startDate) {
            $sql .= " AND DATE(sl.created_at) >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND DATE(sl.created_at) <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY sl.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * بحث في السجلات
     */
    public function searchLogs($keyword, $limit = 50, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT 
                sl.*,
                u.full_name as user_name
            FROM store_logs sl
            LEFT JOIN users u ON sl.user_id = u.id
            WHERE sl.description LIKE ?
               OR sl.action_type LIKE ?
               OR u.full_name LIKE ?
            ORDER BY sl.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $searchTerm = "%{$keyword}%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * جلب إحصائيات السجلات
     */
    public function getLogStatistics($startDate = null, $endDate = null) {
        $sql = "
            SELECT 
                action_type,
                COUNT(*) as count,
                COUNT(DISTINCT user_id) as unique_users
            FROM store_logs
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($startDate) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " GROUP BY action_type ORDER BY count DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * جلب آخر نشاط لمستخدم
     */
    public function getLastUserActivity($userId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM store_logs
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * حذف السجلات القديمة (للصيانة)
     */
    public function cleanOldLogs($daysToKeep = 365) {
        $stmt = $this->pdo->prepare("
            DELETE FROM store_logs
            WHERE created_at < DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ");
        $stmt->execute([$daysToKeep]);
        return $stmt->rowCount();
    }
    
    /**
     * الحصول على IP العميل
     */
    private function getClientIP() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // تنظيف IP من أي محتوى ضار
        $ip = filter_var($ip, FILTER_VALIDATE_IP);
        
        return $ip ?: 'unknown';
    }
    
    /**
     * الحصول على User Agent
     */
    private function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
    
    /**
     * تصدير السجلات إلى CSV
     */
    public function exportToCSV($filename, $startDate = null, $endDate = null, $actionType = null) {
        $sql = "
            SELECT 
                sl.id,
                sl.created_at,
                u.full_name as user_name,
                sl.action_type,
                sl.related_type,
                sl.related_id,
                sl.description,
                sl.ip_address
            FROM store_logs sl
            LEFT JOIN users u ON sl.user_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($startDate) {
            $sql .= " AND DATE(sl.created_at) >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND DATE(sl.created_at) <= ?";
            $params[] = $endDate;
        }
        
        if ($actionType) {
            $sql .= " AND sl.action_type = ?";
            $params[] = $actionType;
        }
        
        $sql .= " ORDER BY sl.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // إنشاء CSV
        $output = fopen($filename, 'w');
        
        // Headers
        fputcsv($output, [
            'ID',
            'التاريخ',
            'المستخدم',
            'نوع الإجراء',
            'نوع الكيان',
            'معرف الكيان',
            'الوصف',
            'IP'
        ]);
        
        // Data
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['created_at'],
                $log['user_name'] ?? 'غير معروف',
                $log['action_type'],
                $log['related_type'] ?? '',
                $log['related_id'] ?? '',
                $log['description'] ?? '',
                $log['ip_address'] ?? ''
            ]);
        }
        
        fclose($output);
        
        return true;
    }
}

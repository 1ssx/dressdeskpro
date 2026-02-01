<?php
/**
 * Audit Log Helper
 * Centralized logging for all critical system operations in Master platform
 */

class AuditLogger {
    private $pdoMaster;

    public function __construct($pdoMaster) {
        $this->pdoMaster = $pdoMaster;
    }

    /**
     * Log a system action to audit trail
     * 
     * @param string $actionType Type of action (e.g., 'store_deleted', 'license_created')
     * @param string|null $description Detailed description
     * @param int|null $adminId Master user ID who performed action
     * @param string|null $adminName Admin name (snapshot)
     * @param int|null $storeId Related store ID
     * @param string|null $storeName Store name (snapshot)
     * @param string|null $affectedEntity Entity type (e.g., 'store', 'license_key')
     * @param int|null $affectedEntityId Entity ID
     * @param array|null $metadata Additional JSON data
     * @return bool Success status
     */
    public function log(
        $actionType,
        $description = null,
        $adminId = null,
        $adminName = null,
        $storeId = null,
        $storeName = null,
        $affectedEntity = null,
        $affectedEntityId = null,
        $metadata = null
    ) {
        try {
            // Capture IP address
            $ipAddress = $this->getClientIp();
            
            // Capture user agent
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // Convert metadata to JSON
            $metadataJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;
            
            $stmt = $this->pdoMaster->prepare("
                INSERT INTO audit_log (
                    action_type, action_description, admin_id, admin_name,
                    store_id, store_name, affected_entity, affected_entity_id,
                    ip_address, user_agent, metadata, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $actionType,
                $description,
                $adminId,
                $adminName,
                $storeId,
                $storeName,
                $affectedEntity,
                $affectedEntityId,
                $ipAddress,
                $userAgent,
                $metadataJson
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Audit Log Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get client IP address (handles proxies)
     */
    private function getClientIp() {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipHeaders as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (take first one)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return null;
    }

    /**
     * Retrieve audit logs with optional filtering
     * 
     * @param array $filters Associative array of filters
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array Array of audit log entries
     */
    public function getLogs($filters = [], $limit = 50, $offset = 0) {
        try {
            $where = [];
            $params = [];
            
            if (!empty($filters['action_type'])) {
                $where[] = "action_type = ?";
                $params[] = $filters['action_type'];
            }
            
            if (!empty($filters['admin_id'])) {
                $where[] = "admin_id = ?";
                $params[] = $filters['admin_id'];
            }
            
            if (!empty($filters['store_id'])) {
                $where[] = "store_id = ?";
                $params[] = $filters['store_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $where[] = "created_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = "created_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "
                SELECT * FROM audit_log
                $whereClause
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdoMaster->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get Audit Logs Error: " . $e->getMessage());
            return [];
        }
    }
}

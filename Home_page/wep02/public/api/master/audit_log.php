<?php
/**
 * Master API - Audit Log Viewer
 * Provides access to system audit trail
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

// Debug logging 
error_log('Audit Log API called');

// Check master session
if (!isset($_SESSION['master_user_id']) || !isset($_SESSION['master_role']) || $_SESSION['master_role'] !== 'owner') {
    error_log('Audit log access denied - Session: ' . json_encode(array_keys($_SESSION)));
    http_response_code(403);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Unauthorized: Master admin access required'
    ]);
    exit;
}

error_log('Audit log access granted');

$pdoMaster = require __DIR__ . '/../../../app/config/master_database.php';
require_once __DIR__ . '/../../../app/helpers/audit_logger.php';

$auditLogger = new AuditLogger($pdoMaster);
$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Get audit logs with optional filters
            $filters = [];
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            if (!empty($_GET['action_type'])) {
                $filters['action_type'] = $_GET['action_type'];
            }
            
            if (!empty($_GET['store_id'])) {
                $filters['store_id'] = (int)$_GET['store_id'];
            }
            
            if (!empty($_GET['date_from'])) {
                $filters['date_from'] = $_GET['date_from'];
            }
            
            if (!empty($_GET['date_to'])) {
                $filters['date_to'] = $_GET['date_to'];
            }
            
            $logs = $auditLogger->getLogs($filters, $limit, $offset);
            
            // Get total count for pagination
            $where = [];
            $params = [];
            
            if (!empty($filters['action_type'])) {
                $where[] = "action_type = ?";
                $params[] = $filters['action_type'];
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
            $stmt = $pdoMaster->prepare("SELECT COUNT(*) as total FROM audit_log $whereClause");
            $stmt->execute($params);
            $totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            echo json_encode([
                'status' => 'success',
                'data' => $logs,
                'pagination' => [
                    'total' => (int)$totalCount,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $totalCount
                ]
            ]);
            break;
            
        case 'get_action_types':
            // Get list of all unique action types for filtering
            $stmt = $pdoMaster->query('SELECT DISTINCT action_type FROM audit_log ORDER BY action_type');
            $actionTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo json_encode([
                'status' => 'success',
                'data' => $actionTypes
            ]);
            break;
            
        case 'stats':
            // Get audit log statistics
            $stmt = $pdoMaster->query('
                SELECT 
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT admin_id) as unique_admins,
                    COUNT(DISTINCT store_id) as affected_stores,
                    MIN(created_at) as first_log,
                    MAX(created_at) as last_log
                FROM audit_log
            ');
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get action type breakdown
            $stmt = $pdoMaster->query('
                SELECT action_type, COUNT(*) as count 
                FROM audit_log 
                GROUP BY action_type 
                ORDER BY count DESC 
                LIMIT 10
            ');
            $topActions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'overview' => $stats,
                    'top_actions' => $topActions
                ]
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log('Audit Log API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}

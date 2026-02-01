<?php
/**
 * Master API - Enhanced Stores Management  
 * Handles CRUD operations with audit logging, soft delete, impersonation
 * FIXED: Proper success/error handling, audit logging after commit
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Check master session
session_start();

// Debug logging
error_log('Stores API called - Action: ' . ($_GET['action'] ?? $_POST['action'] ?? 'list'));
error_log('Session data: ' . json_encode([
    'master_user_id' => $_SESSION['master_user_id'] ?? 'NOT SET',
    'master_role' => $_SESSION['master_role'] ?? 'NOT SET',
    'all_session_keys' => array_keys($_SESSION)
]));

if (!isset($_SESSION['master_user_id'])) {
    error_log('Authorization failed: master_user_id not set in session');
    http_response_code(403);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Unauthorized: Not logged in as master admin',
        'debug' => 'Session check failed - master_user_id not found'
    ]);
    exit;
}

if (!isset($_SESSION['master_role']) || $_SESSION['master_role'] !== 'owner') {
    error_log('Authorization failed: Invalid role - ' . ($_SESSION['master_role'] ?? 'NOT SET'));
    http_response_code(403);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Unauthorized: Insufficient permissions',
        'debug' => 'Role check failed'
    ]);
    exit;
}

error_log('Authorization successful for user ID: ' . $_SESSION['master_user_id']);

$pdoMaster = require __DIR__ . '/../../../app/config/master_database.php';
require_once __DIR__ . '/../../../app/helpers/audit_logger.php';

$auditLogger = new AuditLogger($pdoMaster);
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

$adminId = $_SESSION['master_user_id'];
$adminName = $_SESSION['master_user_name'] ?? 'System Owner';

try {
    switch ($action) {
        case 'list':
            // Get all stores (including soft-deleted if requested)
            $includeDeleted = ($_GET['include_deleted'] ?? 'false') === 'true';
            
            $sql = '
                SELECT s.*, 
                       lk.code as license_code,
                       CASE 
                           WHEN s.status = "deleted" THEN "محذوف"
                           WHEN s.status = "suspended" THEN "موقوف"
                           ELSE "نشط"
                       END as status_ar
                FROM stores s
                LEFT JOIN license_keys lk ON s.activation_code_used = lk.code
            ';
            
            if (!$includeDeleted) {
                $sql .= ' WHERE s.status != "deleted"';
            }
            
            $sql .= ' ORDER BY s.created_at DESC';
            
            $stmt = $pdoMaster->query($sql);
            $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'status' => 'success',
                'data' => $stores
            ]);
            break;

        case 'get_details':
            // Get detailed information about a specific store
            $storeId = $_GET['store_id'] ?? null;
            
            if (!$storeId) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Store ID required']);
                exit;
            }
            
            $stmt = $pdoMaster->prepare('
                SELECT s.*, 
                       lk.code as license_code,
                       lk.expires_at as license_expires_at
                FROM stores s
                LEFT JOIN license_keys lk ON s.activation_code_used = lk.code
                WHERE s.id = ?
            ');
            $stmt->execute([$storeId]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Store not found']);
                exit;
            }
            
            echo json_encode([
                'status' => 'success',
                'data' => $store
            ]);
            break;

        case 'update_status':
            $storeId = $_POST['store_id'] ?? null;
            $status = $_POST['status'] ?? null;
            
            if (!$storeId || !in_array($status, ['active', 'suspended', 'deleted'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
                exit;
            }
            
            // Get store info for logging
            $stmt = $pdoMaster->prepare('SELECT store_name, status as old_status FROM stores WHERE id = ?');
            $stmt->execute([$storeId]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Store not found']);
                exit;
            }
            
            // Update status
            $stmt = $pdoMaster->prepare('UPDATE stores SET status = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$status, $storeId]);
            
            // Log the action
            $actionType = $status === 'suspended' ? 'store_suspended' : 
                         ($status === 'active' ? 'store_activated' : 'store_status_changed');
            
            $auditLogger->log(
                $actionType,
                "تغيير حالة المحل من {$store['old_status']} إلى {$status}",
                $adminId,
                $adminName,
                $storeId,
                $store['store_name'],
                'store',
                $storeId,
                ['old_status' => $store['old_status'], 'new_status' => $status]
            );
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Store status updated successfully'
            ]);
            break;

        case 'soft_delete':
            // Soft delete a store (mark as deleted, preserve data)
            $storeId = $_POST['store_id'] ?? null;
            $confirmation = $_POST['confirmation'] ?? '';
            
            // Validate store ID
            if (!$storeId || !is_numeric($storeId)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'Store ID required and must be a valid number'
                ]);
                exit;
            }
            
            // Get store details
            $stmt = $pdoMaster->prepare('SELECT store_name, status FROM stores WHERE id = ?');
            $stmt->execute([$storeId]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'Store not found. It may have already been deleted.'
                ]);
                exit;
            }
            
            // Check if store is already deleted
            if ($store['status'] === 'deleted') {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'Store is already marked as deleted.'
                ]);
                exit;
            }
            
            // Require confirmation that matches store name (trim whitespace for better UX)
            $confirmation = trim($confirmation);
            if ($confirmation !== $store['store_name']) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'Store name confirmation does not match. Please type the exact store name.'
                ]);
                exit;
            }
            
            error_log("Soft delete initiated for Store ID: {$storeId}, Name: {$store['store_name']}, Previous Status: {$store['status']}");
            
            // Perform soft delete
            $stmt = $pdoMaster->prepare('
                UPDATE stores 
                SET status = "deleted", 
                    deleted_at = NOW(), 
                    deleted_by = ?,
                    updated_at = NOW()
                WHERE id = ?
            ');
            $result = $stmt->execute([$adminId, $storeId]);
            
            if (!$result) {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'Failed to soft delete store'
                ]);
                exit;
            }
            
            // Log the deletion
            $auditLogger->log(
                'store_deleted_soft',
                "حذف مؤقت للمحل (Soft Delete) - البيانات محفوظة - الحالة السابقة: {$store['status']}",
                $adminId,
                $adminName,
                $storeId,
                $store['store_name'],
                'store',
                $storeId,
                ['previous_status' => $store['status']]
            );
            
            error_log("SUCCESS: Soft delete completed for Store ID: {$storeId}");
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Store soft deleted successfully. Data preserved and can be restored later.'
            ]);
            break;




        case 'hard_delete':
            // DANGEROUS: Permanently delete store and database
            
            // DEBUG: Log all received data
            error_log("=== HARD DELETE REQUEST ===");
            error_log("POST data: " . json_encode($_POST));
            error_log("GET data: " . json_encode($_GET));
            error_log("REQUEST data: " . json_encode($_REQUEST));
            
            $storeId = $_POST['store_id'] ?? null;
            $confirmStoreName = $_POST['confirm_store_name'] ?? '';
            $confirmDelete = $_POST['confirm_delete'] ?? '';
            $deleteDatabase = ($_POST['delete_database'] ?? 'false') === 'true';
            
            // DEBUG: Log extracted values
            error_log("Extracted values:");
            error_log("  store_id: " . var_export($storeId, true));
            error_log("  confirm_store_name: " . var_export($confirmStoreName, true));
            error_log("  confirm_delete: " . var_export($confirmDelete, true));
            error_log("  delete_database: " . var_export($deleteDatabase, true));
            
            // CRITICAL SAFETY CHECK: Enforce mandatory checkbox
            // The "Drop Database" checkbox MUST be checked to proceed
            if ($deleteDatabase !== true) {
                error_log("VALIDATION FAILED: Safety checkbox not checked");
                http_response_code(400);
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'Safety confirmation required: You must check the "Drop Database" checkbox to proceed with permanent deletion.',
                    'debug' => [
                        'delete_database' => $deleteDatabase,
                        'required' => true
                    ]
                ]);
                exit;
            }
            
            // Validate store ID
            if (!$storeId || !is_numeric($storeId)) {
                error_log("VALIDATION FAILED: Invalid store ID - Value: " . var_export($storeId, true));
                http_response_code(400);
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'Store ID required and must be a valid number',
                    'debug' => [
                        'received_store_id' => $storeId,
                        'is_numeric' => is_numeric($storeId),
                        'post_keys' => array_keys($_POST)
                    ]
                ]);
                exit;
            }
            
            // Get store details BEFORE ANYTHING ELSE and store in variables
            // This prevents issues if the store is deleted before audit logging
            $stmt = $pdoMaster->prepare('SELECT store_name, database_name FROM stores WHERE id = ?');
            $stmt->execute([$storeId]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                error_log("VALIDATION FAILED: Store not found - ID: {$storeId}");
                http_response_code(404);
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'Store not found. It may have already been deleted.'
                ]);
                exit;
            }
            
            // CRITICAL: Store these values NOW before deletion
            $storeName = $store['store_name'];
            $databaseName = $store['database_name'];
            
            error_log("Store found: " . $storeName);
            
            // Validate confirmations (trim whitespace for better UX)
            $confirmStoreName = trim($confirmStoreName);
            $confirmDelete = trim($confirmDelete);
            
            error_log("Comparing store names:");
            error_log("  Expected: " . $storeName);
            error_log("  Received: " . $confirmStoreName);
            error_log("  Match: " . ($confirmStoreName === $storeName ? 'YES' : 'NO'));
            
            if ($confirmStoreName !== $storeName) {
                error_log("VALIDATION FAILED: Store name mismatch");
                http_response_code(400);
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'Store name confirmation does not match. Please type the exact store name.',
                    'debug' => [
                        'expected' => $storeName,
                        'received' => $confirmStoreName,
                        'expected_length' => strlen($storeName),
                        'received_length' => strlen($confirmStoreName)
                    ]
                ]);
                exit;
            }
            
            error_log("Comparing DELETE confirmation:");
            error_log("  Expected: DELETE");
            error_log("  Received: " . $confirmDelete);
            error_log("  Match: " . (strtoupper($confirmDelete) === 'DELETE' ? 'YES' : 'NO'));
            
            if (strtoupper($confirmDelete) !== 'DELETE') {
                error_log("VALIDATION FAILED: DELETE word mismatch");
                http_response_code(400);
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'Delete confirmation required. Please type "DELETE" to proceed.',
                    'debug' => [
                        'expected' => 'DELETE',
                        'received' => $confirmDelete,
                        'received_upper' => strtoupper($confirmDelete)
                    ]
                ]);
                exit;
            }
            
            error_log("CRITICAL: Hard delete initiated for Store ID: {$storeId}, Name: {$storeName}, DB: {$databaseName}, Drop DB: YES");
            
            // Variables to track results
            $dbDropped = false;
            $dbDropError = null;
            
            $pdoMaster->beginTransaction();
            
            try {
                // STEP 1: Log the audit BEFORE deletion (so we have the store data)
                // This prevents the "false negative" issue
                try {
                    $auditLogger->log(
                        'store_deleted_hard',
                        "حذف نهائي للمحل - بدء العملية",
                        $adminId,
                        $adminName,
                        $storeId,
                        $storeName,
                        'store',
                        $storeId,
                        [
                            'database_name' => $databaseName,
                            'process_started' => true
                        ]
                    );
                } catch (Exception $auditError) {
                    error_log("WARNING: Pre-deletion audit log failed: " . $auditError->getMessage());
                    // Continue anyway - audit failure shouldn't stop deletion
                }
                
                // STEP 2: Delete store record from master database
                $stmt = $pdoMaster->prepare('DELETE FROM stores WHERE id = ?');
                $deleteResult = $stmt->execute([$storeId]);
                
                if (!$deleteResult) {
                    throw new Exception('Failed to delete store record from database');
                }
                
                $deletedRows = $stmt->rowCount();
                if ($deletedRows === 0) {
                    throw new Exception('No store was deleted. Store may have already been removed.');
                }
                
                error_log("Store record deleted successfully");
                
                // STEP 3: Drop the store database (MANDATORY with our new validation)
                $dbName = $databaseName;
                
                // Strict validation: must match pattern wep_store_[number]
                if (!preg_match('/^wep_store_\d+$/', $dbName)) {
                    error_log("SECURITY WARNING: Attempted to drop database with invalid name format: {$dbName}");
                    throw new Exception("Invalid database name format. Database drop aborted for security reasons.");
                }
                
                // Verify database exists before attempting to drop
                $checkStmt = $pdoMaster->query("SHOW DATABASES LIKE '{$dbName}'");
                $dbExists = $checkStmt->rowCount() > 0;
                
                if ($dbExists) {
                    try {
                        $dropResult = $pdoMaster->exec("DROP DATABASE `{$dbName}`");
                        $dbDropped = true;
                        error_log("CRITICAL: Successfully dropped database: {$dbName}");
                    } catch (PDOException $dropEx) {
                        $dbDropError = $dropEx->getMessage();
                        error_log("ERROR: Failed to drop database {$dbName}: " . $dbDropError);
                        // This is a critical error - throw it
                        throw new Exception("Failed to drop database: " . $dbDropError);
                    }
                } else {
                    error_log("WARNING: Database {$dbName} does not exist, skipping DROP");
                    // This is OK - database might have been manually deleted
                }
                
                // STEP 4: Commit the transaction
                $pdoMaster->commit();
                error_log("SUCCESS: Hard delete committed - Store ID: {$storeId}, DB Dropped: " . ($dbDropped ? 'YES' : 'NO'));
                
                // STEP 5: Log successful completion AFTER commit
                try {
                    $auditLogger->log(
                        'store_deleted_hard',
                        "حذف نهائي للمحل - اكتمل بنجاح - قاعدة البيانات: " . ($dbDropped ? 'محذوفة' : 'غير موجودة'),
                        $adminId,
                        $adminName,
                        null, // Store ID is now deleted
                        $storeName,
                        'store',
                        null, // Entity ID is null (deleted)
                        [
                            'database_deleted' => $dbDropped,
                            'database_name' => $databaseName,
                            'database_drop_error' => $dbDropError,
                            'store_id_was' => $storeId
                        ]
                    );
                } catch (Exception $auditError) {
                    error_log("WARNING: Post-deletion audit log failed (deletion was successful): " . $auditError->getMessage());
                    // Don't fail - deletion was successful
                }
                
                // Build success message
                $successMessage = 'Store permanently deleted';
                if ($dbDropped) {
                    $successMessage .= ' and database dropped successfully';
                } else {
                    $successMessage .= ' (database did not exist or was already deleted)';
                }
                
                // CRITICAL: Send response and EXIT immediately
                // This prevents any additional code from running and causing errors
                http_response_code(200);
                echo json_encode([
                    'status' => 'success',
                    'message' => $successMessage,
                    'data' => [
                        'store_deleted' => true,
                        'database_dropped' => $dbDropped,
                        'database_name' => $databaseName,
                        'store_name' => $storeName
                    ]
                ]);
                
                // Exit here to prevent any further execution
                exit;
                
            } catch (Exception $e) {
                // Rollback on any error
                $pdoMaster->rollBack();
                error_log("CRITICAL ERROR: Hard delete failed and rolled back - Store ID: {$storeId} - Error: " . $e->getMessage());
                
                http_response_code(500);
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'Deletion failed: ' . $e->getMessage(),
                    'debug' => [
                        'store_id' => $storeId,
                        'store_name' => $storeName,
                        'error_type' => get_class($e)
                    ]
                ]);
                exit;
            }
            
            // This should never be reached, but just in case
            break;




        case 'restore':
            // Restore a soft-deleted store
            $storeId = $_POST['store_id'] ?? null;
            
            if (!$storeId) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Store ID required']);
                exit;
            }
            
            $stmt = $pdoMaster->prepare('SELECT store_name FROM stores WHERE id = ? AND status = "deleted"');
            $stmt->execute([$storeId]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Deleted store not found']);
                exit;
            }
            
            // Restore the store
            $stmt = $pdoMaster->prepare('
                UPDATE stores 
                SET status = "active", 
                    deleted_at = NULL, 
                    deleted_by = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ');
            $stmt->execute([$storeId]);
            
            // Log restoration
            $auditLogger->log(
                'store_restored',
                "استعادة المحل من الحذف المؤقت",
                $adminId,
                $adminName,
                $storeId,
                $store['store_name'],
                'store',
                $storeId
            );
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Store restored successfully'
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log('Stores API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}

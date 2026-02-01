<?php
// customers_api.php - Multi-Store Support
// Load common infrastructure (handles output buffering, auth, database)
require_once __DIR__ . '/api/_common/bootstrap.php';

// Load CustomerHelper
$helperPath = __DIR__ . '/CustomerHelper.php';
if (file_exists($helperPath)) {
    require_once $helperPath;
}

// Determine action
$action = $_GET['action'] ?? null;

// If GET, return customer list or search
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'search' && isset($_GET['phone'])) {
        handleSearch($pdo);
    } else {
        handleList($pdo);
    }
}
// If POST, handle add/edit/delete/search
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        sendError('Invalid data', 400);
        return;
    }
    
    $action = $data['action'] ?? null;
    
    switch ($action) {
        case 'add':
        case 'edit':
        case 'update':
            handleSave($pdo, $data);
            break;
        case 'delete':
            handleDelete($pdo, $data);
            break;
        case 'search':
            handleSearch($pdo, $data);
            break;
        default:
            // If no action specified but we have valid customer data, treat as add
            if (!empty($data['name']) && !empty($data['phone_1'])) {
                handleSave($pdo, $data);
            } else {
                sendError('Invalid action', 400);
            }
    }
} else {
    sendError('Method not allowed', 405);
}

function handleList($pdo) {
    try {
        $search = $_GET['search'] ?? '';
        
        $sql = "SELECT * FROM customers WHERE 1=1";
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR phone_1 LIKE ? OR phone_2 LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendSuccess($customers);
    } catch (Exception $e) {
        logError('Customers List Error', ['message' => $e->getMessage()]);
        sendError('Failed to load customers: ' . $e->getMessage(), 500);
    }
}

function handleSave($pdo, $data) {
    try {
        $id = $data['id'] ?? null;
        $name = trim($data['name'] ?? '');
        $phone1 = trim($data['phone_1'] ?? '');
        $phone2 = trim($data['phone_2'] ?? '');
        $type = $data['type'] ?? 'new';
        $address = $data['address'] ?? null;
        $notes = $data['notes'] ?? null;
        
        if (empty($name)) {
            sendError('Customer name is required', 400);
            return;
        }
        
        if ($id) {
            // Update
            $stmt = $pdo->prepare("
                UPDATE customers 
                SET name = ?, phone_1 = ?, phone_2 = ?, type = ?, address = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $phone1 ?: null, $phone2 ?: null, $type, $address, $notes, $id]);
        } else {
            // Insert
            $stmt = $pdo->prepare("
                INSERT INTO customers (name, phone_1, phone_2, type, address, notes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$name, $phone1 ?: null, $phone2 ?: null, $type, $address, $notes]);
            $id = $pdo->lastInsertId();
        }
        
        sendSuccess(['id' => $id, 'message' => 'Customer saved successfully']);
    } catch (Exception $e) {
        logError('Save Customer Error', ['message' => $e->getMessage()]);
        sendError('Failed to save customer: ' . $e->getMessage(), 500);
    }
}

function handleDelete($pdo, $data) {
    try {
        $id = $data['id'] ?? null;
        
        if (!$id) {
            sendError('Customer ID is required', 400);
            return;
        }
        
        // Check if customer has invoices
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM invoices WHERE customer_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            sendError('Cannot delete customer with existing invoices', 400);
            return;
        }
        
        // Delete customer
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        
        sendSuccess(['message' => 'Customer deleted successfully']);
    } catch (Exception $e) {
        logError('Delete Customer Error', ['message' => $e->getMessage()]);
        sendError('Failed to delete customer: ' . $e->getMessage(), 500);
    }
}

function handleSearch($pdo, $data = null) {
    try {
        // Get phone from GET or POST data
        $phone = $data['phone'] ?? $_GET['phone'] ?? '';
        
        if (empty($phone)) {
            sendError('Phone number is required', 400);
            return;
        }
        
        // Clean phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Search by phone
        $stmt = $pdo->prepare("
            SELECT * FROM customers 
            WHERE phone_1 = ? OR phone_2 = ?
            LIMIT 1
        ");
        $stmt->execute([$phone, $phone]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($customer) {
            sendSuccess([
                'found' => true,
                'data' => $customer
            ]);
        } else {
            sendSuccess([
                'found' => false,
                'data' => null
            ]);
        }
    } catch (Exception $e) {
        logError('Customer Search Error', ['message' => $e->getMessage()]);
        sendError('Failed to search customer: ' . $e->getMessage(), 500);
    }
}

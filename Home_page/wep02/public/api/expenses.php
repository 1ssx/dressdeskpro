<?php
/**
 * Expenses Management API - Multi-Store Support
 * Handles CRUD operations for business expenses
 */

// Load common infrastructure (handles output buffering, auth, database)
require_once __DIR__ . '/_common/bootstrap.php';

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'list':
        handleList($pdo);
        break;
        
    case 'categories':
        handleCategories($pdo);
        break;
        
    case 'add':
    case 'create':
        if ($method === 'POST') {
            handleAdd($pdo);
        } else {
            sendError('Method not allowed', 405);
        }
        break;
        
    case 'update':
        if ($method === 'POST') {
            handleUpdate($pdo);
        } else {
            sendError('Method not allowed', 405);
        }
        break;
        
    case 'delete':
        if ($method === 'POST') {
            handleDelete($pdo);
        } else {
            sendError('Method not allowed', 405);
        }
        break;
        
    default:
        sendError('Invalid action', 400);
}

function handleList($pdo) {
    try {
        // Get date filter (default to today if not provided)
        $date = $_GET['date'] ?? date('Y-m-d');
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        
        // Get expenses for the date
        $sql = "SELECT * FROM expenses WHERE DATE(expense_date) = ? ORDER BY expense_date DESC, id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate total expenses for the date
        $totalExpenses = 0;
        foreach ($expenses as $expense) {
            $totalExpenses += floatval($expense['amount']);
        }
        
        // ✅ CRITICAL FIX: Get total income (revenue) using CASH-BASED calculation
        // This now includes BOTH initial deposits AND all subsequent payments
        // Load RevenueCalculator for accurate cash-based revenue tracking
        require_once __DIR__ . '/../../app/helpers/revenue_calculator.php';
        $revenueCalculator = new RevenueCalculator($pdo);
        
        $totalIncome = 0;
        try {
            // Use RevenueCalculator to get ALL cash received on this date
            // This includes: invoices.deposit_amount + payments (type='payment')
            $totalIncome = $revenueCalculator->calculateDailyRevenue($date);
            
            error_log("Daily Ledger - Date: {$date}, Total Income (Cash-Based): {$totalIncome}");
            
        } catch (Exception $e) {
            // If RevenueCalculator fails, fall back to deposits only
            error_log("Daily Ledger: RevenueCalculator error, falling back to deposits only: " . $e->getMessage());
            try {
                $incomeStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(deposit_amount), 0) as total_income
                    FROM invoices 
                    WHERE DATE(invoice_date) = ?
                    AND deleted_at IS NULL
                ");
                $incomeStmt->execute([$date]);
                $incomeResult = $incomeStmt->fetch(PDO::FETCH_ASSOC);
                $totalIncome = floatval($incomeResult['total_income'] ?? 0);
            } catch (Exception $e2) {
                error_log('Daily Ledger Income Calculation Error: ' . $e2->getMessage());
                $totalIncome = 0;
            }
        }
        
        // Calculate net income
        $netIncome = $totalIncome - $totalExpenses;
        
        // Prepare response in the format expected by frontend
        $data = [
            'expenses' => $expenses,
            'summary' => [
                'total_income' => $totalIncome,
                'total_expenses' => $totalExpenses,
                'net_income' => $netIncome
            ]
        ];
        
        sendSuccess($data);
    } catch (Exception $e) {
        logError('Expenses List Error', ['message' => $e->getMessage()]);
        // Return empty data instead of error for new stores
        sendSuccess([
            'expenses' => [],
            'summary' => [
                'total_income' => 0,
                'total_expenses' => 0,
                'net_income' => 0
            ]
        ]);
    }
}

function handleAdd($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $expenseDate = $input['expense_date'] ?? date('Y-m-d');
        $amount = floatval($input['amount'] ?? 0);
        $category = trim($input['category'] ?? '');
        $description = trim($input['description'] ?? '');
        
        if ($amount <= 0) {
            sendError('Amount must be greater than zero', 400);
            return;
        }
        
        if (empty($category)) {
            sendError('Category is required', 400);
            return;
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate)) {
            $expenseDate = date('Y-m-d');
        }
        
        // Get current user ID for created_by field
        $createdBy = $_SESSION['user_id'] ?? null;
        $receiptNumber = trim($input['receipt_number'] ?? '');
        
        // Check if receipt_number and created_by columns exist
        $hasReceiptNumber = false;
        $hasCreatedBy = false;
        try {
            $checkCols = $pdo->query("SHOW COLUMNS FROM expenses");
            $columns = $checkCols->fetchAll(PDO::FETCH_COLUMN);
            $hasReceiptNumber = in_array('receipt_number', $columns);
            $hasCreatedBy = in_array('created_by', $columns);
        } catch (Exception $e) {
            // If check fails, assume columns don't exist
        }
        
        if ($hasReceiptNumber && $hasCreatedBy) {
            $stmt = $pdo->prepare("
                INSERT INTO expenses (expense_date, amount, category, description, receipt_number, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$expenseDate, $amount, $category, $description, $receiptNumber ?: null, $createdBy]);
        } else {
            // Fallback for older schemas
            $stmt = $pdo->prepare("
                INSERT INTO expenses (expense_date, amount, category, description, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$expenseDate, $amount, $category, $description]);
        }
        
        $id = $pdo->lastInsertId();
        
        sendSuccess(['id' => $id, 'message' => 'Expense added successfully']);
    } catch (Exception $e) {
        logError('Add Expense Error', ['message' => $e->getMessage()]);
        sendError('Failed to add expense: ' . $e->getMessage(), 500);
    }
}

function handleDelete($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        
        if (!$id) {
            sendError('Expense ID is required', 400);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->execute([$id]);
        
        sendSuccess(['message' => 'Expense deleted successfully']);
    } catch (Exception $e) {
        logError('Delete Expense Error', ['message' => $e->getMessage()]);
        sendError('Failed to delete expense: ' . $e->getMessage(), 500);
    }
}

function handleCategories($pdo) {
    try {
        // LAZY INITIALIZATION: Check if expense_categories table exists
        $tableExists = false;
        try {
            $checkTable = $pdo->query("SHOW TABLES LIKE 'expense_categories'");
            $tableExists = ($checkTable->rowCount() > 0);
        } catch (Exception $e) {
            // Ignore error
        }

        if (!$tableExists) {
            // Create table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `expense_categories` (
                  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `name` varchar(100) NOT NULL,
                  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uq_exp_cat_name` (`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Seed default categories
            $defaults = ['إيجار', 'كهرباء', 'رواتب', 'مشتريات', 'صيانة', 'نثريات', 'تسويق', 'ضيافة'];
            $insertStmt = $pdo->prepare("INSERT IGNORE INTO expense_categories (name) VALUES (?)");
            foreach ($defaults as $cat) {
                $insertStmt->execute([$cat]);
            }
        }

        // Fetch categories from the dedicate table
        $stmt = $pdo->query("SELECT name FROM expense_categories ORDER BY name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Fallback: merge with existing text-based categories from expenses table (for legacy data)
        $stmtOld = $pdo->query("SELECT DISTINCT category FROM expenses WHERE category IS NOT NULL AND category != ''");
        $oldCategories = $stmtOld->fetchAll(PDO::FETCH_COLUMN);
        
        $allCategories = array_unique(array_merge($categories, $oldCategories));
        sort($allCategories);

        sendSuccess(array_values($allCategories));
    } catch (Exception $e) {
        logError('Expenses Categories Error', ['message' => $e->getMessage()]);
        // Return empty array instead of error
        sendSuccess([]);
    }
}

function handleUpdate($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $id = $input['id'] ?? null;
        $expenseDate = $input['expense_date'] ?? date('Y-m-d');
        $amount = floatval($input['amount'] ?? 0);
        $category = trim($input['category'] ?? '');
        $description = trim($input['description'] ?? '');
        
        if (!$id) {
            sendError('Expense ID is required', 400);
            return;
        }
        
        if ($amount <= 0) {
            sendError('Amount must be greater than zero', 400);
            return;
        }
        
        if (empty($category)) {
            sendError('Category is required', 400);
            return;
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate)) {
            $expenseDate = date('Y-m-d');
        }
        
        $receiptNumber = trim($input['receipt_number'] ?? '');
        
        // Check if receipt_number column exists
        $hasReceiptNumber = false;
        try {
            $checkCols = $pdo->query("SHOW COLUMNS FROM expenses");
            $columns = $checkCols->fetchAll(PDO::FETCH_COLUMN);
            $hasReceiptNumber = in_array('receipt_number', $columns);
        } catch (Exception $e) {
            // If check fails, assume column doesn't exist
        }
        
        if ($hasReceiptNumber) {
            $stmt = $pdo->prepare("
                UPDATE expenses 
                SET expense_date = ?, amount = ?, category = ?, description = ?, receipt_number = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$expenseDate, $amount, $category, $description, $receiptNumber ?: null, $id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE expenses 
                SET expense_date = ?, amount = ?, category = ?, description = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$expenseDate, $amount, $category, $description, $id]);
        }
        
        sendSuccess(['id' => $id, 'message' => 'Expense updated successfully']);
    } catch (Exception $e) {
        logError('Update Expense Error', ['message' => $e->getMessage()]);
        sendError('Failed to update expense: ' . $e->getMessage(), 500);
    }
}

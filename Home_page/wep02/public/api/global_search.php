<?php
// global_search.php - Global Search Endpoint for Invoices and Customers
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// Disable error display to avoid JSON corruption
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // 1. Session and Database Connection
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['store_db_name'])) {
        throw new Exception("Unauthorized: Store context not found");
    }

    // Connect using the central configuration
    $pdo = require __DIR__ . '/../../app/config/store_database.php';
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    // 2. Get Search Query
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';

    if (mb_strlen($q) < 2) {
        echo json_encode(['status' => 'success', 'results' => []]);
        exit;
    }

    $results = [];
    $limitPerType = 5;
    $searchTerm = "%{$q}%";

    // 3. Search Invoices (Match Invoice Number OR Customer Name)
    // We join customers table to search by customer name for the invoice
    $stmtInvoices = $pdo->prepare("
        SELECT 
            i.id, 
            i.invoice_number, 
            i.invoice_date, 
            i.total_price,
            c.name as customer_name
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        WHERE 
            i.invoice_number LIKE :q1 
            OR c.name LIKE :q2
        ORDER BY i.created_at DESC
        LIMIT :limit
    ");

    $stmtInvoices->bindValue(':q1', $searchTerm, PDO::PARAM_STR);
    $stmtInvoices->bindValue(':q2', $searchTerm, PDO::PARAM_STR);
    $stmtInvoices->bindValue(':limit', $limitPerType, PDO::PARAM_INT);
    $stmtInvoices->execute();
    
    while ($row = $stmtInvoices->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'type' => 'invoice',
            'id' => $row['id'],
            'title' => $row['invoice_number'], // Title is Invoice Number
            'subtitle' => $row['customer_name'] . ' - ' . $row['invoice_date'],
            'link' => 'invoice_details.php?id=' . $row['id']
        ];
    }

    // 4. Search Customers (Match Name or Phone)
    $stmtCustomers = $pdo->prepare("
        SELECT 
            id, 
            name, 
            phone_1,
            phone_2
        FROM customers 
        WHERE 
            name LIKE :q1 
            OR phone_1 LIKE :q2
            OR phone_2 LIKE :q3
        ORDER BY created_at DESC
        LIMIT :limit
    ");

    $stmtCustomers->bindValue(':q1', $searchTerm, PDO::PARAM_STR);
    $stmtCustomers->bindValue(':q2', $searchTerm, PDO::PARAM_STR);
    $stmtCustomers->bindValue(':q3', $searchTerm, PDO::PARAM_STR);
    $stmtCustomers->bindValue(':limit', $limitPerType, PDO::PARAM_INT);
    $stmtCustomers->execute();

    while ($row = $stmtCustomers->fetch(PDO::FETCH_ASSOC)) {
        $phone = $row['phone_1'] ?: $row['phone_2'];
        $results[] = [
            'type' => 'customer',
            'id' => $row['id'],
            'title' => $row['name'], // Title is Customer Name
            'subtitle' => $phone,
            'link' => 'customer.php?id=' . $row['id'] // Assuming customer profile link
        ];
    }

    // 5. Return Results
    echo json_encode([
        'status' => 'success',
        'results' => $results
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

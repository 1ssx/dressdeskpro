<?php
/**
 * Inventory API
 * PHASE 6 - Refactored to use common infrastructure and v2 schema (category_id, supplier_id)
 */

// Disable error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Load common infrastructure (handles output buffering)
require_once __DIR__ . '/_common/bootstrap.php';

$action = getQueryParam('action', 'list');

/**
 * تحويل سطر حركة مخزون من قاعدة البيانات إلى شكل JSON المستخدم في الواجهة
 */
function mapMovementRow(array $row): array {
    return [
        'id'       => (int)$row['id'],
        'date'     => substr($row['created_at'], 0, 10),
        'type'     => $row['type'],
        'quantity' => (int)$row['quantity'],
        'balance'  => (int)$row['balance_after'],
        'notes'    => $row['notes'] ?? null,
        'user'     => $row['user_name'] ?? $row['user'] ?? null,
    ];
}

/**
 * تحويل منتج إلى شكل JSON المستخدم في inventory.js
 * PHASE 6 - Updated for v2 schema (category_id, supplier_id with JOINs)
 */
function mapProductRow(array $row, array $movements): array {
    // Handle both image and image_url fields (v2 uses image_url)
    $image = $row['image_url'] ?? $row['image'] ?? '';
    
    // Get category name from JOIN (v2 schema uses category_id FK)
    $categoryName = $row['category_name'] ?? '';
    $categoryId = isset($row['category_id']) ? (int)$row['category_id'] : null;
    
    // Get supplier name from JOIN (v2 schema uses supplier_id FK)
    $supplierName = $row['supplier_name'] ?? '';
    $supplierId = isset($row['supplier_id']) ? (int)$row['supplier_id'] : null;
    
    // Check if product is locked
    $isLocked = isset($row['is_locked']) && $row['is_locked'] == 1;
    
    return [
        'id'          => (int)$row['id'],
        'code'        => $row['code'] ?? '',
        'name'        => $row['name'] ?? '',
        'category'    => $categoryName, // For display
        'category_id' => $categoryId,   // For form editing
        'supplier'    => $supplierName, // For display
        'supplier_id' => $supplierId,   // For form editing
        'price'       => (float)($row['price'] ?? 0),
        'cost'        => (float)($row['cost'] ?? 0),
        'size'        => $row['size'] ?? '',
        'color'       => $row['color'] ?? '',
        'fabricType'  => $row['fabric_type'] ?? '',
        'quantity'    => (int)($row['quantity'] ?? 0),
        'minQuantity' => (int)($row['min_quantity'] ?? 5),
        'min_quantity' => (int)($row['min_quantity'] ?? 5),
        'description' => $row['description'] ?? null,
        'image'       => $image,
        'image_url'   => $image,
        'date'        => isset($row['created_at']) ? substr($row['created_at'], 0, 10) : '',
        'movements'   => $movements,
        'is_locked'   => $isLocked,
        'can_edit'    => !$isLocked,
        'can_delete'  => !$isLocked,
    ];
}

try {
    switch ($action) {

        /**
         * GET inventory.php?action=stats
         * جلب إحصائيات المخزون + حالات الفساتين
         */
        case 'stats':
            try {
                // 1. Total Products
                $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM products");
                $totalProducts = (int)$totalStmt->fetchColumn();

                // 2. Available Items (quantity > 0)
                $availableStmt = $pdo->query("SELECT COUNT(*) as available FROM products WHERE quantity > 0");
                $availableItems = (int)$availableStmt->fetchColumn();

                // 3. Low Stock Items - Check if min_quantity column exists
                $lowStockItems = 0;
                try {
                    $checkMinQty = $pdo->query("SHOW COLUMNS FROM products LIKE 'min_quantity'");
                    if ($checkMinQty->rowCount() > 0) {
                        $lowStockStmt = $pdo->query("
                            SELECT COUNT(*) as low_stock 
                            FROM products 
                            WHERE quantity > 0 AND quantity <= COALESCE(min_quantity, 5)
                        ");
                        $lowStockItems = (int)$lowStockStmt->fetchColumn();
                    } else {
                        // Fallback: use default threshold of 5
                        $lowStockStmt = $pdo->query("
                            SELECT COUNT(*) as low_stock 
                            FROM products 
                            WHERE quantity > 0 AND quantity <= 5
                        ");
                        $lowStockItems = (int)$lowStockStmt->fetchColumn();
                    }
                } catch (Exception $e) {
                    // If query fails, set to 0
                    $lowStockItems = 0;
                }

                // 4. Out of Stock Items (quantity = 0)
                $outOfStockStmt = $pdo->query("SELECT COUNT(*) as out_of_stock FROM products WHERE quantity = 0");
                $outOfStockItems = (int)$outOfStockStmt->fetchColumn();

                // 5. Total Categories
                $categoriesStmt = $pdo->query("SELECT COUNT(*) as total FROM categories");
                $totalCategories = (int)$categoriesStmt->fetchColumn();

                // 6. Total Suppliers
                $suppliersStmt = $pdo->query("SELECT COUNT(*) as total FROM suppliers");
                $totalSuppliers = (int)$suppliersStmt->fetchColumn();

                // 7. Total Inventory Movements
                $movementsStmt = $pdo->query("SELECT COUNT(*) as total FROM inventory_movements");
                $totalMovements = (int)$movementsStmt->fetchColumn();
                
                // 8. Product Status Counts (synced with invoices)
                $rentedProducts = 0;
                $reservedProducts = 0;
                $availableStatusProducts = 0;
                
                try {
                    $checkStatus = $pdo->query("SHOW COLUMNS FROM products LIKE 'status'");
                    if ($checkStatus->rowCount() > 0) {
                        // Count products by status
                        $statusStmt = $pdo->query("
                            SELECT 
                                COALESCE(SUM(CASE WHEN status = 'rented' OR status = 'out_with_customer' THEN 1 ELSE 0 END), 0) as rented,
                                COALESCE(SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END), 0) as reserved,
                                COALESCE(SUM(CASE WHEN status = 'available' OR status IS NULL OR status = '' THEN 1 ELSE 0 END), 0) as available
                            FROM products
                        ");
                        $statusCounts = $statusStmt->fetch(PDO::FETCH_ASSOC);
                        $rentedProducts = (int)($statusCounts['rented'] ?? 0);
                        $reservedProducts = (int)($statusCounts['reserved'] ?? 0);
                        $availableStatusProducts = (int)($statusCounts['available'] ?? 0);
                    }
                } catch (Exception $e) {
                    // Status column might not exist
                    if (function_exists('logError')) {
                        logError('Status count check failed', ['message' => $e->getMessage()]);
                    }
                }

                $data = [
                    'total_products' => $totalProducts,
                    'available_items' => $availableItems,
                    'low_stock_items' => $lowStockItems,
                    'out_of_stock_items' => $outOfStockItems,
                    'total_categories' => $totalCategories,
                    'total_suppliers' => $totalSuppliers,
                    'total_movements' => $totalMovements,
                    // Enhanced status info
                    'rented_products' => $rentedProducts,
                    'reserved_products' => $reservedProducts,
                    'available_status_products' => $availableStatusProducts
                ];

                sendSuccess($data);
            } catch (Exception $e) {
                logError('Inventory Stats Error', ['message' => $e->getMessage()]);
                // Return empty stats instead of error for new stores
                sendSuccess([
                    'total_products' => 0,
                    'available_items' => 0,
                    'low_stock_items' => 0,
                    'out_of_stock_items' => 0,
                    'total_categories' => 0,
                    'total_suppliers' => 0,
                    'total_movements' => 0,
                    'rented_products' => 0,
                    'reserved_products' => 0,
                    'available_status_products' => 0
                ]);
            }
            break;

        /**
         * GET inventory.php?action=list
         * جلب كل المنتجات + حركات المخزون الخاصة بكل منتج
         * v2 schema: JOIN with categories and suppliers tables
         */
        case 'list':
            // جلب كل المنتجات مع JOIN للفئات والموردين (v2 schema)
            $stmt = $pdo->query("
                SELECT 
                    p.*,
                    c.name as category_name,
                    s.name as supplier_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN suppliers s ON p.supplier_id = s.id
                ORDER BY p.id DESC
            ");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];

            if ($products && count($products) > 0) {
                $productIds = array_column($products, 'id');

                // جلب كل الحركات دفعة واحدة
                if (count($productIds) > 0) {
                    $inPlaceholders = implode(',', array_fill(0, count($productIds), '?'));
                    $stmtMov = $pdo->prepare("
                        SELECT 
                            im.*,
                            u.full_name as user_name
                        FROM inventory_movements im
                        LEFT JOIN users u ON im.created_by = u.id
                        WHERE im.product_id IN ($inPlaceholders)
                        ORDER BY im.created_at DESC
                    ");
                    $stmtMov->execute($productIds);
                    $movRows = $stmtMov->fetchAll(PDO::FETCH_ASSOC);

                    $movementsByProduct = [];
                    foreach ($movRows as $row) {
                        $pid = (int)$row['product_id'];
                        if (!isset($movementsByProduct[$pid])) {
                            $movementsByProduct[$pid] = [];
                        }
                        $movementsByProduct[$pid][] = mapMovementRow($row);
                    }
                } else {
                    $movementsByProduct = [];
                }

                // تركيب النتيجة
                foreach ($products as $p) {
                    $pid = (int)$p['id'];
                    $result[] = mapProductRow($p, $movementsByProduct[$pid] ?? []);
                }
            }

            sendSuccess($result);
            break;

        /**
         * POST inventory.php?action=create
         * إضافة فستان جديد + إنشاء حركة مخزون أولية إذا الكمية > 0
         * v2 schema: Uses category_id and supplier_id (INT FKs)
         */
        case 'create':
            requireMethod('POST');
            
            // Handle FormData (file upload) or JSON
            $data = [];
            if (!empty($_POST) && isset($_POST['code'])) {
                // FormData submission - $_POST contains form fields
                $data = $_POST;
            } else {
                // JSON submission
                try {
                    $data = getJsonInput();
                } catch (Exception $e) {
                    // If JSON parsing fails, try $_POST anyway
                    $data = $_POST ?? [];
                }
            }
            
            // Debug: Log received data
            if (function_exists('logError')) {
                logError('Create product data', [
                    'data_keys' => array_keys($data), 
                    'category_id' => $data['category_id'] ?? 'missing',
                    'has_files' => !empty($_FILES)
                ]);
            }
            
            // Validate required fields
            $required = ['code', 'name', 'category_id', 'size', 'color'];
            $missing = [];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $missing[] = $field;
                }
            }
            if (!empty($missing)) {
                sendError('Missing required fields: ' . implode(', ', $missing), 400);
            }

            $code = sanitize(trim($data['code'] ?? ''));
            $name = sanitize(trim($data['name'] ?? ''));
            $categoryId = (int)($data['category_id'] ?? 0);
            $supplierId = !empty($data['supplier_id']) ? (int)($data['supplier_id']) : null;
            $price = (float)($data['price'] ?? 0);
            $cost = (float)($data['cost'] ?? 0);
            $size = sanitize(trim($data['size'] ?? ''));
            $color = sanitize(trim($data['color'] ?? ''));
            $fabricType = sanitize(trim($data['fabric_type'] ?? $data['fabricType'] ?? ''));
            $quantity = (int)($data['quantity'] ?? 0);
            $minQuantity = (int)($data['min_quantity'] ?? $data['minQuantity'] ?? 5);
            $description = !empty($data['description']) ? sanitize(trim($data['description'])) : null;
            
            // Handle image upload
            $image = '';
            if (isset($_FILES['dress_image']) && $_FILES['dress_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../assets/uploads/products/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $fileName = time() . '_' . basename($_FILES['dress_image']['name']);
                $destPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['dress_image']['tmp_name'], $destPath)) {
                    $image = '../assets/uploads/products/' . $fileName;
                }
            } elseif (!empty($data['image']) || !empty($data['image_url'])) {
                $image = sanitize(trim($data['image'] ?? $data['image_url'] ?? ''));
            } else {
                // Default placeholder
                $image = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='150' height='150'><rect width='150' height='150' fill='%23e0e0e0'/><text x='50%25' y='55%25' font-size='14' text-anchor='middle' fill='%23666' font-family='Arial' dy='.3em'>صورة</text></svg>";
            }

            if ($categoryId <= 0) {
                sendError('Invalid category_id. Please select a valid category.', 400);
            }
            
            // Validate that category exists
            try {
                $catCheck = $pdo->prepare("SELECT id FROM categories WHERE id = :id");
                $catCheck->execute([':id' => $categoryId]);
                if (!$catCheck->fetch()) {
                    sendError('Selected category does not exist', 400);
                }
            } catch (Exception $e) {
                sendError('Error validating category: ' . $e->getMessage(), 500);
            }

            try {
                $pdo->beginTransaction();

                // إدخال المنتج (v2 schema: category_id, supplier_id)
                $stmt = $pdo->prepare("
                    INSERT INTO products 
                    (code, name, category_id, supplier_id, price, cost, size, color, fabric_type, quantity, min_quantity, description, image_url)
                    VALUES
                    (:code, :name, :category_id, :supplier_id, :price, :cost, :size, :color, :fabric_type, :quantity, :min_quantity, :description, :image_url)
                ");

                $stmt->execute([
                    ':code'        => $code,
                    ':name'        => $name,
                    ':category_id' => $categoryId,
                    ':supplier_id' => $supplierId,
                    ':price'       => $price,
                    ':cost'        => $cost,
                    ':size'        => $size,
                    ':color'       => $color,
                    ':fabric_type' => $fabricType,
                    ':quantity'    => $quantity,
                    ':min_quantity'=> $minQuantity,
                    ':description' => $description,
                    ':image_url'   => $image,
                ]);

                $productId = (int)$pdo->lastInsertId();

                // لو في كمية، نسجل حركة مخزون أولية
                if ($quantity > 0) {
                    $userId = getCurrentUserId(); // May be null if not logged in
                    $stmtMov = $pdo->prepare("
                        INSERT INTO inventory_movements (product_id, type, quantity, balance_after, notes, created_by)
                        VALUES (:product_id, 'in', :quantity, :balance_after, :notes, :created_by)
                    ");
                    $stmtMov->execute([
                        ':product_id'   => $productId,
                        ':quantity'     => $quantity,
                        ':balance_after'=> $quantity,
                        ':notes'        => 'مخزون أولي',
                        ':created_by'   => $userId, // NULL is allowed in schema
                    ]);
                }

                $pdo->commit();

                sendSuccess(['id' => $productId]);
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // Check for specific database errors
                if ($e->getCode() == 23000) {
                    // Duplicate entry
                    if (strpos($e->getMessage(), 'uq_products_code') !== false) {
                        sendError('Product code already exists. Please use a different code.', 400);
                    } elseif (strpos($e->getMessage(), 'fk_products_category') !== false) {
                        sendError('Invalid category. The selected category does not exist.', 400);
                    } else {
                        sendError('Database constraint violation: ' . $e->getMessage(), 400);
                    }
                } else {
                    sendError('Database error: ' . $e->getMessage(), 500);
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                sendError('Error creating product: ' . $e->getMessage(), 500);
            }
            break;

        /**
         * POST inventory.php?action=update
         * تعديل بيانات فستان موجود
         * v2 schema: Uses category_id and supplier_id
         */
        case 'update':
            requireMethod('POST');
            
            // Handle FormData or JSON
            $data = [];
            if (!empty($_POST)) {
                $data = $_POST;
            } else {
                $data = getJsonInput();
            }
            
            validateRequired($data, ['id']);
            
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                sendError('Invalid product ID', 400);
            }
            
            // Check if product exists first
            $checkStmt = $pdo->prepare("SELECT id FROM products WHERE id = :id");
            $checkStmt->execute([':id' => $id]);
            if (!$checkStmt->fetch()) {
                sendError('Product not found', 404);
            }
            
            // Check if is_locked column exists and if product is locked
            try {
                $lockCheck = $pdo->prepare("SELECT is_locked FROM products WHERE id = :id");
                $lockCheck->execute([':id' => $id]);
                $product = $lockCheck->fetch(PDO::FETCH_ASSOC);
                
                if ($product && isset($product['is_locked']) && $product['is_locked'] == 1) {
                    sendError('لا يمكن تعديل هذا المنتج لأنه مرتبط بفاتورة', 403);
                }
            } catch (PDOException $e) {
                // is_locked column might not exist - proceed with update
                if (function_exists('logError')) {
                    logError('is_locked column check failed', ['message' => $e->getMessage()]);
                }
            }

            $code = sanitize(trim($data['code'] ?? ''));
            $name = sanitize(trim($data['name'] ?? ''));
            $categoryId = (int)($data['category_id'] ?? 0);
            $supplierId = !empty($data['supplier_id']) ? (int)($data['supplier_id']) : null;
            $price = (float)($data['price'] ?? 0);
            $cost = (float)($data['cost'] ?? 0);
            $size = sanitize(trim($data['size'] ?? ''));
            $color = sanitize(trim($data['color'] ?? ''));
            $fabricType = sanitize(trim($data['fabric_type'] ?? $data['fabricType'] ?? ''));
            $quantity = (int)($data['quantity'] ?? 0);
            $minQuantity = (int)($data['min_quantity'] ?? $data['minQuantity'] ?? 5);
            $description = !empty($data['description']) ? sanitize(trim($data['description'])) : null;

            // Handle image upload
            $image = null;
            if (isset($_FILES['dress_image']) && $_FILES['dress_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../assets/uploads/products/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $fileName = time() . '_' . basename($_FILES['dress_image']['name']);
                $destPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['dress_image']['tmp_name'], $destPath)) {
                    $image = '../assets/uploads/products/' . $fileName;
                }
            } elseif (!empty($data['image']) || !empty($data['image_url'])) {
                $image = sanitize(trim($data['image'] ?? $data['image_url'] ?? ''));
            }

            if ($categoryId <= 0) {
                sendError('Invalid category_id', 400);
            }

            // Build UPDATE query dynamically (only update image if provided)
            $updateFields = [
                'code = :code',
                'name = :name',
                'category_id = :category_id',
                'supplier_id = :supplier_id',
                'price = :price',
                'cost = :cost',
                'size = :size',
                'color = :color',
                'fabric_type = :fabric_type',
                'quantity = :quantity',
                'min_quantity = :min_quantity',
                'description = :description'
            ];
            
            $params = [
                ':code'        => $code,
                ':name'        => $name,
                ':category_id' => $categoryId,
                ':supplier_id' => $supplierId,
                ':price'       => $price,
                ':cost'        => $cost,
                ':size'        => $size,
                ':color'       => $color,
                ':fabric_type' => $fabricType,
                ':quantity'    => $quantity,
                ':min_quantity'=> $minQuantity,
                ':description' => $description,
                ':id'          => $id,
            ];
            
            if ($image !== null) {
                $updateFields[] = 'image_url = :image_url';
                $params[':image_url'] = $image;
            }

            $stmt = $pdo->prepare("
                UPDATE products SET
                    " . implode(', ', $updateFields) . "
                WHERE id = :id
            ");

            $stmt->execute($params);

            sendSuccess(['id' => $id]);
            break;

        /**
         * POST inventory.php?action=delete
         * حذف فستان
         */
        case 'delete':
            requireMethod('POST');
            $data = getJsonInput();
            validateRequired($data, ['id']);
            
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                sendError('Invalid product ID', 400);
            }
            
            // Check if product exists first
            $checkStmt = $pdo->prepare("SELECT id FROM products WHERE id = :id");
            $checkStmt->execute([':id' => $id]);
            if (!$checkStmt->fetch()) {
                sendError('Product not found', 404);
            }
            
            // Check if is_locked column exists and if product is locked
            try {
                $lockCheck = $pdo->prepare("SELECT is_locked FROM products WHERE id = :id");
                $lockCheck->execute([':id' => $id]);
                $product = $lockCheck->fetch(PDO::FETCH_ASSOC);
                
                if ($product && isset($product['is_locked']) && $product['is_locked'] == 1) {
                    sendError('لا يمكن حذف هذا المنتج لأنه مرتبط بفاتورة', 403);
                }
            } catch (PDOException $e) {
                // is_locked column might not exist - proceed with deletion
                // Log the error for debugging
                if (function_exists('logError')) {
                    logError('is_locked column check failed', ['message' => $e->getMessage()]);
                }
            }

            // Delete inventory movements first (due to foreign key constraint)
            try {
                $deleteMovements = $pdo->prepare("DELETE FROM inventory_movements WHERE product_id = :id");
                $deleteMovements->execute([':id' => $id]);
            } catch (PDOException $e) {
                // Movements table might not have this product or constraint might be CASCADE
                if (function_exists('logError')) {
                    logError('Delete movements warning', ['message' => $e->getMessage()]);
                }
            }

            // Now delete the product
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
            $stmt->execute([':id' => $id]);

            sendSuccess(['id' => $id]);
            break;

        /**
         * POST inventory.php?action=add_stock
         * إضافة مخزون لفستان موجود
         */
        case 'add_stock':
            requireMethod('POST');
            $data = getJsonInput();
            validateRequired($data, ['dressId', 'quantity']);
            
            $dressId = (int)($data['dressId'] ?? 0);
            $quantity = (int)($data['quantity'] ?? 0);
            $supplierId = !empty($data['supplierId']) ? (int)($data['supplierId']) : null;
            $notes = sanitize(trim($data['notes'] ?? ''));

            if ($dressId <= 0 || $quantity <= 0) {
                sendError('Invalid product ID or quantity', 400);
            }

            $pdo->beginTransaction();

            // جلب الكمية الحالية
            $stmt = $pdo->prepare("SELECT quantity FROM products WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $dressId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                $pdo->rollBack();
                sendError('Product not found', 404);
            }

            $oldQty = (int)$current['quantity'];
            $newQty = $oldQty + $quantity;

            // تحديث كمية المنتج
            $stmt = $pdo->prepare("UPDATE products SET quantity = :qty WHERE id = :id");
            $stmt->execute([
                ':qty' => $newQty,
                ':id'  => $dressId,
            ]);

            // إدخال حركة المخزون
            $finalNotes = $notes;
            if ($finalNotes === '' && $supplierId !== null) {
                $supplierStmt = $pdo->prepare("SELECT name FROM suppliers WHERE id = :id");
                $supplierStmt->execute([':id' => $supplierId]);
                $supplierName = $supplierStmt->fetchColumn();
                if ($supplierName) {
                    $finalNotes = 'إضافة مخزون من المورد: ' . $supplierName;
                }
            } elseif ($finalNotes === '') {
                $finalNotes = 'إضافة مخزون';
            }

            $userId = getCurrentUserId();
            $stmtMov = $pdo->prepare("
                INSERT INTO inventory_movements (product_id, type, quantity, balance_after, notes, created_by)
                VALUES (:product_id, 'in', :quantity, :balance_after, :notes, :created_by)
            ");
            $stmtMov->execute([
                ':product_id'    => $dressId,
                ':quantity'      => $quantity,
                ':balance_after' => $newQty,
                ':notes'         => $finalNotes,
                ':created_by'    => $userId,
            ]);

            $pdo->commit();

            sendSuccess(['newQuantity' => $newQty]);
            break;

        default:
            sendError('Unknown action', 400);
            break;
    }
} catch (Throwable $e) {
    // Clean any output before sending error
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Log error (if logError function exists)
    if (function_exists('logError')) {
        logError('Inventory API Error', [
            'message' => $e->getMessage(), 
            'action' => $action,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    
    // Send clean JSON error response
    sendError('Server error: ' . $e->getMessage(), 500);
}

<?php
/**
 * Invoice Helper Functions
 * Auto-create and lock inventory items when creating invoices
 */

/**
 * Get or create default category for auto-created products
 */
function getOrCreateDefaultCategory($pdo) {
    $stmt = $pdo->prepare("
        SELECT id FROM categories 
        WHERE slug = 'auto-created' 
        LIMIT 1
    ");
    $stmt->execute();
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($category) {
        return $category['id'];
    }
    
    // Create default category
    $stmt = $pdo->prepare("
        INSERT INTO categories (name, slug, color, description)
        VALUES ('عناصر تلقائية', 'auto-created', '#95a5a6', 'عناصر تم إنشاؤها تلقائياً من الفواتير')
    ");
    $stmt->execute();
    
    return $pdo->lastInsertId();
}

/**
 * Auto-create product from invoice item
 * Returns product ID
 */
function autoCreateProduct($pdo, $itemData) {
    error_log("autoCreateProduct called with: " . json_encode($itemData));
    
    // Check if product already exists by exact name and size/color combination
    $checkSql = "SELECT id FROM products WHERE name = :name";
    $params = [':name' => $itemData['item_name']];
    
    if (!empty($itemData['size'])) {
        $checkSql .= " AND size = :size";
        $params[':size'] = $itemData['size'];
    }
    
    if (!empty($itemData['color'])) {
        $checkSql .= " AND color = :color";
        $params[':color'] = $itemData['color'];
    }
    
    $checkSql .= " LIMIT 1";
    
    $stmt = $pdo->prepare($checkSql);
    $stmt->execute($params);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        error_log("Product already exists with ID: " . $existing['id']);
        // Product exists, try to lock it if column exists
        try {
            $updateStmt = $pdo->prepare("UPDATE products SET is_locked = 1 WHERE id = :id");
            $updateStmt->execute([':id' => $existing['id']]);
        } catch (PDOException $e) {
            // is_locked column might not exist - that's OK
            error_log("Could not set is_locked (column may not exist): " . $e->getMessage());
        }
        return $existing['id'];
    }
    
    // Generate unique product code with category-based prefix
    // Use category character if provided, otherwise default to 'P' for Product
    $categoryPrefix = isset($itemData['category_char']) && !empty($itemData['category_char']) 
        ? strtoupper($itemData['category_char'])
        : 'P';
    
    // Get the next sequential number for this category
    $stmt = $pdo->prepare("
        SELECT code FROM products 
        WHERE code LIKE :prefix 
        ORDER BY code DESC 
        LIMIT 1
    ");
    $stmt->execute([':prefix' => $categoryPrefix . '-%']);
    $lastProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextNum = 1;
    if ($lastProduct && preg_match('/' . preg_quote($categoryPrefix) . '-(\d+)/', $lastProduct['code'], $matches)) {
        $nextNum = intval($matches[1]) + 1;
    }
    
    // Format: D-0001, A-0001, etc.
    $code = $categoryPrefix . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    
    // Ensure code is unique (double-check)
    $codeCheckStmt = $pdo->prepare("SELECT id FROM products WHERE code = :code");
    $codeCheckStmt->execute([':code' => $code]);
    while ($codeCheckStmt->fetch()) {
        $nextNum++;
        $code = $categoryPrefix . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        $codeCheckStmt->execute([':code' => $code]);
    }
    
    error_log("Generated unique product code: $code");
    
    // Get default category
    $categoryId = getOrCreateDefaultCategory($pdo);
    error_log("Using category ID: $categoryId");
    
    // Check if is_locked column exists
    $hasIsLocked = false;
    try {
        $columnCheck = $pdo->query("SHOW COLUMNS FROM products LIKE 'is_locked'");
        $hasIsLocked = $columnCheck->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Could not check for is_locked column: " . $e->getMessage());
    }
    
    // Create new product - handle both schemas
    if ($hasIsLocked) {
        $stmt = $pdo->prepare("
            INSERT INTO products (
                code, name, category_id, price, cost,
                size, color, quantity, status, is_locked,
                description, created_at, updated_at
            ) VALUES (
                :code, :name, :category_id, :price, :cost,
                :size, :color, 1, 'sold', 1,
                :description, NOW(), NOW()
            )
        ");
    } else {
        // Schema without is_locked column
        $stmt = $pdo->prepare("
            INSERT INTO products (
                code, name, category_id, price, cost,
                size, color, quantity, status,
                description, created_at, updated_at
            ) VALUES (
                :code, :name, :category_id, :price, :cost,
                :size, :color, 1, 'sold',
                :description, NOW(), NOW()
            )
        ");
    }
    
    try {
        $stmt->execute([
            ':code' => $code,
            ':name' => $itemData['item_name'],
            ':category_id' => $categoryId,
            ':price' => $itemData['unit_price'] ?? 0,
            ':cost' => 0,
            ':size' => $itemData['size'] ?? null,
            ':color' => $itemData['color'] ?? null,
            ':description' => 'تم إنشاؤه تلقائياً من الفاتورة'
        ]);
        
        $newProductId = $pdo->lastInsertId();
        error_log("Successfully created new product with ID: $newProductId");
        return $newProductId;
    } catch (PDOException $e) {
        error_log("ERROR creating product: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Check if product is locked
 */
function isProductLocked($pdo, $productId) {
    $stmt = $pdo->prepare("SELECT is_locked FROM products WHERE id = :id");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $product && $product['is_locked'] == 1;
}

/**
 * Lock product (mark as linked to invoice)
 */
function lockProduct($pdo, $productId) {
    $stmt = $pdo->prepare("UPDATE products SET is_locked = 1 WHERE id = :id");
    $stmt->execute([':id' => $productId]);
}

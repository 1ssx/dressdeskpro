<?php
// inventory_api.php - Multi-Store Support
header('Content-Type: application/json; charset=utf-8');

// Start session for store context
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connect to store database
if (!isset($_SESSION['store_db_name'])) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Store context not found. Please log in again.']));
}

$pdo = require __DIR__ . '/../app/config/store_database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// إنشاء مجلد للصور إذا لم يكن موجوداً
$uploadDir = '../assets/uploads/products/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

try {
    // --- 1. طلبات GET ---
    if ($method === 'GET') {
        
        // أ) إحصائيات لوحة المعلومات
        if ($action === 'stats') {
            // عدد الفساتين
            $stmt = $pdo->query("SELECT COUNT(*) FROM products");
            $totalDresses = $stmt->fetchColumn();
            
            // قيمة المخزون (التكلفة * الكمية)
            $stmt = $pdo->query("SELECT SUM(cost * quantity) FROM products");
            $totalValue = $stmt->fetchColumn() ?: 0;
            
            // عدد الفئات (التي تحتوي على منتجات أو الكل) - حسب الطلب: "Count from categories"
            $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
            $totalCategories = $stmt->fetchColumn();
            
            // عدد الموردين
            $stmt = $pdo->query("SELECT COUNT(*) FROM suppliers");
            $totalSuppliers = $stmt->fetchColumn();

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'total_dresses' => $totalDresses,
                    'total_value' => $totalValue,
                    'total_categories' => $totalCategories,
                    'total_suppliers' => $totalSuppliers
                ]
            ]);
            exit;
        }

        // ب) توليد كود SKU التالي
        if ($action === 'next_sku') {
            $categoryChar = $_GET['category_char'] ?? 'A'; // Default to A
            // Validate char
            if (!in_array($categoryChar, ['A', 'B', 'C'])) {
                $categoryChar = 'A';
            }
            
            // البحث عن أكبر رقم للكود يبدأ بهذا الحرف
            // نفترض الصيغة A001, B005, إلخ.
            // نستخدم REGEXP لاستخراج الأرقام فقط بعد الحرف
            $stmt = $pdo->prepare("
                SELECT code 
                FROM products 
                WHERE code LIKE ? 
                ORDER BY LENGTH(code) DESC, code DESC 
                LIMIT 1
            ");
            $stmt->execute([$categoryChar . '%']);
            $lastCode = $stmt->fetchColumn();
            
            if ($lastCode) {
                // استخراج الرقم
                $num = intval(substr($lastCode, 1));
                $nextNum = $num + 1;
            } else {
                $nextNum = 1;
            }
            
            // تنسيق الرقم (مثلاً 001)
            $nextSku = $categoryChar . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
            
            echo json_encode(['status' => 'success', 'sku' => $nextSku]);
            exit;
        }

        // ج) جلب المنتجات (الافتراضي) - مع عدد مرات التأجير
        // Small optimization: allow caller to request a limited number of rows via ?limit=N
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
        
        $sql = "
            SELECT p.*, COALESCE(r.rental_count, 0) as rental_count 
            FROM products p
            LEFT JOIN (
                SELECT ii.product_id, COUNT(*) as rental_count 
                FROM invoice_items ii
                JOIN invoices inv ON ii.invoice_id = inv.id
                WHERE inv.invoice_status IN ('returned', 'closed') 
                  AND (inv.operation_type = 'rent' OR inv.operation_type = 'design-rent')
                GROUP BY ii.product_id
            ) r ON p.id = r.product_id
            ORDER BY p.created_at DESC
        ";
        
        if ($limit > 0) {
            $sql .= " LIMIT $limit";
        }

        $stmt = $pdo->query($sql);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $products]);
    }

    // --- 2. طلبات POST (إضافة/حذف) ---
    elseif ($method === 'POST') {
        
        // التحقق من الحذف
        if ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            echo json_encode(['status' => 'success', 'message' => 'تم حذف المنتج']);
            exit;
        }

        // معالجة رفع الصورة
        $imagePath = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='150' height='150'><rect width='150' height='150' fill='%23e0e0e0'/><text x='50%25' y='55%25' font-size='14' text-anchor='middle' fill='%23666' font-family='Arial' dy='.3em'>صورة</text></svg>"; // صورة افتراضية
        if (isset($_FILES['dress_image']) && $_FILES['dress_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['dress_image']['tmp_name'];
            $fileName = time() . '_' . $_FILES['dress_image']['name'];
            $destPath = $uploadDir . $fileName;
            
            if(move_uploaded_file($fileTmpPath, $destPath)) {
                $imagePath = '../assets/uploads/products/' . $fileName;
            }
        }

        // Refactored Fields Logic:
        // Use provided category_id or lookup based on fixed categories A, B, C
        // Map category input (A, B, C) to Database IDs
        $catInput = $_POST['category_id'] ?? '';
        $categoryId = null;
        
        if (is_numeric($catInput)) {
            $categoryId = intval($catInput);
        } else {
            // Helper to find category ID by name part
            $findCatId = function($namePart) use ($pdo) {
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE name LIKE ? LIMIT 1");
                $stmt->execute(["%$namePart%"]);
                return $stmt->fetchColumn();
            };

            if ($catInput === 'A') { // Wedding
                $categoryId = $findCatId('زفاف') ?: 1;
            } elseif ($catInput === 'B') { // Engagement
                $categoryId = $findCatId('خطوبة');
                if (!$categoryId) {
                    // Create Engagement Category if missing
                    $stmtIns = $pdo->prepare("INSERT INTO categories (name, slug, color) VALUES ('فساتين خطوبة', 'engagement-dresses', '#fab1a0')");
                    $stmtIns->execute();
                    $categoryId = $pdo->lastInsertId();
                }
            } elseif ($catInput === 'C') { // Evening
                $categoryId = $findCatId('سهرة') ?: 2;
            }
        }
        
        // Deprecated fields (set to null or default)
        $supplierId = null; // Removing supplier logic
        $size = null;       // Removing size logic
        $minQuantity = 0;   // Removing min_stock logic

        // Validate Uniqueness of Code
        $code = $_POST['code'] ?? '';
        $stmtCheck = $pdo->prepare("SELECT count(*) FROM products WHERE code = ?");
        $stmtCheck->execute([$code]);
        if ($stmtCheck->fetchColumn() > 0) {
            throw new Exception("كود الفستان '$code' موجود مسبقاً. الرجاء اختيار كود آخر.");
        }
        
        // إدخال البيانات للقاعدة (Refactored Schema)
        // Note: keeping columns in INSERT even if null to satisfy DB schema if not updated
        $stmt = $pdo->prepare("
            INSERT INTO products (
                code, name, category_id, supplier_id, price, cost, 
                size, color, fabric_type, quantity, min_quantity, 
                description, image, image_url
            ) VALUES (
                :code, :name, :category_id, :supplier_id, :price, :cost, 
                :size, :color, :fabric_type, :quantity, :min_quantity, 
                :description, :image, :image_url
            )
        ");

        $stmt->execute([
            ':code'         => $code,
            ':name'         => $_POST['name'],
            ':category_id'  => $categoryId,
            ':supplier_id'  => $supplierId,
            ':price'        => floatval($_POST['price'] ?? 0),
            ':cost'         => floatval($_POST['cost'] ?? 0),
            ':size'         => $size,
            ':color'        => $_POST['color'] ?? null,
            ':fabric_type'  => $_POST['fabric_type'] ?? null,
            ':quantity'     => intval($_POST['quantity'] ?? 0),
            ':min_quantity' => $minQuantity,
            ':description'  => $_POST['description'] ?? null,
            ':image'        => $imagePath,
            ':image_url'    => $imagePath
        ]);

        // تسجيل حركة أولية للمخزون
        $productId = $pdo->lastInsertId();
        $stmtMove = $pdo->prepare("INSERT INTO inventory_movements (product_id, type, quantity, balance_after, notes) VALUES (?, 'in', ?, ?, 'رصيد افتتاحي')");
        $stmtMove->execute([$productId, $_POST['quantity'], $_POST['quantity']]);

        echo json_encode(['status' => 'success', 'message' => 'تم إضافة الفستان بنجاح']);
    }

} catch (Exception $e) {
    http_response_code(400); // Bad Request for logic errors
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
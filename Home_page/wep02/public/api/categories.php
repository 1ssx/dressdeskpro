<?php
/**
 * Categories API
 * PHASE 3 - Refactored to use common infrastructure
 */

// Load common infrastructure
require_once __DIR__ . '/_common/bootstrap.php';

$action = getQueryParam('action', 'list');

/**
 * تحويل نص عربي/إنجليزي إلى slug (value) مناسب للحفظ في قاعدة البيانات
 */
function slugify(string $name): string {
    $slug = trim($name);
    $slug = mb_strtolower($slug, 'UTF-8');
    // استبدال المسافات بشرطة
    $slug = preg_replace('/\s+/u', '-', $slug);
    // إزالة أي أحرف غريبة
    $slug = preg_replace('/[^a-z0-9\-_\p{Arabic}]/u', '', $slug);
    return $slug ?: 'cat-' . time();
}

try {
    switch ($action) {

        /**
         * GET categories.php?action=list
         * جلب كل الفئات + عدد الفساتين في كل فئة
         */
        case 'list':
            // LAZY INITIALIZATION: Check if categories exist, if not seed defaults
            $checkCount = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
            if ($checkCount == 0) {
                // Seed default categories
                $defaults = [
                    ['name' => 'فساتين زفاف', 'slug' => 'wedding-dresses', 'color' => '#e84393'],
                    ['name' => 'فساتين سهرة', 'slug' => 'evening-dresses', 'color' => '#6c5ce7'],
                    ['name' => 'طرح', 'slug' => 'veils', 'color' => '#00cec9'],
                    ['name' => 'اكسسوارات', 'slug' => 'accessories', 'color' => '#fdcb6e'],
                    ['name' => 'عبايات', 'slug' => 'abayas', 'color' => '#2d3436']
                ];
                
                $seedStmt = $pdo->prepare("INSERT INTO categories (name, slug, color) VALUES (:name, :slug, :color)");
                foreach ($defaults as $cat) {
                    try {
                        $seedStmt->execute($cat);
                    } catch (Exception $e) {
                        // Ignore duplicate errors
                    }
                }
            }

            // جلب الفئات مع عدد المنتجات (v2 schema: category_id)
            $stmt = $pdo->query("
                SELECT 
                    c.*,
                    COUNT(p.id) AS product_count
                FROM categories c
                LEFT JOIN products p ON c.id = p.category_id
                GROUP BY c.id
                ORDER BY c.id ASC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $c) {
                $result[] = [
                    'id'            => (int)$c['id'],
                    'name'          => $c['name'],
                    'slug'          => $c['slug'] ?? $c['value'] ?? '',
                    'value'         => $c['slug'] ?? $c['value'] ?? '',
                    'description'   => $c['description'] ?? '',
                    'color'         => $c['color'] ?? '#3498db',
                    'product_count' => (int)($c['product_count'] ?? 0),
                ];
            }

            sendSuccess($result);
            break;

        /**
         * POST categories.php?action=create
         * إنشاء فئة جديدة
         */
        case 'create':
            requireMethod('POST');
            $data = getJsonInput();
            validateRequired($data, ['name']);
            
            $name = sanitize(trim($data['name'] ?? ''));
            $description = $data['description'] ?? null;
            $color = $data['color'] ?? '#3498db';

            $slug = slugify($name);

            $stmt = $pdo->prepare(
                "INSERT INTO categories (name, slug, description, color)
                 VALUES (:name, :slug, :description, :color)"
            );
            $stmt->execute([
                ':name'        => $name,
                ':slug'        => $slug,
                ':description' => $description,
                ':color'       => $color,
            ]);

            $id = (int)$pdo->lastInsertId();
            sendSuccess(['id' => $id, 'slug' => $slug]);
            break;

        /**
         * POST categories.php?action=update
         * تعديل فئة موجودة + تحديث قيمة category في products إذا تغيرت
         */
        case 'update':
            requireMethod('POST');
            $data = getJsonInput();
            validateRequired($data, ['id', 'name']);
            
            $id   = (int)($data['id'] ?? 0);
            $name = sanitize(trim($data['name'] ?? ''));
            $description = $data['description'] ?? null;
            $color = $data['color'] ?? '#3498db';

            $slug = slugify($name);

            // تحديث الفئة (v2 schema: uses slug, not value)
            $stmt = $pdo->prepare(
                "UPDATE categories 
                 SET name = :name, slug = :slug, description = :description, color = :color
                 WHERE id = :id"
            );
            $stmt->execute([
                ':name'        => $name,
                ':slug'        => $slug,
                ':description' => $description,
                ':color'       => $color,
                ':id'          => $id,
            ]);

            sendSuccess(['id' => $id, 'slug' => $slug]);
            break;

        /**
         * POST categories.php?action=delete
         * حذف فئة إذا لم يكن هناك منتجات مرتبطة بها
         */
        case 'delete':
            requireMethod('POST');
            $data = getJsonInput();
            validateRequired($data, ['id']);
            
            $id = (int)($data['id'] ?? 0);

            // التأكد من عدم وجود منتجات مرتبطة بها (v2 schema: category_id)
            $stmtCnt = $pdo->prepare(
                "SELECT COUNT(*) AS cnt FROM products WHERE category_id = :id"
            );
            $stmtCnt->execute([':id' => $id]);
            $cnt = (int)$stmtCnt->fetchColumn();

            if ($cnt > 0) {
                sendError('Cannot delete category with associated products', 400);
                break;
            }

            $stmtDel = $pdo->prepare("DELETE FROM categories WHERE id = :id");
            $stmtDel->execute([':id' => $id]);

            sendSuccess(['id' => $id]);
            break;

        default:
            sendError('Unknown action', 400);
            break;
    }
} catch (Throwable $e) {
    logError('Categories API Error', ['message' => $e->getMessage()]);
    sendError('Server error: ' . $e->getMessage(), 500);
}

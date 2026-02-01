<?php
/**
 * Suppliers API (Multi-schema compatible)
 * - Works with products.supplier_id (v2 schema) OR products.supplier (legacy schema)
 * - Works with suppliers.contact_person OR suppliers.contact
 * - Accepts JSON body OR form-data POST
 */

require_once __DIR__ . '/_common/bootstrap.php';

$action = $_GET['action'] ?? 'list';

/**
 * Read input from:
 * - $_POST (FormData / classic POST)
 * - JSON body (fetch with application/json)
 */
function getInput(): array {
    if (!empty($_POST)) return $_POST;

    $raw = file_get_contents('php://input');
    if ($raw && trim($raw) !== '') {
        $data = json_decode($raw, true);
        if (is_array($data)) return $data;
    }

    return [];
}

/** Check if a column exists in a table */
function columnExists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($row['Field']) && strcasecmp($row['Field'], $column) === 0) {
                return true;
            }
        }
        return false;
    } catch (Throwable $e) {
        // لو الجدول نفسه غير موجود أو فيه مشكلة، اعتبر العمود غير موجود
        return false;
    }
}


try {
    // Detect schema capabilities
    $productsHasSupplierId   = columnExists($pdo, 'products', 'supplier_id');
    $productsHasSupplierName = columnExists($pdo, 'products', 'supplier'); // legacy

    $supHasContactPerson = columnExists($pdo, 'suppliers', 'contact_person');
    $supHasContact       = columnExists($pdo, 'suppliers', 'contact');
    $supHasPhone         = columnExists($pdo, 'suppliers', 'phone');
    $supHasEmail         = columnExists($pdo, 'suppliers', 'email');
    $supHasAddress       = columnExists($pdo, 'suppliers', 'address');
    $supHasNotes         = columnExists($pdo, 'suppliers', 'notes');

    switch ($action) {

        /**
         * GET suppliers.php?action=list
         */
        case 'list': {
            $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY id ASC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $countsById = [];
            $countsByName = [];

            // Count products per supplier depending on schema
            if ($productsHasSupplierId) {
                $stmtCnt = $pdo->query("
                    SELECT supplier_id, COUNT(*) AS cnt
                    FROM products
                    WHERE supplier_id IS NOT NULL
                    GROUP BY supplier_id
                ");
                foreach ($stmtCnt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $countsById[(int)$r['supplier_id']] = (int)$r['cnt'];
                }
            } elseif ($productsHasSupplierName) {
                $stmtCnt = $pdo->query("
                    SELECT supplier AS supplier_name, COUNT(*) AS cnt
                    FROM products
                    WHERE supplier IS NOT NULL AND supplier <> ''
                    GROUP BY supplier
                ");
                foreach ($stmtCnt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $countsByName[(string)$r['supplier_name']] = (int)$r['cnt'];
                }
            }

            $result = [];
            foreach ($rows as $s) {
                $id = (int)($s['id'] ?? 0);
                $name = (string)($s['name'] ?? '');

                $contactValue = null;
                if ($supHasContactPerson) $contactValue = $s['contact_person'] ?? null;
                elseif ($supHasContact)   $contactValue = $s['contact'] ?? null;

                $result[] = [
                    'id'            => $id,
                    'name'          => $name,
                    'contact'       => $contactValue,
                    'phone'         => $supHasPhone ? ($s['phone'] ?? null) : null,
                    'email'         => $supHasEmail ? ($s['email'] ?? null) : null,
                    'address'       => $supHasAddress ? ($s['address'] ?? null) : null,
                    'notes'         => $supHasNotes ? ($s['notes'] ?? null) : null,
                    'productsCount' => $productsHasSupplierId
                        ? ($countsById[$id] ?? 0)
                        : ($countsByName[$name] ?? 0),
                ];
            }

            sendSuccess($result);
            break;
        }

        /**
         * POST suppliers.php?action=create
         */
        case 'create': {
            requireMethod('POST');
            $data = getInput();

            $name = trim($data['name'] ?? '');
            if ($name === '') sendError('Name is required', 400);

            $contact = !empty($data['contact']) ? sanitize($data['contact']) : null;
            $phone   = !empty($data['phone']) ? sanitize($data['phone']) : null;
            $email   = !empty($data['email']) ? sanitize($data['email']) : null;
            $address = !empty($data['address']) ? sanitize($data['address']) : null;
            $notes   = !empty($data['notes']) ? sanitize($data['notes']) : null;

            // Duplicate name check
            $checkStmt = $pdo->prepare("SELECT id FROM suppliers WHERE name = :name LIMIT 1");
            $checkStmt->execute([':name' => $name]);
            if ($checkStmt->fetch()) {
                sendError('Supplier with this name already exists', 400);
            }

            // Build insert dynamically based on existing columns
            $cols = ['name'];
            $params = [':name' => $name];

            if ($supHasContactPerson) { $cols[] = 'contact_person'; $params[':contact'] = $contact; }
            elseif ($supHasContact)   { $cols[] = 'contact';        $params[':contact'] = $contact; }

            if ($supHasPhone)   { $cols[] = 'phone';   $params[':phone'] = $phone; }
            if ($supHasEmail)   { $cols[] = 'email';   $params[':email'] = $email; }
            if ($supHasAddress) { $cols[] = 'address'; $params[':address'] = $address; }
            if ($supHasNotes)   { $cols[] = 'notes';   $params[':notes'] = $notes; }

            $placeholders = [];
            foreach ($cols as $c) {
                if ($c === 'name') $placeholders[] = ':name';
                elseif ($c === 'contact_person' || $c === 'contact') $placeholders[] = ':contact';
                else $placeholders[] = ':' . $c;
            }

            $sql = "INSERT INTO suppliers (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $id = (int)$pdo->lastInsertId();
            sendSuccess(['id' => $id, 'message' => 'تم إضافة المورد بنجاح']);
            break;
        }

        /**
         * POST suppliers.php?action=update
         */
        case 'update': {
            requireMethod('POST');
            $data = getInput();

            $id = (int)($data['id'] ?? 0);
            $name = trim($data['name'] ?? '');
            if ($id <= 0 || $name === '') sendError('Invalid data', 400);

            $contact = !empty($data['contact']) ? sanitize($data['contact']) : null;
            $phone   = !empty($data['phone']) ? sanitize($data['phone']) : null;
            $email   = !empty($data['email']) ? sanitize($data['email']) : null;
            $address = !empty($data['address']) ? sanitize($data['address']) : null;
            $notes   = !empty($data['notes']) ? sanitize($data['notes']) : null;

            // Existing supplier
            $stmtOld = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id LIMIT 1");
            $stmtOld->execute([':id' => $id]);
            $old = $stmtOld->fetch(PDO::FETCH_ASSOC);
            if (!$old) sendError('Supplier not found', 404);

            $oldName = (string)($old['name'] ?? '');

            // Duplicate name check (excluding same id)
            if ($oldName !== $name) {
                $checkStmt = $pdo->prepare("SELECT id FROM suppliers WHERE name = :name AND id != :id LIMIT 1");
                $checkStmt->execute([':name' => $name, ':id' => $id]);
                if ($checkStmt->fetch()) {
                    sendError('Supplier with this name already exists', 400);
                }
            }

            $pdo->beginTransaction();

            // Build update dynamically
            $sets = ["name = :name"];
            $params = [':name' => $name, ':id' => $id];

            if ($supHasContactPerson) { $sets[] = "contact_person = :contact"; $params[':contact'] = $contact; }
            elseif ($supHasContact)   { $sets[] = "contact = :contact";        $params[':contact'] = $contact; }

            if ($supHasPhone)   { $sets[] = "phone = :phone";     $params[':phone'] = $phone; }
            if ($supHasEmail)   { $sets[] = "email = :email";     $params[':email'] = $email; }
            if ($supHasAddress) { $sets[] = "address = :address"; $params[':address'] = $address; }
            if ($supHasNotes)   { $sets[] = "notes = :notes";     $params[':notes'] = $notes; }

            $stmt = $pdo->prepare("UPDATE suppliers SET " . implode(', ', $sets) . " WHERE id = :id");
            $stmt->execute($params);

            // If legacy schema uses products.supplier (text), update references by name
            if ($productsHasSupplierName && $oldName !== $name) {
                $stmtP = $pdo->prepare("UPDATE products SET supplier = :newName WHERE supplier = :oldName");
                $stmtP->execute([':newName' => $name, ':oldName' => $oldName]);
            }

            $pdo->commit();
            sendSuccess(['message' => 'تم تحديث المورد بنجاح']);
            break;
        }

        /**
         * POST suppliers.php?action=delete
         */
        case 'delete': {
            requireMethod('POST');
            $data = getInput();

            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) sendError('Invalid id', 400);

            $stmt = $pdo->prepare("SELECT id, name FROM suppliers WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $sup = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sup) sendError('Supplier not found', 404);

            $supName = (string)($sup['name'] ?? '');

            // Prevent deletion if products linked
            if ($productsHasSupplierId) {
                $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = :id");
                $stmtCnt->execute([':id' => $id]);
                $cnt = (int)$stmtCnt->fetchColumn();
                if ($cnt > 0) sendError('Cannot delete supplier with linked products', 400);
            } elseif ($productsHasSupplierName) {
                $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier = :name");
                $stmtCnt->execute([':name' => $supName]);
                $cnt = (int)$stmtCnt->fetchColumn();
                if ($cnt > 0) sendError('Cannot delete supplier with linked products', 400);
            }

            $stmtDel = $pdo->prepare("DELETE FROM suppliers WHERE id = :id");
            $stmtDel->execute([':id' => $id]);

            sendSuccess(['message' => 'تم حذف المورد بنجاح']);
            break;
        }

        default:
            sendError('Unknown action', 400);
    }

} catch (Throwable $e) {
    logError('Suppliers API Error', [
        'message' => $e->getMessage(),
        'action'  => $action,
    ]);
    sendError('Failed to process request: ' . $e->getMessage(), 500);
}

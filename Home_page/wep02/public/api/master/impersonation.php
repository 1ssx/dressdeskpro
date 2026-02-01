<?php
// Always JSON
if (!ob_get_level()) ob_start();
header('Content-Type: application/json; charset=utf-8');

// Debug (PRODUCTION: keep at 0)
ini_set('display_errors', 0);
error_reporting(E_ALL);

function send_json($code, $payload) {
    if (ob_get_level()) { @ob_clean(); }
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Catch fatal errors (require/class not found…)
register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) return;
    $fatal = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR);
    if (!in_array($err['type'], $fatal, true)) return;

    error_log("❌ FATAL impersonation.php: {$err['message']} in {$err['file']}:{$err['line']}");
    send_json(500, array(
        'status' => 'error',
        'message' => 'Fatal server error in impersonation.php',
        'debug'  => $err['message']
    ));
});

session_start();

// ---- Parse request (supports form + JSON fetch) ----
$req = array();
if (!empty($_GET))  $req = array_merge($req, $_GET);
if (!empty($_POST)) $req = array_merge($req, $_POST);

$ct = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) $req = array_merge($req, $json);
}

$action = isset($req['action']) ? $req['action'] : null;
if (!$action && isset($req['store_id'])) $action = 'login_as_store';

// ---- Compute /public base for redirects ----
$script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$publicBase = preg_replace('~/public/.*$~', '/public', $script);
$redirectStore  = $publicBase . '/index.php';
$redirectMaster = $publicBase . '/master_dashboard.php';

// ---- Auth check ----
if (!isset($_SESSION['master_user_id']) || !isset($_SESSION['master_role']) || $_SESSION['master_role'] !== 'owner') {
    send_json(403, array(
        'status' => 'error',
        'message' => 'Unauthorized: Master admin access required',
        'debug' => 'Session check failed (master_user_id/master_role)'
    ));
}

$adminId   = $_SESSION['master_user_id'];
$adminName = isset($_SESSION['master_user_name']) ? $_SESSION['master_user_name'] : 'System Owner';

// ---- Load master DB safely (supports "return PDO" OR "$pdo = new PDO") ----
$masterDbFile = __DIR__ . '/../../../app/config/master_database.php';
if (!file_exists($masterDbFile)) {
    send_json(500, array(
        'status' => 'error',
        'message' => 'master_database.php not found',
        'debug' => $masterDbFile
    ));
}

$pdoMaster = null;
$pdo = null; // in case included file sets $pdo
$maybe = require $masterDbFile;

if ($maybe instanceof PDO) $pdoMaster = $maybe;
else if (isset($pdo) && $pdo instanceof PDO) $pdoMaster = $pdo;

if (!($pdoMaster instanceof PDO)) {
    send_json(500, array(
        'status' => 'error',
        'message' => 'Master DB connection not available (master_database.php did not provide PDO)',
        'debug' => 'Fix by returning $pdo from master_database.php OR define $pdo as PDO.'
    ));
}

// Load centralized database credentials for store connections
$dbCreds = require __DIR__ . '/../../../app/config/db_credentials.php';

// ---- Audit logger optional (never crash) ----
$auditLogger = null;
$auditFile = __DIR__ . '/../../../app/helpers/audit_logger.php';
if (file_exists($auditFile)) {
    require_once $auditFile;
    if (class_exists('AuditLogger')) {
        try { $auditLogger = new AuditLogger($pdoMaster); } catch (Exception $e) { $auditLogger = null; }
    }
}
if (!$auditLogger) {
    $auditLogger = new class {
        public function log() { /* noop */ }
    };
}

try {
    switch ($action) {

        case 'login_as_store':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_json(405, array('status' => 'error', 'message' => 'Method not allowed'));
            }

            $storeId = isset($req['store_id']) ? $req['store_id'] : null;
            if ($storeId === null || $storeId === '' || !ctype_digit((string)$storeId)) {
                send_json(400, array('status' => 'error', 'message' => 'Valid store_id is required'));
            }
            $storeId = (int)$storeId;

            $stmt = $pdoMaster->prepare('SELECT id, store_name, database_name, status, owner_name, owner_email FROM stores WHERE id = ? LIMIT 1');
            $stmt->execute(array($storeId));
            $store = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$store) send_json(404, array('status' => 'error', 'message' => 'Store not found'));
            if (isset($store['status']) && $store['status'] === 'deleted') {
                send_json(400, array('status' => 'error', 'message' => 'Cannot impersonate deleted store'));
            }

            $storeDbName = isset($store['database_name']) ? trim($store['database_name']) : '';
            if ($storeDbName === '') $storeDbName = 'wep_store_' . $storeId;

            // Connect store DB using centralized credentials
            try {
                $pdoStore = new PDO(
                    "mysql:host={$dbCreds['host']};dbname={$storeDbName};charset=utf8mb4",
                    $dbCreds['user'],
                    $dbCreds['password'],
                    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
                );
            } catch (PDOException $e) {
                send_json(500, array(
                    'status' => 'error',
                    'message' => "Cannot connect to store database: {$storeDbName}",
                    'debug' => $e->getMessage()
                ));
            }

            // Find store user (fallback to ANY user, otherwise virtual owner)
            $storeUser = null;
            try {
                $q = $pdoStore->query("SELECT id, name, email, role FROM users WHERE role IN ('admin','owner') ORDER BY id ASC LIMIT 1");
                $storeUser = $q->fetch(PDO::FETCH_ASSOC);

                if (!$storeUser) {
                    $q = $pdoStore->query("SELECT id, name, email, 'admin' AS role FROM users ORDER BY id ASC LIMIT 1");
                    $storeUser = $q->fetch(PDO::FETCH_ASSOC);
                }
            } catch (PDOException $e) {
                // users table missing -> fallback
                $storeUser = null;
            }

            if (!$storeUser) {
                // Virtual owner session (works حتى لو المتجر غير مهيأ 100%)
                $storeUser = array(
                    'id' => 0,
                    'name' => $adminName,
                    'email' => isset($_SESSION['master_user_email']) ? $_SESSION['master_user_email'] : (isset($store['owner_email']) ? $store['owner_email'] : ''),
                    'role' => 'owner'
                );
            }

            // Session flags
            $_SESSION['impersonation_active'] = true;
            $_SESSION['impersonation_master_id'] = $adminId;
            $_SESSION['impersonation_master_name'] = $adminName;
            $_SESSION['impersonation_master_role'] = 'owner';
            $_SESSION['impersonation_store_id'] = $store['id'];
            $_SESSION['impersonation_timestamp'] = time();

            // Store session
            $_SESSION['user_id'] = $storeUser['id'];
            $_SESSION['user_name'] = $storeUser['name'];
            $_SESSION['user_email'] = $storeUser['email'];
            $_SESSION['user_role'] = $storeUser['role'];

            $_SESSION['store_name'] = $store['store_name'];
            $_SESSION['store_db_name'] = $storeDbName;
            $_SESSION['store_id'] = $store['id'];

            @session_regenerate_id(true);

            // Update last_login
            try {
                $u = $pdoMaster->prepare("UPDATE stores SET last_login = NOW() WHERE id = ?");
                $u->execute(array($storeId));
            } catch (Exception $e) {}

            // Audit (non-blocking)
            try {
                $auditLogger->log('impersonation_login', "Owner impersonated store {$store['store_name']}", $adminId, $adminName, $store['id'], $store['store_name']);
            } catch (Exception $e) {}

            send_json(200, array(
                'status' => 'success',
                'message' => 'Impersonation successful',
                'redirect' => $redirectStore
            ));

        case 'exit_impersonation':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_json(405, array('status' => 'error', 'message' => 'Method not allowed'));
            }

            // Clear store session
            unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role']);
            unset($_SESSION['store_name'], $_SESSION['store_db_name'], $_SESSION['store_id']);

            // Clear impersonation flags
            unset($_SESSION['impersonation_active'], $_SESSION['impersonation_master_id'], $_SESSION['impersonation_master_name'], $_SESSION['impersonation_master_role'], $_SESSION['impersonation_store_id'], $_SESSION['impersonation_timestamp']);

            send_json(200, array(
                'status' => 'success',
                'message' => 'Exited impersonation mode',
                'redirect' => $redirectMaster
            ));

        default:
            send_json(400, array('status' => 'error', 'message' => 'Invalid action', 'debug' => $action));
    }

} catch (PDOException $e) {
    send_json(500, array('status' => 'error', 'message' => 'Database error', 'debug' => $e->getMessage()));
} catch (Exception $e) {
    send_json(500, array('status' => 'error', 'message' => 'Server error', 'debug' => $e->getMessage()));
}

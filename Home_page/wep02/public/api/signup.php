<?php
/**
 * Signup endpoint: Creates a new store + first owner user
 * Validates activation code, creates store database, and registers first user
 * Returns JSON responses only.
 */

declare(strict_types=1);

// Start output buffering to prevent any unwanted output
ob_start();

header('Content-Type: application/json; charset=utf-8');

// Require master database connection for activation code validation
$pdoMaster = require __DIR__ . '/../../app/config/master_database.php';
require __DIR__ . '/../../app/helpers/functions.php';

// Load centralized database credentials
$dbCreds = require __DIR__ . '/../../app/config/db_credentials.php';

$errors = [];

// Read POST data
$fullName        = sanitizeInput($_POST['full_name'] ?? '');
$email           = sanitizeInput($_POST['email'] ?? '');
$password        = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$phone           = sanitizeInput($_POST['phone'] ?? '');
$storeName       = sanitizeInput($_POST['store_name'] ?? '');
$activationCode  = sanitizeInput($_POST['activation_code'] ?? '');
$termsAccepted   = $_POST['terms'] ?? '';

// Validation
if ($fullName === '') {
    $errors['full_name'] = 'Full name is required.';
}

if ($email === '') {
    $errors['email'] = 'Email is required.';
} elseif (!isValidEmail($email)) {
    $errors['email'] = 'Email format is invalid.';
}

if ($storeName === '') {
    $errors['store_name'] = 'Store name is required.';
}

if ($activationCode === '') {
    $errors['activation_code'] = 'Activation code is required.';
}

if ($password === '') {
    $errors['password'] = 'Password is required.';
} elseif (!isValidPassword($password)) {
    $errors['password'] = 'Password must be at least 8 characters and include letters and numbers.';
}

if ($confirmPassword === '') {
    $errors['confirm_password'] = 'Please confirm your password.';
} elseif ($password !== $confirmPassword) {
    $errors['confirm_password'] = 'Passwords do not match.';
}

if (!isValidPhone($phone)) {
    $errors['phone'] = 'Phone number is invalid.';
}

if ($termsAccepted !== '1') {
    $errors['terms'] = 'You must accept the terms and conditions.';
}

// Early return on validation errors
if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'errors'  => $errors,
    ]);
    exit;
}

try {
    // Step 1: Validate activation code against master DB
    $stmt = $pdoMaster->prepare('
        SELECT id, status, expires_at, max_uses 
        FROM license_keys 
        WHERE code = :code 
        LIMIT 1
    ');
    $stmt->execute([':code' => $activationCode]);
    $licenseKey = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$licenseKey) {
        echo json_encode([
            'success' => false,
            'errors'  => ['activation_code' => 'Activation code is invalid or already used.'],
        ]);
        exit;
    }

    // Check if code is already used
    if ($licenseKey['status'] !== 'unused') {
        echo json_encode([
            'success' => false,
            'errors'  => ['activation_code' => 'Activation code is invalid or already used.'],
        ]);
        exit;
    }

    // Check if code is expired
    if ($licenseKey['expires_at'] !== null && strtotime($licenseKey['expires_at']) < time()) {
        echo json_encode([
            'success' => false,
            'errors'  => ['activation_code' => 'Activation code has expired.'],
        ]);
        exit;
    }

    // Step 2: Check if store name already exists (BEFORE inserting anything)
    $stmt = $pdoMaster->prepare('SELECT id FROM stores WHERE store_name = :store_name LIMIT 1');
    $stmt->execute([':store_name' => $storeName]);
    if ($stmt->fetch()) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'errors'  => ['store_name' => 'Store name already exists. Please choose a different name.'],
        ]);
        exit;
    }

    // Step 3: Begin transaction for store record insertion
    $pdoMaster->beginTransaction();
    
    try {
        // Step 4: Insert store record first to get the actual AUTO_INCREMENT ID
        // Use temporary database name initially
        $tempDbName = 'temp_' . time() . '_' . rand(1000, 9999);
        
        $stmt = $pdoMaster->prepare('
            INSERT INTO stores (store_name, owner_name, owner_email, database_name, activation_code_used, status, created_at, updated_at)
            VALUES (:store_name, :owner_name, :owner_email, :database_name, :activation_code, :status, NOW(), NOW())
        ');
        $stmt->execute([
            ':store_name' => $storeName,
            ':owner_name' => $fullName,
            ':owner_email' => $email,
            ':database_name' => $tempDbName, // Temporary, will update after getting ID
            ':activation_code' => $activationCode,
            ':status' => 'active'
        ]);
        
        // Get the actual inserted store ID
        $storeId = $pdoMaster->lastInsertId();
        
        // Build the correct database name: wep_store_{ID}
        $newDbName = 'wep_store_' . $storeId;
        
        // Update the database_name with the correct name
        $updateStmt = $pdoMaster->prepare('UPDATE stores SET database_name = :db_name WHERE id = :id');
        $updateStmt->execute([':db_name' => $newDbName, ':id' => $storeId]);
        
        // Commit the store record insertion (database creation can't be in transaction)
        $pdoMaster->commit();
        
        // Step 5: Check if database already exists (safety check)
        $stmt = $pdoMaster->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$newDbName'");
        if ($stmt->fetch()) {
            // Database already exists - this is a problem, rollback store record
            $pdoMaster->beginTransaction();
            $pdoMaster->prepare('DELETE FROM stores WHERE id = ?')->execute([$storeId]);
            $pdoMaster->commit();
            throw new Exception("Database $newDbName already exists. This should not happen.");
        }

        // Step 6: Create the new database (outside transaction - MySQL doesn't support DDL in transactions)
        // Connect without database to create new database
        try {
            $pdoForCreate = new PDO(
                "mysql:host={$dbCreds['host']};charset=utf8mb4",
                $dbCreds['user'],
                $dbCreds['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdoForCreate->exec("CREATE DATABASE `$newDbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            // Database creation failed - delete store record
            $pdoMaster->beginTransaction();
            $pdoMaster->prepare('DELETE FROM stores WHERE id = ?')->execute([$storeId]);
            $pdoMaster->commit();
            throw new Exception("Failed to create database: $newDbName. Error: " . $e->getMessage());
        }
        
        // Verify database was created
        $stmt = $pdoMaster->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$newDbName'");
        if (!$stmt->fetch()) {
            // Database creation failed - delete store record
            $pdoMaster->beginTransaction();
            $pdoMaster->prepare('DELETE FROM stores WHERE id = ?')->execute([$storeId]);
            $pdoMaster->commit();
            throw new Exception("Database $newDbName was not created successfully (verification failed)");
        }
        
        // Begin new transaction for remaining operations
        $pdoMaster->beginTransaction();

        // Step 7: Execute schema SQL on new database
        $schemaFile = __DIR__ . '/../../sql/create_tables_v2.sql';
        
        // Resolve absolute path
        $schemaFile = realpath($schemaFile);
        
        if (!$schemaFile || !file_exists($schemaFile)) {
            $attemptedPath = __DIR__ . '/../../sql/create_tables_v2.sql';
            throw new Exception("Schema file not found. Attempted path: $attemptedPath (resolved: " . ($schemaFile ?: 'null') . ")");
        }

        $pdoNewStore = new PDO(
            "mysql:host={$dbCreds['host']};dbname=$newDbName;charset=utf8mb4",
            $dbCreds['user'],
            $dbCreds['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );


        // --- SCHEMA EXECUTION USING MYSQLI MULTI_QUERY ---
        // mysqli's multi_query handles multiple statements correctly, including complex VIEWs
        try {
            $sqlContent = file_get_contents($schemaFile);
            
            if (empty($sqlContent)) {
                throw new Exception("Schema file content is empty or could not be read.");
            }

            // Log schema execution start
            error_log("Starting schema execution for database: $newDbName");
            error_log("Schema file size: " . strlen($sqlContent) . " bytes");

            // Use mysqli for multi_query support
            $mysqli = new mysqli($dbCreds['host'], $dbCreds['user'], $dbCreds['password'], $newDbName);
            
            if ($mysqli->connect_error) {
                throw new Exception("mysqli connection failed: " . $mysqli->connect_error);
            }
            
            // Set charset
            $mysqli->set_charset('utf8mb4');
            
            // Execute the entire SQL file at once using multi_query
            if ($mysqli->multi_query($sqlContent)) {
                $stmtCount = 0;
                do {
                    // Store first result set (if any)
                    if ($result = $mysqli->store_result()) {
                        $result->free();
                    }
                    $stmtCount++;
                } while ($mysqli->next_result());
                
                // Check for any errors after execution
                if ($mysqli->error) {
                    throw new Exception("SQL execution error: " . $mysqli->error);
                }
                
                error_log("Successfully executed SQL file ($stmtCount result sets processed)");
            } else {
                throw new Exception("multi_query failed: " . $mysqli->error);
            }
            
            $mysqli->close();
            
            // Reconnect with PDO for remaining operations
            $pdoNewStore = new PDO(
                "mysql:host={$dbCreds['host']};dbname=$newDbName;charset=utf8mb4",
                $dbCreds['user'],
                $dbCreds['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

        } catch (Exception $schemaError) {
            // Log detailed error
            error_log("SCHEMA EXECUTION ERROR: " . $schemaError->getMessage());
            error_log("Schema file: " . $schemaFile);
            error_log("Database: " . $newDbName);
            throw new Exception("Schema execution failed: " . $schemaError->getMessage());
        }
        // --- END SCHEMA EXECUTION ---

        // Step 8: Verify schema was imported correctly (check for users table)
        // Wait a moment for MySQL to process the changes
        usleep(100000); // 0.1 second
        
        try {
            // Check if users table exists
            $checkTable = $pdoNewStore->query("SHOW TABLES LIKE 'users'");
            if ($checkTable->rowCount() == 0) {
                // List all tables for debugging
                $allTables = $pdoNewStore->query("SHOW TABLES");
                $tableList = [];
                while ($row = $allTables->fetch(PDO::FETCH_NUM)) {
                    $tableList[] = $row[0];
                }
                throw new Exception("Users table not found after schema import. Tables found: " . implode(", ", $tableList ?: ["none"]));
            }
            
            // Verify we can query the table
            $pdoNewStore->query("SELECT 1 FROM users LIMIT 1");
        } catch (PDOException $e) {
            // List all tables for debugging
            try {
                $allTables = $pdoNewStore->query("SHOW TABLES");
                $tableList = [];
                while ($row = $allTables->fetch(PDO::FETCH_NUM)) {
                    $tableList[] = $row[0];
                }
                $tableInfo = "Tables in database: " . implode(", ", $tableList ?: ["none"]);
            } catch (Exception $listError) {
                $tableInfo = "Could not list tables: " . $listError->getMessage();
            }
            
            throw new Exception("Schema import verification failed: users table not accessible. " . $e->getMessage() . ". " . $tableInfo);
        }

        // Step 9: Create first user in new store database
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdoNewStore->prepare('
            INSERT INTO users (full_name, email, password, phone, role, created_at, updated_at)
            VALUES (:full_name, :email, :password, :phone, :role, NOW(), NOW())
        ');
        $stmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':password' => $hashedPassword,
            ':phone' => ($phone === '') ? null : $phone,
            ':role' => 'admin' // First user is admin
        ]);

        // Step 10: Mark activation code as used in license_keys table
        $stmt = $pdoMaster->prepare('
            UPDATE license_keys 
            SET status = :status, used_by_store_id = :store_id, used_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            ':status' => 'used',
            ':store_id' => $storeId,
            ':id' => $licenseKey['id']
        ]);

        // Commit remaining changes (license key update)
        $pdoMaster->commit();

        // Clean any output before sending JSON
        ob_clean();
        
        echo json_encode([
            'success' => true,
            'message' => 'Store and user account created successfully. You can now log in.',
        ]);
        exit;

    } catch (Exception $e) {
        // Rollback any active transaction
        if ($pdoMaster->inTransaction()) {
            $pdoMaster->rollBack();
        }
        
        // Cleanup: Try to drop the database if it was created
        if (isset($newDbName)) {
            try {
                $pdoMaster->exec("DROP DATABASE IF EXISTS `$newDbName`");
            } catch (Exception $dropError) {
                error_log('Failed to drop database: ' . $dropError->getMessage());
            }
        }
        
        // Cleanup: Delete store record if it was created but database creation failed
        if (isset($storeId)) {
            try {
                $pdoMaster->beginTransaction();
                $pdoMaster->prepare('DELETE FROM stores WHERE id = ?')->execute([$storeId]);
                $pdoMaster->commit();
            } catch (Exception $deleteError) {
                error_log('Failed to delete store record: ' . $deleteError->getMessage());
            }
        }
        
        // Log detailed error
        error_log('Store creation failed: ' . $e->getMessage());
        error_log('Store name: ' . $storeName);
        error_log('Database name: ' . ($newDbName ?? 'N/A'));
        error_log('Store ID: ' . ($storeId ?? 'N/A'));
        
        http_response_code(500);
        
        ob_clean();
        
        // In development, show detailed error. In production, show generic message
        $isDevelopment = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                         strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false);
        
        echo json_encode([
            'success' => false,
            'message' => $isDevelopment 
                ? 'Unable to create store: ' . $e->getMessage() 
                : 'Unable to create store. Please try again later or contact support.',
            'error_details' => $isDevelopment ? $e->getMessage() : null,
            'error_file' => $isDevelopment ? $e->getFile() : null,
            'error_line' => $isDevelopment ? $e->getLine() : null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

} catch (PDOException $e) {
    error_log('Signup failed: ' . $e->getMessage());
    http_response_code(500);
    
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Unable to process your request now. Please try again later.',
    ]);
    exit;
}
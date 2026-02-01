<?php
/**
 * Store Settings API
 * Handles store name and logo management
 */

require_once __DIR__ . '/../../includes/session_check.php';

header('Content-Type: application/json');

// Helper functions
function sendSuccess($data = []) {
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

function requireMethod($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        sendError("Method $method required", 405);
    }
}

function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

function sanitize($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

try {
    // Get database connections
    $pdoMaster = require __DIR__ . '/../../app/config/master_database.php';
    
    // Ensure user is logged in and has a store
    if (!isset($_SESSION['store_id']) || !isset($_SESSION['user_id'])) {
        sendError('Unauthorized', 401);
    }
    
    $storeId = $_SESSION['store_id'];
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        
        /**
         * GET store_settings.php?action=get
         * Get current store settings
         */
        case 'get':
            requireMethod('GET');
            
            $stmt = $pdoMaster->prepare("
                SELECT id, store_name, logo_path, logo_updated_at, created_at, updated_at
                FROM stores 
                WHERE id = :id
            ");
            $stmt->execute([':id' => $storeId]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                sendError('Store not found', 404);
            }
            
            $data = [
                'id' => (int)$store['id'],
                'store_name' => $store['store_name'],
                'logo_path' => $store['logo_path'],
                'logo_updated_at' => $store['logo_updated_at'],
                'created_at' => $store['created_at'],
                'updated_at' => $store['updated_at']
            ];
            
            sendSuccess($data);
            break;
        
        /**
         * POST store_settings.php?action=update_name
         * Update store name
         */
        case 'update_name':
            requireMethod('POST');
            
            $data = getJsonInput();
            $storeName = sanitize(trim($data['store_name'] ?? ''));
            
            // Validation
            if (empty($storeName)) {
                sendError('Store name is required', 400);
            }
            
            if (strlen($storeName) < 2) {
                sendError('Store name must be at least 2 characters', 400);
            }
            
            if (strlen($storeName) > 255) {
                sendError('Store name is too long (max 255 characters)', 400);
            }
            
            // Check if store name is already taken by another store
            $checkStmt = $pdoMaster->prepare("SELECT id FROM stores WHERE store_name = :store_name AND id != :id");
            $checkStmt->execute([':store_name' => $storeName, ':id' => $storeId]);
            if ($checkStmt->fetch()) {
                sendError('Store name already exists. Please choose a different name.', 400);
            }
            
            // Update store name
            $stmt = $pdoMaster->prepare("
                UPDATE stores 
                SET store_name = :store_name, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':store_name' => $storeName,
                ':id' => $storeId
            ]);
            
            // Update session
            $_SESSION['store_name'] = $storeName;
            
            sendSuccess(['message' => 'Store name updated successfully', 'store_name' => $storeName]);
            break;
        
        /**
         * POST store_settings.php?action=upload_logo
         * Upload store logo
         */
        case 'upload_logo':
            requireMethod('POST');
            
            // Check if file was uploaded
            if (!isset($_FILES['logo'])) {
                error_log('Logo upload: $_FILES[\'logo\'] not set');
                sendError('No file uploaded', 400);
            }
            
            if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                $errorCode = $_FILES['logo']['error'];
                error_log("Logo upload: UPLOAD_ERR code $errorCode");
                
                switch ($errorCode) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        sendError('File is too large', 400);
                    case UPLOAD_ERR_PARTIAL:
                        sendError('File was only partially uploaded', 400);
                    case UPLOAD_ERR_NO_FILE:
                        sendError('No file was uploaded', 400);
                    case UPLOAD_ERR_NO_TMP_DIR:
                        sendError('Missing temporary folder', 500);
                    case UPLOAD_ERR_CANT_WRITE:
                        sendError('Failed to write file to disk', 500);
                    default:
                        sendError('Upload error', 400);
                }
            }
            
            $file = $_FILES['logo'];
            error_log("Logo upload: Received file '{$file['name']}', size: {$file['size']}, tmp: {$file['tmp_name']}");
            
            // Validate file type
            $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            error_log("Logo upload: Detected MIME type: $mimeType");
            
            if (!in_array($mimeType, $allowedTypes)) {
                sendError('Invalid file type. Only PNG and JPG images are allowed', 400);
            }
            
            // Validate file size (max 2MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                sendError('File is too large. Maximum size is 2MB', 400);
            }
            
            // FIX: Use realpath for robust path resolution (handles Windows paths with spaces)
            $baseDir = realpath(__DIR__ . '/../..');
            if (!$baseDir) {
                error_log("Logo upload: Failed to resolve base directory from: " . __DIR__);
                sendError('Server configuration error', 500);
            }
            
            $uploadsDir = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'store_logos';
            error_log("Logo upload: Target directory: $uploadsDir");
            
            // Create uploads directory if it doesn't exist
            if (!is_dir($uploadsDir)) {
                error_log("Logo upload: Creating directory: $uploadsDir");
                if (!mkdir($uploadsDir, 0755, true)) {
                    error_log("Logo upload: Failed to create directory");
                    sendError('Failed to create upload directory', 500);
                }
            }
            
            // Verify directory is writable
            if (!is_writable($uploadsDir)) {
                error_log("Logo upload: Directory not writable: $uploadsDir");
                sendError('Upload directory is not writable', 500);
            }
            
            // Generate unique filename
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'store_' . $storeId . '_' . time() . '.' . $extension;
            $filepath = $uploadsDir . DIRECTORY_SEPARATOR . $filename;
            
            // Use forward slashes for web path (cross-platform)
            $relativeFilepath = 'uploads/store_logos/' . $filename;
            
            error_log("Logo upload: Saving to: $filepath");
            error_log("Logo upload: Relative path: $relativeFilepath");
            
            // Get current logo to delete it later
            $stmt = $pdoMaster->prepare("SELECT logo_path FROM stores WHERE id = :id");
            $stmt->execute([':id' => $storeId]);
            $currentLogo = $stmt->fetchColumn();
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                error_log("Logo upload: move_uploaded_file() failed");
                error_log("Logo upload: From: {$file['tmp_name']}");
                error_log("Logo upload: To: $filepath");
                error_log("Logo upload: File exists in tmp: " . (file_exists($file['tmp_name']) ? 'yes' : 'no'));
                sendError('Failed to save uploaded file', 500);
            }
            
            // Verify file was saved
            if (!file_exists($filepath)) {
                error_log("Logo upload: File not found after move_uploaded_file");
                sendError('File was not saved successfully', 500);
            }
            
            error_log("Logo upload: File saved successfully, size on disk: " . filesize($filepath));
            
            // Update database
            try {
                $stmt = $pdoMaster->prepare("
                    UPDATE stores 
                    SET logo_path = :logo_path, logo_updated_at = NOW(), updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':logo_path' => $relativeFilepath,
                    ':id' => $storeId
                ]);
                
                error_log("Logo upload: Database updated successfully");
            } catch (PDOException $e) {
                error_log("Logo upload: Database update failed: " . $e->getMessage());
                // Cleanup uploaded file if database update fails
                @unlink($filepath);
                throw $e;
            }
            
            // Delete old logo file if it exists
            if ($currentLogo) {
                $oldLogoPath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $currentLogo);
                if (file_exists($oldLogoPath)) {
                    error_log("Logo upload: Deleting old logo: $oldLogoPath");
                    @unlink($oldLogoPath);
                }
            }
            
            sendSuccess([
                'message' => 'Logo uploaded successfully',
                'logo_path' => $relativeFilepath
            ]);
            break;
        
        /**
         * POST store_settings.php?action=remove_logo
         * Remove store logo
         */
        case 'remove_logo':
            requireMethod('POST');
            
            // Get current logo
            $stmt = $pdoMaster->prepare("SELECT logo_path FROM stores WHERE id = :id");
            $stmt->execute([':id' => $storeId]);
            $currentLogo = $stmt->fetchColumn();
            
            if (!$currentLogo) {
                sendError('No logo to remove', 400);
            }
            
            // Update database
            $stmt = $pdoMaster->prepare("
                UPDATE stores 
                SET logo_path = NULL, logo_updated_at = NULL, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $storeId]);
            
            // Delete logo file
            $logoFile = __DIR__ . '/../../' . $currentLogo;
            if (file_exists($logoFile)) {
                @unlink($logoFile);
            }
            
            sendSuccess(['message' => 'Logo removed successfully']);
            break;
        
        default:
            sendError('Invalid action', 400);
    }
    
} catch (PDOException $e) {
    error_log('Store Settings API Database Error: ' . $e->getMessage());
    sendError('Database error occurred', 500);
} catch (Exception $e) {
    error_log('Store Settings API Error: ' . $e->getMessage());
    sendError($e->getMessage(), 500);
}

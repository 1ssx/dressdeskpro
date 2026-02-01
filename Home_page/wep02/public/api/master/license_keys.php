<?php
/**
 * Master API - License Keys Management
 * Handles generation and management of activation codes
 */

header('Content-Type: application/json; charset=utf-8');

// Check master session
session_start();
if (!isset($_SESSION['master_user_id']) || $_SESSION['master_role'] !== 'owner') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$pdoMaster = require __DIR__ . '/../../../app/config/master_database.php';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

function generateActivationCode($length = 16) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $code;
}

try {
    switch ($action) {
        case 'list':
            $stmt = $pdoMaster->query('
                SELECT lk.*, s.store_name
                FROM license_keys lk
                LEFT JOIN stores s ON lk.used_by_store_id = s.id
                ORDER BY lk.created_at DESC
            ');
            $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'status' => 'success',
                'data' => $keys
            ]);
            break;

        case 'generate':
            $expiresAt = $_POST['expires_at'] ?? null;
            $maxUses = intval($_POST['max_uses'] ?? 1);
            
            // Generate unique code
            $code = generateActivationCode();
            $attempts = 0;
            while ($attempts < 10) {
                $stmt = $pdoMaster->prepare('SELECT id FROM license_keys WHERE code = ?');
                $stmt->execute([$code]);
                if (!$stmt->fetch()) {
                    break; // Code is unique
                }
                $code = generateActivationCode();
                $attempts++;
            }
            
            if ($attempts >= 10) {
                throw new Exception('Failed to generate unique code');
            }
            
            $stmt = $pdoMaster->prepare('
                INSERT INTO license_keys (code, status, max_uses, expires_at, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $code,
                'unused',
                $maxUses,
                $expiresAt ?: null
            ]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Activation code generated successfully',
                'data' => ['code' => $code]
            ]);
            break;

        case 'expire':
            $keyId = $_POST['key_id'] ?? null;
            
            if (!$keyId) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid key ID']);
                exit;
            }
            
            $stmt = $pdoMaster->prepare('UPDATE license_keys SET status = ?, updated_at = NOW() WHERE id = ? AND status = ?');
            $stmt->execute(['expired', $keyId, 'unused']);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'License key expired successfully'
                ]);
            } else {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Key not found or already used']);
            }
            break;

        case 'delete':
            $keyId = $_POST['key_id'] ?? null;
            
            if (!$keyId) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid key ID']);
                exit;
            }
            
            // Only delete unused keys
            $stmt = $pdoMaster->prepare('DELETE FROM license_keys WHERE id = ? AND status = ?');
            $stmt->execute([$keyId, 'unused']);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'License key deleted successfully'
                ]);
            } else {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Key not found or cannot be deleted']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log('License keys API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('License keys error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}


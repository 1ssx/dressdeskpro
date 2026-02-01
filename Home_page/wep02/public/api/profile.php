<?php
/**
 * User Profile API
 * Handles user profile data retrieval and updates
 */

// Load common infrastructure
require_once __DIR__ . '/_common/bootstrap.php';

$action = getQueryParam('action', 'get');

try {
    switch ($action) {
        
        /**
         * GET profile.php?action=get
         * Get current user's profile data
         */
        case 'get':
            $userId = getCurrentUserId();
            
            if (!$userId) {
                sendError('User not authenticated', 401);
            }
            
            $stmt = $pdo->prepare("
                SELECT id, full_name, email, phone, created_at, updated_at
                FROM users 
                WHERE id = :id
            ");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                sendError('User not found', 404);
            }
            
            // Get role from session or default to 'user'
            $role = $_SESSION['user_role'] ?? 'user';
            
            $data = [
                'id' => (int)$user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'phone' => $user['phone'] ?? '',
                'role' => $role,
                'created_at' => $user['created_at'],
                'updated_at' => $user['updated_at']
            ];
            
            sendSuccess($data);
            break;
        
        /**
         * POST profile.php?action=update
         * Update user profile (name, email, phone)
         */
        case 'update':
            requireMethod('POST');
            
            $userId = getCurrentUserId();
            if (!$userId) {
                sendError('User not authenticated', 401);
            }
            
            $data = getJsonInput();
            
            $fullName = sanitize(trim($data['full_name'] ?? ''));
            $email = sanitize(trim($data['email'] ?? ''));
            $phone = !empty($data['phone']) ? sanitize(trim($data['phone'])) : null;
            
            // Validation
            if (empty($fullName)) {
                sendError('Full name is required', 400);
            }
            
            if (empty($email)) {
                sendError('Email is required', 400);
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendError('Invalid email format', 400);
            }
            
            // Check if email is already taken by another user
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
            $checkStmt->execute([':email' => $email, ':id' => $userId]);
            if ($checkStmt->fetch()) {
                sendError('Email is already registered by another user', 400);
            }
            
            // Update user
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = :full_name, email = :email, phone = :phone, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':full_name' => $fullName,
                ':email' => $email,
                ':phone' => $phone,
                ':id' => $userId
            ]);
            
            // Update session
            $_SESSION['user_name'] = $fullName;
            $_SESSION['full_name'] = $fullName;
            $_SESSION['user_email'] = $email;
            $_SESSION['email'] = $email;
            
            sendSuccess(['message' => 'Profile updated successfully']);
            break;
        
        /**
         * POST profile.php?action=change_password
         * Change user password
         */
        case 'change_password':
            requireMethod('POST');
            
            $userId = getCurrentUserId();
            if (!$userId) {
                sendError('User not authenticated', 401);
            }
            
            $data = getJsonInput();
            
            $currentPassword = $data['current_password'] ?? '';
            $newPassword = $data['new_password'] ?? '';
            $confirmPassword = $data['confirm_password'] ?? '';
            
            // Validation
            if (empty($currentPassword)) {
                sendError('Current password is required', 400);
            }
            
            if (empty($newPassword)) {
                sendError('New password is required', 400);
            }
            
            if (strlen($newPassword) < 8) {
                sendError('New password must be at least 8 characters', 400);
            }
            
            if ($newPassword !== $confirmPassword) {
                sendError('New password and confirmation do not match', 400);
            }
            
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($currentPassword, $user['password'])) {
                sendError('Current password is incorrect', 400);
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET password = :password, updated_at = NOW()
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':password' => $hashedPassword,
                ':id' => $userId
            ]);
            
            sendSuccess(['message' => 'Password changed successfully']);
            break;
        
        default:
            sendError('Unknown action', 400);
            break;
    }
} catch (Exception $e) {
    logError('Profile API Error', ['message' => $e->getMessage(), 'action' => $action]);
    sendError('Failed to process request: ' . $e->getMessage(), 500);
}


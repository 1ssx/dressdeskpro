<?php
// app/controllers/signup.php
// Signup controller (moved from public/api and public root)

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../helpers/functions.php';
$pdo = require __DIR__ . '/../config/database.php';

$errors = [];

$fullName        = sanitizeInput($_POST['full_name'] ?? '');
$email           = sanitizeInput($_POST['email'] ?? '');
$password        = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$phone           = sanitizeInput($_POST['phone'] ?? '');
$termsAccepted   = $_POST['terms'] ?? '';

if ($fullName === '') $errors['full_name'] = 'Full name is required.';
if ($email === '') $errors['email'] = 'Email is required.';
elseif (!isValidEmail($email)) $errors['email'] = 'Email format is invalid.';
if ($password === '') $errors['password'] = 'Password is required.';
elseif (!isValidPassword($password)) $errors['password'] = 'Password must be at least 8 characters and include letters and numbers.';
if ($confirmPassword === '') $errors['confirm_password'] = 'Please confirm your password.';
elseif ($password !== $confirmPassword) $errors['confirm_password'] = 'Passwords do not match.';
if (!isValidPhone($phone)) $errors['phone'] = 'Phone number is invalid.';
if ($termsAccepted !== '1') $errors['terms'] = 'You must accept the terms and conditions.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'errors' => ['email' => 'This email is already registered.']]);
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $insert = $pdo->prepare(
        'INSERT INTO users (full_name, email, password, phone, created_at, updated_at)
         VALUES (:full_name, :email, :password, :phone, NOW(), NOW())'
    );

    $insert->execute([
        ':full_name' => $fullName,
        ':email'     => $email,
        ':password'  => $hashedPassword,
        ':phone'     => ($phone === '') ? null : $phone,
    ]);

    echo json_encode(['success' => true, 'message' => 'User registered successfully.']);
} catch (PDOException $e) {
    error_log('Signup failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to process your request now. Please try again later.']);
}

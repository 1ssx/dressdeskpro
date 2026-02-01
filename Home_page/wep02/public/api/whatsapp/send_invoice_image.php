<?php
/**
 * WhatsApp Invoice Image Sender API
 * Endpoint: api/whatsapp/send_invoice_image.php
 * 
 * Receives Base64 JPG image from frontend, saves it, and sends via WhatsApp API
 */

// CRITICAL: Prevent any HTML output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start output buffering immediately
ob_start();

// Set JSON header before anything else
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_clean();
    http_response_code(204);
    exit;
}

// Helper function to send JSON response safely
function sendJsonResponse($status, $message, $data = []) {
    // Clean all output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    $response = ['status' => $status, 'message' => $message];
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendJsonResponse('error', 'Method not allowed');
}

// Start session for auth (silently)
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Get JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input) {
    http_response_code(400);
    sendJsonResponse('error', 'Invalid JSON input');
}

// Validate required fields
$invoiceId = $input['invoice_id'] ?? null;
$phone = $input['phone'] ?? null;
$base64Image = $input['image_base64'] ?? null;
$customerName = $input['customer_name'] ?? 'عزيزي العميل';
$invoiceNumber = $input['invoice_number'] ?? '';

if (!$invoiceId || !$phone || !$base64Image) {
    http_response_code(400);
    sendJsonResponse('error', 'Missing required fields (invoice_id, phone, image_base64)');
}

// Load WhatsApp helper
// Path: public/api/whatsapp -> go up 3 levels to wep02, then into app/helpers
$whatsappHelperPath = __DIR__ . '/../../../app/helpers/whatsapp.php';
if (!file_exists($whatsappHelperPath)) {
    error_log("WhatsApp helper not found at: " . $whatsappHelperPath);
    http_response_code(500);
    sendJsonResponse('error', 'WhatsApp helper file not found');
}

require_once $whatsappHelperPath;

try {
    // 1. Decode and save the image
    // Path: api/whatsapp -> go up 2 levels to public, then into temp_invoices (existing folder)
    $imageDir = __DIR__ . '/../../temp_invoices/';
    
    // Create directory if it doesn't exist
    if (!is_dir($imageDir)) {
        if (!@mkdir($imageDir, 0755, true)) {
            throw new Exception('Failed to create upload directory: ' . $imageDir);
        }
    }
    
    // Check if directory is writable
    if (!is_writable($imageDir)) {
        throw new Exception('Upload directory is not writable: ' . $imageDir);
    }
    
    error_log("WhatsApp Image API: Image dir = " . realpath($imageDir));
    
    // Remove data URL prefix if present
    $cleanBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $base64Image);
    
    // Decode Base64
    $imageData = base64_decode($cleanBase64, true);
    
    if ($imageData === false) {
        throw new Exception('Failed to decode Base64 image');
    }
    
    // Generate unique filename
    $timestamp = time();
    $fileName = 'invoice_' . $invoiceId . '_' . $timestamp . '.jpg';
    $filePath = $imageDir . $fileName;
    
    // Save the image
    $saved = @file_put_contents($filePath, $imageData);
    
    if ($saved === false) {
        throw new Exception('Failed to save image to server. Path: ' . $filePath);
    }
    
    error_log("WhatsApp Image API: Image saved successfully at " . $filePath . " (" . $saved . " bytes)");
    
    // 2. Generate public URL for the image
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $protocol . '://' . $host;
    
    // Detect the correct path prefix (handles different hosting setups)
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
    $pathPrefix = '';
    if (preg_match('#(.*)/public/api/whatsapp/#', $scriptPath, $matches)) {
        $pathPrefix = $matches[1];
    }
    
    // Construct the public URL (using temp_invoices instead of uploads/temp_invoices)
    $imageUrl = $baseUrl . $pathPrefix . '/public/temp_invoices/' . $fileName;
    
    error_log("WhatsApp Image API: Image URL = " . $imageUrl);
    
    // ✅ FIX: Get dynamic store name from session instead of hardcoded value
    $storeName = $_SESSION['store_name'] ?? 'المتجر';
    
    // 3. Prepare WhatsApp message caption (Arabic) - Dynamic store name
    $caption = "عزيزي العميل {$customerName}،\n\n";
    $caption .= "مرفق لكم صورة الفاتورة رقم {$invoiceNumber}.\n";
    $caption .= "شكراً لثقتكم بنا.\n\n";
    $caption .= "💍 {$storeName}";
    
    // 4. Send via WhatsApp with file/image
    if (!function_exists('sendWhatsAppWithFile')) {
        throw new Exception('WhatsApp sendWhatsAppWithFile function not available');
    }
    
    $result = sendWhatsAppWithFile(
        $phone,
        $caption,
        $imageUrl,
        'فاتورة_' . $invoiceNumber . '.jpg'
    );
    
    if ($result['success']) {
        error_log("WhatsApp Invoice Image sent successfully - Invoice #{$invoiceId} to {$phone}");
        sendJsonResponse('success', 'تم إرسال صورة الفاتورة بنجاح عبر واتساب', ['image_url' => $imageUrl]);
    } else {
        throw new Exception($result['error'] ?? 'Failed to send WhatsApp message');
    }
    
} catch (Exception $e) {
    error_log("WhatsApp Invoice Image Error: " . $e->getMessage());
    http_response_code(500);
    sendJsonResponse('error', 'فشل إرسال الرسالة: ' . $e->getMessage());
}

// Cleanup old temp files (files older than 24 hours)
try {
    $files = glob($imageDir . 'invoice_*.jpg');
    $cutoffTime = time() - (24 * 3600);
    
    foreach ($files as $file) {
        if (@filemtime($file) < $cutoffTime) {
            @unlink($file);
        }
    }
} catch (Exception $e) {
    // Silently fail cleanup
}

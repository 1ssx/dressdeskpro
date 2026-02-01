<?php
/**
 * WhatsApp Helper
 * Handles sending WhatsApp messages via WasenderAPI
 */

/**
 * Send a WhatsApp message to a phone number
 * 
 * @param string $phone Phone number (include country code, e.g., 966501234567)
 * @param string $text Message text (UTF-8 supported for Arabic)
 * @return array Response with 'success' boolean and 'message' or 'error'
 */
function sendWhatsApp($phone, $text) {
    // API Configuration
    $apiKey = '55a96a0d922fa12385d37a9ddf171557e7fc7e69e3139eb4abaac2e1a7a104d1';
    $apiUrl = 'https://wasenderapi.com/api/send-message';
    
    // Validate inputs
    if (empty($phone)) {
        return [
            'success' => false,
            'error' => 'Phone number is required'
        ];
    }
    
    if (empty($text)) {
        return [
            'success' => false,
            'error' => 'Message text is required'
        ];
    }
    
    // Clean phone number (remove spaces, dashes, etc.)
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Prepare request payload
    $payload = [
        'to' => $phone,
        'text' => $text
    ];
    
    // Initialize cURL
    $ch = curl_init($apiUrl);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    curl_close($ch);
    
    // Handle cURL errors
    if ($curlError) {
        return [
            'success' => false,
            'error' => 'cURL Error: ' . $curlError
        ];
    }
    
    // Parse response
    $responseData = json_decode($response, true);
    
    // Check HTTP status code
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'message' => 'WhatsApp message sent successfully',
            'response' => $responseData
        ];
    } else {
        return [
            'success' => false,
            'error' => 'API Error (HTTP ' . $httpCode . '): ' . ($responseData['message'] ?? $response),
            'response' => $responseData
        ];
    }
}

/**
 * Format invoice message for WhatsApp
 * 
 * @param string $customerName Customer name
 * @param string $invoiceNumber Invoice number
 * @param string $type Message type: 'created' or 'reminder'
 * @param string $storeName Dynamic store name (defaults to session value)
 * @return string Formatted message
 */
function formatInvoiceMessage($customerName, $invoiceNumber, $type = 'created', $storeName = null) {
    // ✅ FIX: Use dynamic store name instead of hardcoded value
    // Priority: 1. Parameter, 2. Session, 3. Default fallback
    if (empty($storeName)) {
        $storeName = $_SESSION['store_name'] ?? 'المتجر';
    }
    
    if ($type === 'created') {
        return "أهلاً {$customerName} 💍✨\n\n" .
               "تم إصدار فاتورتكم رقم {$invoiceNumber} بنجاح، وحجزكم أصبح مؤكداً.\n" .
               "نحن في {$storeName} نعتز بثقتكم، يسعدنا تواصلكم في أي وقت، ونتمنى لكم تجربة فريدة معنا.";
    } else {
        // Reminder message
        return "أهلاً {$customerName} 💍✨\n\n" .
               "هذه رسالة بخصوص فاتورتكم رقم {$invoiceNumber}.\n" .
               "نحن في {$storeName} نعتز بثقتكم، يسعدنا تواصلكم في أي وقت.";
    }
}

/**
 * Send WhatsApp message with file attachment (PDF, image, etc.)
 * 
 * @param string $phone Phone number
 * @param string $text Caption/message text
 * @param string $fileUrl URL to the file (must be publicly accessible)
 * @param string $fileName Optional filename
 * @return array Response with 'success' boolean and 'message' or 'error'
 */
function sendWhatsAppWithFile($phone, $text, $fileUrl, $fileName = 'invoice.pdf') {
    // API Configuration
    $apiKey = '55a96a0d922fa12385d37a9ddf171557e7fc7e69e3139eb4abaac2e1a7a104d1';
    // Changed to unified endpoint as 'send-image' and 'send-media' returned 404
    $apiUrl = 'https://wasenderapi.com/api/send-message';
    
    // Validate inputs
    if (empty($phone)) {
        return [
            'success' => false,
            'error' => 'Phone number is required'
        ];
    }
    
    if (empty($fileUrl)) {
        return [
            'success' => false,
            'error' => 'File URL is required'
        ];
    }
    
    // VALIDATION: Prevent sending localhost/private URLs which the external API cannot access
    // This ensures compatibility when running via Ngrok (public URL) vs Localhost
    if (strpos($fileUrl, 'localhost') !== false || strpos($fileUrl, '127.0.0.1') !== false) {
        return [
            'success' => false,
            'error' => 'Invalid Media URL: Cannot send localhost URL to external API. Please access the system via the public Ngrok URL.'
        ];
    }
    
    // Clean phone number
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // FIX: URL Encoding for paths with spaces (e.g. "Home page")
    // This allows the external API to correctly fetch the file
    $fileUrl = str_replace(' ', '%20', $fileUrl);

    // Prepare request payload
    // Updated structure based on "send-message" unified endpoint requirements
    // We include both key variations (number/to, message/text) to maximize compatibility
    $payload = [
        'number' => $phone,       // Preferred key for some providers
        'to' => $phone,           // Common legacy key
        'message' => $text,       // Preferred key for caption
        'text' => $text,          // Common legacy key
        'type' => 'media',        // Explicitly specify message type
        'media_url' => $fileUrl,  // Primary media URL
        'url' => $fileUrl,        // Alternative media key found in some docs
        'filename' => $fileName
    ];
    
    // Initialize cURL
    $ch = curl_init($apiUrl);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increased timeout for media handling
    
    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    curl_close($ch);
    
    // Handle cURL errors
    if ($curlError) {
        return [
            'success' => false,
            'error' => 'cURL Error: ' . $curlError
        ];
    }
    
    // Parse response
    $responseData = json_decode($response, true);
    
    // Check HTTP status code
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'message' => 'WhatsApp message with media sent successfully',
            'response' => $responseData
        ];
    } else {
        return [
            'success' => false,
            'error' => 'API Error (HTTP ' . $httpCode . '): ' . ($responseData['message'] ?? $response),
            'response' => $responseData
        ];
    }
}

/**
 * Generate invoice HTML and convert to PDF URL for WhatsApp
 * 
 * @param int $invoiceId Invoice ID
 * @param PDO $pdo Database connection
 * @return string|false PDF URL or false on failure
 */
function generateInvoicePDFUrl($invoiceId, $pdo) {
    try {
        // Load the image converter
        $imageConverterPath = __DIR__ . '/invoice_to_image.php';
        if (file_exists($imageConverterPath)) {
            require_once $imageConverterPath;
        }
        
        // Try to generate invoice as image first (using Pictify API)
        if (function_exists('generateInvoiceImage')) {
            $imageUrl = generateInvoiceImage($invoiceId, $pdo);
            if ($imageUrl) {
                error_log("WhatsApp: Using invoice image - " . $imageUrl);
                return $imageUrl;
            } else {
                error_log("WhatsApp: Image generation failed, falling back to HTML");
            }
        }
        
        // Fallback: Load the print invoice generator for HTML
        $generatorPath = __DIR__ . '/print_invoice_generator.php';
        if (file_exists($generatorPath)) {
            require_once $generatorPath;
        }
        
        // Create directory if it doesn't exist
        $pdfDir = __DIR__ . '/../../public/temp_pdfs/';
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }
        
        // Generate print-quality invoice HTML
        $html = null;
        if (function_exists('generatePrintInvoiceHTML')) {
            $html = generatePrintInvoiceHTML($invoiceId, $pdo);
        }
        
        // Fallback to simple HTML if print generator fails
        if (!$html) {
            // Get invoice data
            $stmt = $pdo->prepare("
                SELECT 
                    i.*,
                    c.name as customer_name,
                    c.phone_1,
                    c.phone_2
                FROM invoices i
                LEFT JOIN customers c ON i.customer_id = c.id
                WHERE i.id = ?
            ");
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$invoice) {
                return false;
            }
            
            // Get invoice items
            $stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
            $stmt->execute([$invoiceId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Generate simple HTML content
            $html = generateInvoiceHTML($invoice, $items);
        }
        
        // Save HTML file
        $htmlPath = $pdfDir . 'invoice_' . $invoiceId . '_' . time() . '.html';
        file_put_contents($htmlPath, $html);
        
        // Return the URL to the HTML file
        // The URL needs to be publicly accessible for WhatsApp API
        $baseUrl = getBaseUrl();
        error_log("WhatsApp: Using HTML fallback - " . $htmlPath);
        return $baseUrl . '/public/temp_pdfs/' . basename($htmlPath);
        
    } catch (Exception $e) {
        error_log("PDF Generation Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get base URL of the application
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}

/**
 * Generate simple invoice HTML
 */
function generateInvoiceHTML($invoice, $items = []) {
    $html = '<!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <title>فاتورة رقم ' . htmlspecialchars($invoice['invoice_number']) . '</title>
        <style>
            * { box-sizing: border-box; font-family: Arial, sans-serif; }
            body { margin: 20px; background: #fff; color: #333; }
            .container { max-width: 800px; margin: 0 auto; padding: 20px; border: 2px solid #c5a059; }
            h1 { text-align: center; color: #1a1a1a; border-bottom: 3px solid #c5a059; padding-bottom: 10px; }
            .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 5px; }
            .section h3 { margin-top: 0; color: #c5a059; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            th, td { padding: 8px; text-align: right; border-bottom: 1px solid #ddd; }
            th { background: #c5a059; color: #fff; }
            .total { font-size: 18px; font-weight: bold; color: #2c3e50; text-align: left; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>' . htmlspecialchars($_SESSION['store_name'] ?? 'المتجر') . '</h1>
            <p style="text-align:center">Luxury Wedding Dresses</p>
            
            <div class="section">
                <h3>معلومات الفاتورة</h3>
                <p><strong>رقم الفاتورة:</strong> ' . htmlspecialchars($invoice['invoice_number']) . '</p>
                <p><strong>التاريخ:</strong> ' . htmlspecialchars($invoice['invoice_date']) . '</p>
            </div>
            
            <div class="section">
                <h3>بيانات العميل</h3>
                <p><strong>الاسم:</strong> ' . htmlspecialchars($invoice['customer_name']) . '</p>
                <p><strong>الجوال:</strong> ' . htmlspecialchars($invoice['phone_1']) . '</p>
            </div>
            
            <div class="section">
                <h3>الملخص المالي</h3>
                <table>
                    <tr>
                        <th>البيان</th>
                        <th>المبلغ</th>
                    </tr>
                    <tr>
                        <td>الإجمالي</td>
                        <td>' . number_format($invoice['total_price'], 2) . ' ريال</td>
                    </tr>
                    <tr>
                        <td>المدفوع</td>
                        <td>' . number_format($invoice['deposit_amount'], 2) . ' ريال</td>
                    </tr>
                    <tr class="total">
                        <td><strong>المتبقي</strong></td>
                        <td><strong>' . number_format($invoice['remaining_balance'], 2) . ' ريال</strong></td>
                    </tr>
                </table>
            </div>
            
            <p style="text-align:center; margin-top:30px; font-size:12px; color:#888;">
                شكراً لتعاملكم معنا
            </p>
        </div>
    </body>
    </html>';
    
    return $html;
}

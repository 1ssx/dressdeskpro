<?php
/**
 * Convert Invoice HTML to Image for WhatsApp
 * Creates beautiful invoice images from HTML
 */

/**
 * Generate invoice image from HTML
 * 
 * @param int $invoiceId Invoice ID
 * @param PDO $pdo Database connection
 * @return string|false Image URL or false on failure
 */
function generateInvoiceImage($invoiceId, $pdo) {
    try {
        // First generate the print-quality HTML
        $generatorPath = __DIR__ . '/print_invoice_generator.php';
        if (file_exists($generatorPath)) {
            require_once $generatorPath;
        }
        
        $html = null;
        if (function_exists('generatePrintInvoiceHTML')) {
            $html = generatePrintInvoiceHTML($invoiceId, $pdo);
        }
        
        if (!$html) {
            return false;
        }
        
        // Create images directory
        $imagesDir = __DIR__ . '/../../public/temp_invoices/';
        if (!is_dir($imagesDir)) {
            mkdir($imagesDir, 0755, true);
        }
        
        // Save HTML temporarily
        $htmlPath = $imagesDir . 'temp_' . $invoiceId . '_' . time() . '.html';
        file_put_contents($htmlPath, $html);
        
        // Try multiple methods to convert HTML to image
        $imagePath = null;
        
        // Method 1: Using wkhtmltoimage if available
        $imagePath = convertUsingWkhtmltoimage($htmlPath, $imagesDir, $invoiceId);
        
        // Method 2: Using API service (fallback)
        if (!$imagePath) {
            $imagePath = convertUsingAPI($html, $imagesDir, $invoiceId);
        }
        
        // Method 3: Keep as HTML (final fallback)
        if (!$imagePath) {
            // Just use the HTML file
            $imagePath = $htmlPath;
        }
        
        // Return public URL
        $baseUrl = getBaseUrl();
        return $baseUrl . '/public/temp_invoices/' . basename($imagePath);
        
    } catch (Exception $e) {
        error_log("Generate Invoice Image Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Convert HTML to image using wkhtmltoimage
 */
function convertUsingWkhtmltoimage($htmlPath, $outputDir, $invoiceId) {
    $imagePath = $outputDir . 'invoice_' . $invoiceId . '_' . time() . '.jpg';
    
    // Check if wkhtmltoimage is installed
    $wkhtmlPath = 'wkhtmltoimage'; // or full path like 'C:\Program Files\wkhtmltopdf\bin\wkhtmltoimage.exe'
    
    // Try to convert
    $command = sprintf(
        '%s --quality 95 --width 800 %s %s 2>&1',
        escapeshellarg($wkhtmlPath),
        escapeshellarg($htmlPath),
        escapeshellarg($imagePath)
    );
    
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($imagePath)) {
        // Delete temp HTML
        @unlink($htmlPath);
        return $imagePath;
    }
    
    return false;
}

/**
 * Convert HTML to image using Pictify.io API
 */
function convertUsingAPI($html, $outputDir, $invoiceId) {
    try {
        // Pictify.io API Configuration
        $apiKey = '1c98510d64dc211f4846e6ce702df4c2e3ef6398781567647fd535164924547d';
        $apiUrl = 'https://api.pictify.io/api/generate';
        
        // Prepare the request data
        $data = [
            'html' => $html,
            'width' => 800,
            'height' => 1200,
            'format' => 'jpeg',
            'quality' => 95,
            'full_page' => true
        ];
        
        // Initialize cURL
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . $apiKey,
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Log for debugging
        error_log("Pictify API Response Code: " . $httpCode);
        
        if ($curlError) {
            error_log("Pictify cURL Error: " . $curlError);
            return false;
        }
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            
            // Pictify returns image URL or base64 data
            if (isset($result['url'])) {
                // Download image from URL
                $imageData = @file_get_contents($result['url']);
                if ($imageData) {
                    $imagePath = $outputDir . 'invoice_' . $invoiceId . '_' . time() . '.jpg';
                    file_put_contents($imagePath, $imageData);
                    error_log("Pictify: Image saved to " . $imagePath);
                    return $imagePath;
                }
            } elseif (isset($result['image'])) {
                // Base64 encoded image
                $imageData = base64_decode($result['image']);
                $imagePath = $outputDir . 'invoice_' . $invoiceId . '_' . time() . '.jpg';
                file_put_contents($imagePath, $imageData);
                error_log("Pictify: Image saved from base64");
                return $imagePath;
            } else {
                error_log("Pictify: Unexpected response format: " . $response);
            }
        } else {
            error_log("Pictify API Error (HTTP $httpCode): " . $response);
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Pictify API Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Simple method: Save HTML as viewable file
 * This works but sends HTML instead of image
 */
function saveAsViewableHTML($html, $outputDir, $invoiceId) {
    $htmlPath = $outputDir . 'invoice_' . $invoiceId . '_' . time() . '.html';
    
    // Add viewport meta for mobile viewing
    $html = str_replace(
        '<meta name="viewport"',
        '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"><meta name="viewport"',
        $html
    );
    
    file_put_contents($htmlPath, $html);
    return $htmlPath;
}

/**
 * Alternative: Convert to PDF using browser print CSS
 * Then convert PDF to image (requires imagemagick)
 */
function convertViaPDF($htmlPath, $outputDir, $invoiceId) {
    // This requires wkhtmltopdf and imagemagick
    $pdfPath = $outputDir . 'invoice_' . $invoiceId . '_temp.pdf';
    $imagePath = $outputDir . 'invoice_' . $invoiceId . '_' . time() . '.jpg';
    
    // Convert HTML to PDF
    $command1 = sprintf(
        'wkhtmltopdf %s %s',
        escapeshellarg($htmlPath),
        escapeshellarg($pdfPath)
    );
    exec($command1, $output1, $code1);
    
    if ($code1 === 0 && file_exists($pdfPath)) {
        // Convert PDF to image
        $command2 = sprintf(
            'convert -density 150 %s -quality 95 %s',
            escapeshellarg($pdfPath),
            escapeshellarg($imagePath)
        );
        exec($command2, $output2, $code2);
        
        if ($code2 === 0 && file_exists($imagePath)) {
            @unlink($pdfPath);
            @unlink($htmlPath);
            return $imagePath;
        }
    }
    
    return false;
}

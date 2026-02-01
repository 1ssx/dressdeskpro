<?php
/**
 * Invoice PDF Generator
 * Generates PDF invoices for WhatsApp sending
 * Uses FPDF library (lightweight and simple)
 */

require_once __DIR__ . '/../app/helpers/fpdf/fpdf.php';

class InvoicePDF extends FPDF
{
    private $invoice;
    private $customer;
    private $items;
    
    public function __construct($invoice, $customer, $items = [])
    {
        parent::__construct('P', 'mm', 'A4');
        $this->invoice = $invoice;
        $this->customer = $customer;
        $this->items = $items;
    }
    
    // Header
    function Header()
    {
        // Arabic font support would require additional setup
        // For now, using default fonts with English/Numbers
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(0, 10, 'Invoice / Fatorh', 0, 1, 'C');
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Lamasat Al Ostorah Store', 0,1, 'C');
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, 'Luxury Wedding Dresses', 0, 1, 'C');
        
        $this->Ln(5);
        
        // Invoice number and date
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, 'Invoice #: ' . ($this->invoice['invoice_number'] ?? 'N/A'), 0, 1);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, 'Date: ' . ($this->invoice['invoice_date'] ?? date('Y-m-d')), 0, 1);
        
        $this->Ln(3);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }
    
    // Footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
    
    public function generateInvoice()
    {
        $this->AddPage();
        
        // Customer Information
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, 'Customer Information', 0, 1);
        $this->SetFont('Arial', '', 10);
        
        $this->Cell(40, 6, 'Name:', 0, 0);
        $this->Cell(0, 6, $this->customer['name'] ?? 'N/A', 0, 1);
        
        $this->Cell(40, 6, 'Phone 1:', 0, 0);
        $this->Cell(0, 6, $this->customer['phone_1'] ?? 'N/A', 0, 1);
        
        if (!empty($this->customer['phone_2'])) {
            $this->Cell(40, 6, 'Phone 2:', 0, 0);
            $this->Cell(0, 6, $this->customer['phone_2'], 0, 1);
        }
        
        $this->Ln(5);
        
        // Invoice Details
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, 'Invoice Details', 0, 1);
        $this->SetFont('Arial', '', 10);
        
        $this->Cell(50, 6, 'Operation Type:', 0, 0);
        $this->Cell(0, 6, $this->translateOperationType($this->invoice['operation_type'] ?? ''), 0, 1);
        
        $this->Cell(50, 6, 'Payment Method:', 0, 0);
        $this->Cell(0, 6, $this->translatePaymentMethod($this->invoice['payment_method'] ?? ''), 0, 1);
        
        if (!empty($this->invoice['wedding_date'])) {
            $this->Cell(50, 6, 'Wedding Date:', 0, 0);
            $this->Cell(0, 6, $this->invoice['wedding_date'], 0, 1);
        }
        
        if (!empty($this->invoice['collection_date'])) {
            $this->Cell(50, 6, 'Collection Date:', 0, 0);
            $this->Cell(0, 6, $this->invoice['collection_date'], 0, 1);
        }
        
        $this->Ln(5);
        
        // Items/Products
        if (!empty($this->items)) {
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 7, 'Items', 0, 1);
            
            // Table header
            $this->SetFont('Arial', 'B', 10);
            $this->SetFillColor(200, 200, 200);
            $this->Cell(80, 7, 'Item Name', 1, 0, 'C', true);
            $this->Cell(30, 7, 'Quantity', 1, 0, 'C', true);
            $this->Cell(40, 7, 'Unit Price', 1, 0, 'C', true);
            $this->Cell(40, 7, 'Total', 1, 1, 'C', true);
            
            // Table rows
            $this->SetFont('Arial', '', 9);
            foreach ($this->items as $item) {
                $this->Cell(80, 6, $this->sanitizeText($item['item_name'] ?? ''), 1, 0);
                $this->Cell(30, 6, $item['quantity'] ?? '1', 1, 0, 'C');
                $this->Cell(40, 6, number_format($item['unit_price'] ?? 0, 2) . ' SAR', 1, 0, 'R');
                $this->Cell(40, 6, number_format($item['total_price'] ?? 0, 2) . ' SAR', 1, 1, 'R');
            }
            
            $this->Ln(3);
        }
        
        // Financial Summary
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, 'Financial Summary', 0, 1);
        $this->SetFont('Arial', '', 10);
        
        $this->Cell(100, 6, '', 0, 0);
        $this->Cell(50, 6, 'Total Price:', 0, 0, 'R');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(40, 6, number_format($this->invoice['total_price'] ?? 0, 2) . ' SAR', 0, 1, 'R');
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(100, 6, '', 0, 0);
        $this->Cell(50, 6, 'Deposit Paid:', 0, 0, 'R');
        $this->Cell(40, 6, number_format($this->invoice['deposit_amount'] ?? 0, 2) . ' SAR', 0, 1, 'R');
        
        $this->Line(150, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);
        
        $this->Cell(100, 6, '', 0, 0);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(50, 6, 'Remaining Balance:', 0, 0, 'R');
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(200, 0, 0);
        $this->Cell(40, 6, number_format($this->invoice['remaining_balance'] ?? 0, 2) . ' SAR', 0, 1, 'R');
        $this->SetTextColor(0, 0, 0);
        
        // Notes
        if (!empty($this->invoice['notes'])) {
            $this->Ln(8);
            $this->SetFont('Arial', 'B', 11);
            $this->Cell(0, 6, 'Notes:', 0, 1);
            $this->SetFont('Arial', '', 9);
            $this->MultiCell(0, 5, $this->sanitizeText($this->invoice['notes']));
        }
        
        // Terms and Conditions
        $this->Ln(10);
        $this->SetFont('Arial', 'I', 7);
        $this->MultiCell(0, 4, "Terms: Deposits are non-refundable. Store is not responsible for size changes after measurements. No complaints after leaving the store.");
    }
    
    private function translateOperationType($type)
    {
        $types = [
            'sale' => 'Sale',
            'rent' => 'Rent',
            'design' => 'Design',
            'design-sale' => 'Design & Sale',
            'design-rent' => 'Design & Rent'
        ];
        return $types[$type] ?? $type;
    }
    
    private function translatePaymentMethod($method)
    {
        $methods = [
            'cash' => 'Cash',
            'card' => 'Card/Network',
            'transfer' => 'Bank Transfer',
            'mixed' => 'Mixed'
        ];
        return $methods[$method] ?? $method;
    }
    
    private function sanitizeText($text)
    {
        // Remove or replace Arabic characters for basic PDF
        // In production, you'd use a proper Arabic font
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    }
}

/**
 * Generate PDF for an invoice
 * 
 * @param array $invoice Invoice data
 * @param array $customer Customer data
 * @param array $items Invoice items
 * @param string $outputPath Path to save PDF
 * @return bool Success status
 */
function generateInvoicePDF($invoice, $customer, $items = [], $outputPath = null)
{
    try {
        $pdf = new InvoicePDF($invoice, $customer, $items);
        $pdf->generateInvoice();
        
        if ($outputPath) {
            $pdf->Output('F', $outputPath);
        } else {
            // Return as string
            return $pdf->Output('S');
        }
        
        return true;
    } catch (Exception $e) {
        error_log("PDF Generation Error: " . $e->getMessage());
        return false;
    }
}

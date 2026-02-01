<?php
/**
 * Generate Print-Quality Invoice HTML for WhatsApp
 * Uses the same layout as invoice-print.js
 */

function generatePrintInvoiceHTML($invoiceId, $pdo, $storeName = null) {
    // ✅ FIX: Get dynamic store name
    if (empty($storeName)) {
        $storeName = $_SESSION['store_name'] ?? 'المتجر';
    }
    
    try {
        // Get invoice with customer data
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
        $stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
        $stmt->execute([$invoiceId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse measurements for items
        foreach ($items as &$item) {
            if (!empty($item['measurements'])) {
                $item['measurements_decoded'] = json_decode($item['measurements'], true) ?: [];
            } else {
                $item['measurements_decoded'] = [];
            }
        }
        
        // Separate accessories and products
        $accessories = [];
        $products = [];
        
        foreach ($items as $item) {
            if ($item['item_type'] === 'accessory') {
                $accessories[] = $item['item_name'];
            } else {
                $products[] = $item;
            }
        }
        
        // Get the main dress item
        $mainItem = !empty($products) ? $products[0] : [];
        $measurements = $mainItem['measurements_decoded'] ?? [];
        
        // Check if measurements exist
        $hasMeasurements = !empty($measurements['bust']) || !empty($measurements['waist']) || 
                          !empty($measurements['length']) || !empty($measurements['sleeve']) || 
                          !empty($measurements['shoulder']) || !empty($measurements['other']);
        
        // Generate HTML
        $html = generateInvoiceHTML_Print($invoice, $mainItem, $accessories, $measurements, $hasMeasurements, $storeName);
        
        return $html;
        
    } catch (Exception $e) {
        error_log("Generate Print Invoice HTML Error: " . $e->getMessage());
        return false;
    }
}

function generateInvoiceHTML_Print($invoice, $item, $accessories, $measurements, $hasMeasurements, $storeName = 'المتجر') {
    
    // Helper functions
    $formatCurrency = function($amount) {
        return number_format($amount ?? 0, 2, '.', ',') . ' ريال';
    };
    
    $formatDate = function($dateString) {
        if (!$dateString || $dateString === '0000-00-00') return '-';
        return date('Y/m/d', strtotime($dateString));
    };
    
    $translateType = function($type) {
        $types = [
            'sale' => 'بيع',
            'rent' => 'إيجار',
            'design' => 'تصميم',
            'design-sale' => 'تصميم وبيع',
            'design-rent' => 'تصميم وإيجار'
        ];
        return $types[$type] ?? $type ?? '-';
    };
    
    $translatePayment = function($method) {
        $methods = [
            'cash' => 'نقداً',
            'card' => 'بطاقة / شبكة',
            'transfer' => 'تحويل بنكي',
            'mixed' => 'مختلطة'
        ];
        return $methods[$method] ?? $method ?? '-';
    };
    
    // Accessories HTML
    $accessoriesHtml = '<span style="color:#999; font-style:italic; font-size:11px;">لا توجد إكسسوارات إضافية</span>';
    if (!empty($accessories)) {
        $tags = array_map(function($acc) {
            return '<span class="tag"><i class="fas fa-check" style="color:#c5a059; font-size:10px;"></i> ' . htmlspecialchars($acc) . '</span>';
        }, $accessories);
        $accessoriesHtml = '<div class="tags">' . implode('', $tags) . '</div>';
    }
    
    // Measurements box
    $measurementsBox = '';
    if ($hasMeasurements) {
        $measurementsBox = '
        <div class="col-4 box">
            <div class="box-header gold">
                <i class="fas fa-ruler-combined"></i> القياسات
            </div>
            <div class="box-content measurements-grid">
                <div class="measure-item"><span class="measure-label">الصدر</span><span class="measure-val">' . ($measurements['bust'] ?? '-') . '</span></div>
                <div class="measure-item"><span class="measure-label">الخصر</span><span class="measure-val">' . ($measurements['waist'] ?? '-') . '</span></div>
                <div class="measure-item"><span class="measure-label">الطول</span><span class="measure-val">' . ($measurements['length'] ?? '-') . '</span></div>
                <div class="measure-item"><span class="measure-label">الأكمام</span><span class="measure-val">' . ($measurements['sleeve'] ?? '-') . '</span></div>
                <div class="measure-item"><span class="measure-label">الأكتاف</span><span class="measure-val">' . ($measurements['shoulder'] ?? '-') . '</span></div>
                ' . (!empty($measurements['other']) ? '<div class="measure-item" style="grid-column:span 2; text-align:right; font-size:11px; padding:2px 5px;"><span class="measure-label">أخرى:</span> ' . htmlspecialchars($measurements['other']) . '</div>' : '') . '
            </div>
        </div>';
    }
    
    $dressBoxClass = $hasMeasurements ? 'col-8' : 'col-12';
    
    $html = '<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة رقم ' . htmlspecialchars($invoice['invoice_number']) . '</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a1a1a;
            --gold: #c5a059;
            --gold-light: #f9f5eb;
            --border: #e0e0e0;
            --text: #333;
            --text-light: #666;
        }
        
        * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        
        body {
            font-family: "Tajawal", sans-serif;
            margin: 0;
            padding: 0;
            background: #fff;
            color: var(--text);
            direction: rtl;
            font-size: 13px;
        }

        .invoice-container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 10mm;
            background: white;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid var(--gold);
            padding-bottom: 15px;
            position: relative;
        }
        
        .header h1 {
            margin: 0;
            font-size: 26px;
            color: var(--primary);
            font-weight: 800;
            letter-spacing: 1px;
        }
        
        .header .subtitle {
            color: var(--gold);
            font-size: 14px;
            margin-top: 5px;
            font-weight: bold;
        }

        .header .print-meta {
            position: absolute;
            left: 0;
            top: 0;
            font-size: 10px;
            color: #999;
            text-align: left;
        }

        .grid-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .col-3 { flex: 0 0 calc(25% - 11.25px); }
        .col-4 { flex: 0 0 calc(33.333% - 10px); }
        .col-6 { flex: 0 0 calc(50% - 7.5px); }
        .col-8 { flex: 0 0 calc(66.666% - 7.5px); }
        .col-12 { flex: 0 0 100%; }

        .box {
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            height: 100%;
        }

        .box-header {
            background: var(--primary);
            color: #fff;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .box-header.gold { background: var(--gold); color: #000; }

        .box-content {
            padding: 12px;
        }

        .data-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            border-bottom: 1px dashed #eee;
            padding-bottom: 4px;
        }
        .data-row:last-child { border-bottom: none; margin-bottom: 0; }
        
        .label { color: var(--text-light); font-weight: 500; font-size: 11px; }
        .value { color: var(--primary); font-weight: 700; font-size: 12px; }

        .measurements-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .measure-item {
            background: #fdfdfd;
            border: 1px solid #eee;
            padding: 5px;
            border-radius: 4px;
            text-align: center;
        }
        .measure-label { display: block; font-size: 10px; color: #888; }
        .measure-val { display: block; font-weight: bold; color: var(--primary); }

        .product-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }
        .product-table th {
            background: var(--gold-light);
            color: var(--primary);
            padding: 6px;
            font-size: 11px;
            border-bottom: 2px solid var(--gold);
        }
        .product-table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
        }

        .summary-box {
            background: var(--gold-light);
            border: 1px solid var(--gold);
            border-radius: 8px;
            padding: 12px;
            height: 100%;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .summary-row.total {
            border-top: 2px solid var(--gold);
            padding-top: 8px;
            margin-top: 8px;
            font-size: 16px;
            font-weight: 900;
            color: var(--primary);
        }

        .tags { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px; }
        .tag {
            background: #fff;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            color: #333;
            border: 1px solid #ccc;
        }

        .terms-box {
            margin-top: 15px;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 8px;
            background: #fcfcfc;
        }
        .terms-title {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 5px;
            color: #e74c3c;
            text-decoration: underline;
        }
        .terms-list {
            margin: 0;
            padding-right: 15px;
            font-size: 9px;
            color: #555;
            line-height: 1.5;
        }

        .signatures {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            padding: 0 30px;
        }
        .sign-box {
            text-align: center;
            width: 150px;
        }
        .sign-line {
            margin-top: 30px;
            border-bottom: 1px solid #333;
        }
        .sign-label { font-size: 11px; margin-top: 2px; display: block; }

        @media print {
            @page { size: A4; margin: 0; }
            body { padding: 0; }
            .invoice-container { width: 100%; max-width: none; padding: 5mm 10mm; }
            .box, .summary-box { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <header class="header">
            <div class="print-meta">
                تاريخ الطباعة: ' . date('Y/m/d') . '<br>
                الرقم المرجعي: #' . $invoice['id'] . '
            </div>
            <h1>' . htmlspecialchars($storeName) . '</h1>
            <div class="subtitle">Luxury Wedding Dresses</div>
        </header>

        <div class="grid-row">
            <div class="col-6 box">
                <div class="box-header">
                    <i class="fas fa-file-invoice"></i> تفاصيل الفاتورة
                </div>
                <div class="box-content">
                    <div class="data-row"><span class="label">رقم الفاتورة:</span><span class="value">#' . htmlspecialchars($invoice['invoice_number']) . '</span></div>
                    <div class="data-row"><span class="label">تاريخ الإصدار:</span><span class="value">' . $formatDate($invoice['invoice_date']) . '</span></div>
                    <div class="data-row"><span class="label">نوع العملية:</span><span class="value">' . $translateType($invoice['operation_type']) . '</span></div>
                    <div class="data-row"><span class="label">طريقة الدفع:</span><span class="value">' . $translatePayment($invoice['payment_method']) . '</span></div>
                </div>
            </div>
            <div class="col-6 box">
                <div class="box-header gold">
                    <i class="fas fa-user"></i> بيانات العميل
                </div>
                <div class="box-content">
                    <div class="data-row"><span class="label">الاسم:</span><span class="value">' . htmlspecialchars($invoice['customer_name'] ?? 'غير محدد') . '</span></div>
                    <div class="data-row"><span class="label">جوال 1:</span><span class="value" style="direction:ltr;text-align:right">' . htmlspecialchars($invoice['phone_1'] ?? '-') . '</span></div>
                    <div class="data-row"><span class="label">جوال 2:</span><span class="value" style="direction:ltr;text-align:right">' . htmlspecialchars($invoice['phone_2'] ?? '-') . '</span></div>
                </div>
            </div>
        </div>

        <div class="box" style="margin-bottom: 15px;">
            <div class="box-header">
                <i class="fas fa-calendar-alt"></i> التواريخ المهمة
            </div>
            <div class="box-content" style="display:flex; justify-content:space-around; text-align:center;">
                <div>
                    <span class="label" style="display:block; margin-bottom:4px;">تاريخ المناسبة/الزفاف</span>
                    <span class="value" style="font-size:14px; color:#c5a059;">' . $formatDate($invoice['wedding_date']) . '</span>
                </div>
                <div style="border-right:1px solid #eee; height:30px;"></div>
                <div>
                    <span class="label" style="display:block; margin-bottom:4px;">تاريخ الاستلام</span>
                    <span class="value">' . $formatDate($invoice['collection_date']) . '</span>
                </div>
                <div style="border-right:1px solid #eee; height:30px;"></div>
                <div>
                    <span class="label" style="display:block; margin-bottom:4px;">تاريخ الإرجاع</span>
                    <span class="value">' . $formatDate($invoice['return_date']) . '</span>
                </div>
            </div>
        </div>

        <div class="grid-row">
            <div class="' . $dressBoxClass . ' box">
                <div class="box-header">
                    <i class="fas fa-tshirt"></i> تفاصيل الفستان
                </div>
                <div class="box-content">
                    <table class="product-table">
                        <thead>
                            <tr>
                                <th style="text-align:right">موديل / اسم الفستان</th>
                                <th>اللون</th>
                                <th>الكود</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="text-align:right">' . htmlspecialchars($item['item_name'] ?? 'غير محدد') . '</td>
                                <td>' . htmlspecialchars($item['color'] ?? '-') . '</td>
                                <td style="font-family:sans-serif">' . htmlspecialchars($item['item_code'] ?? '-') . '</td>
                            </tr>
                        </tbody>
                    </table>
                    <div style="margin-top: 10px; padding-top:8px; border-top:1px dashed #eee;">
                        <strong style="font-size:11px;">الملحقات:</strong>
                        ' . $accessoriesHtml . '
                    </div>
                </div>
            </div>
            ' . $measurementsBox . '
        </div>

        <div class="grid-row">
            <div class="col-6 box">
                <div class="box-header">
                    <i class="fas fa-pen"></i> ملاحظات
                </div>
                <div class="box-content">
                    <p style="margin:0; font-size:12px; color:#555; white-space: pre-wrap;">' . htmlspecialchars($invoice['notes'] ?? 'لا توجد ملاحظات.') . '</p>
                </div>
            </div>
            <div class="col-6 summary-box">
                <div class="summary-row"><span>السعر الإجمالي</span><span>' . $formatCurrency($invoice['total_price']) . '</span></div>
                <div class="summary-row" style="color:#666"><span>العربون المدفوع</span><span>' . $formatCurrency($invoice['deposit_amount']) . '</span></div>
                <div class="summary-row total"><span>المبلغ المتبقي</span><span>' . $formatCurrency($invoice['remaining_balance']) . '</span></div>
            </div>
        </div>

        <div class="signatures">
            <div class="sign-box">
                <strong>توقيع البائع</strong>
                <div class="sign-line"></div>
                <span class="sign-label">Seller Signature</span>
            </div>
            <div class="sign-box">
                <strong>توقيع العميل</strong>
                <div class="sign-line"></div>
                <span class="sign-label">Customer Signature</span>
            </div>
        </div>

        <div class="terms-box">
            <div class="terms-title">شروط التعاقد:</div>
            <ul class="terms-list">
                <li>العربون المدفوع غير قابل للاسترداد في حال إلغاء الطلب من قبل العميل.</li>
                <li>يحق للعميل طلب تغيير الموديل المختار، وذلك وفقاً للشروط والأحكام المعتمدة في سياسة المحل.</li>
                <li>استلام الفستان إقرار بسلامته وخلوه من العيوب؛ لا تُقبل أي شكاوى بعد مغادرة المحل.</li>
                <li>تُحتسب رسوم تأخير يومية قدرها (500) ريال في حال عدم إعادة الفستان في الموعد المتفق عليه.</li>
                <li>في حال حدوث تلف أو بقع، تُخصم تكلفة الإصلاح الفعلي فقط من مبلغ التأمين المدفوع.</li>
            </ul>
        </div>
    </div>
</body>
</html>';

    return $html;
}

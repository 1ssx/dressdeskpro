/**
 * Invoice Image Generator & WhatsApp Sender
 * نظام توليد صور الفواتير وإرسالها عبر واتساب
 * Uses html2canvas to capture the print-style invoice layout as JPG
 */

const InvoiceImageSender = (function () {
    // Detect base URL for API calls (handles different page locations)
    function getApiBasePath() {
        // Check if we're in the public folder
        const path = window.location.pathname;
        if (path.includes('/public/')) {
            return 'api/';
        }
        return 'public/api/';
    }

    // API Endpoints
    const INVOICE_API = getApiBasePath() + 'invoices.php';
    const WHATSAPP_IMAGE_API = getApiBasePath() + 'whatsapp/send_invoice_image.php';

    // html2canvas CDN URL (fallback injection)
    const HTML2CANVAS_CDN = 'https://html2canvas.hertzen.com/dist/html2canvas.min.js';

    /**
     * Dynamically load html2canvas if not already loaded
     */
    function loadHtml2Canvas() {
        return new Promise((resolve, reject) => {
            if (typeof html2canvas !== 'undefined') {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = HTML2CANVAS_CDN;
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Failed to load html2canvas library'));
            document.head.appendChild(script);
        });
    }

    /**
     * Format currency for display
     */
    const formatCurrency = (amount) => {
        return Number(amount || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' <small>ريال</small>';
    };

    /**
     * Format date for display
     */
    const formatDate = (dateString) => {
        if (!dateString || dateString === '0000-00-00' || dateString === null) return '-';
        const date = new Date(dateString);
        return `${date.getFullYear()}/${String(date.getMonth() + 1).padStart(2, '0')}/${String(date.getDate()).padStart(2, '0')}`;
    };

    /**
     * Translate operation types
     */
    const translateType = (type) => {
        const types = {
            'sale': 'بيع', 'rent': 'إيجار', 'design': 'تصميم',
            'design-sale': 'تصميم وبيع', 'design-rent': 'تصميم وإيجار'
        };
        return types[type] || type || '-';
    };

    /**
     * Generate the invoice HTML for image capture
     * Uses the same "Royal Monochrome" design from invoice-print.js
     */
    function generateInvoiceHTML(data) {
        const inv = data.invoice || {};
        const item = data.item || {};
        const acc = data.accessories || [];
        const store = data.store_info || {};
        const storeName = store.name || 'اسم المتجر';
        const storeSlogan = store.slogan || '';
        const storeLogo = store.logo || null;

        // Unified measurements
        const measurements = {
            bust: inv.bust_measurement || item.bust_meas || null,
            waist: inv.waist_measurement || item.waist_meas || null,
            length: inv.dress_length || item.dress_len || null,
            sleeve: inv.sleeve_length || item.sleeve_len || null,
            shoulder: inv.shoulder_length || item.shoulder_len || null,
            other: inv.other_measurements || item.other_meas || null
        };

        const hasMeasurements = (
            (measurements.bust && measurements.bust != '0') ||
            (measurements.waist && measurements.waist != '0') ||
            (measurements.length && measurements.length != '0') ||
            (measurements.sleeve && measurements.sleeve != '0') ||
            (measurements.shoulder && measurements.shoulder != '0') ||
            (measurements.other && measurements.other.trim().length > 0)
        );

        // Merge all accessories
        let allAccessories = [...acc];
        if (data.items && Array.isArray(data.items)) {
            data.items.forEach(prod => {
                if (prod.item_name !== item.dress_name && prod.id !== item.id) {
                    allAccessories.push(prod.item_name);
                }
            });
        }
        const uniqueAccessories = [...new Set(allAccessories)]
            .filter(a => a && typeof a === 'string' && a.trim().length > 0);

        let accessoriesHtml = '<span style="color:#999; font-style:italic; font-size:11px;">لا توجد ملحقات</span>';
        if (uniqueAccessories.length > 0) {
            accessoriesHtml = `<div style="display:flex;flex-wrap:wrap;gap:6px;">${uniqueAccessories.map(a => `<span style="background:#fff;padding:4px 8px;border:1px solid #000;font-size:10px;font-weight:700;">✓ ${a}</span>`).join('')}</div>`;
        }

        let measurementsHTML = '';
        if (hasMeasurements) {
            measurementsHTML = `
                <div style="flex:0 0 30%;">
                    <div style="font-weight:800;font-size:13px;border-bottom:2px solid #000;padding-bottom:5px;margin-bottom:10px;">📏 القياسات</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                        <div style="border:1px solid #ddd;padding:5px;text-align:center;"><span style="font-size:9px;color:#555;display:block;">الصدر</span><strong>${measurements.bust || '-'}</strong></div>
                        <div style="border:1px solid #ddd;padding:5px;text-align:center;"><span style="font-size:9px;color:#555;display:block;">الخصر</span><strong>${measurements.waist || '-'}</strong></div>
                        <div style="border:1px solid #ddd;padding:5px;text-align:center;"><span style="font-size:9px;color:#555;display:block;">الطول</span><strong>${measurements.length || '-'}</strong></div>
                        <div style="border:1px solid #ddd;padding:5px;text-align:center;"><span style="font-size:9px;color:#555;display:block;">الأكمام</span><strong>${measurements.sleeve || '-'}</strong></div>
                        <div style="border:1px solid #ddd;padding:5px;text-align:center;"><span style="font-size:9px;color:#555;display:block;">الأكتاف</span><strong>${measurements.shoulder || '-'}</strong></div>
                        ${measurements.other ? `<div style="border:1px solid #ddd;padding:5px;text-align:center;grid-column:span 2;"><span style="font-size:9px;color:#555;">أخرى:</span> <strong>${measurements.other}</strong></div>` : ''}
                    </div>
                </div>
            `;
        }

        const dressColStyle = hasMeasurements ? 'flex:0 0 65%;' : 'flex:0 0 100%;';

        return `
        <div id="invoice-capture-area" style="width:794px;padding:30px;background:#fff;font-family:'Tajawal',Arial,sans-serif;direction:rtl;color:#000;line-height:1.4;">
            <!-- Header -->
            <div style="text-align:center;border-bottom:3px solid #000;padding-bottom:15px;margin-bottom:15px;position:relative;">
                <div style="position:absolute;left:0;top:0;font-size:10px;color:#555;text-align:left;">
                    تاريخ الفاتورة: ${formatDate(inv.invoice_date)}<br>
                    الرقم المرجعي: #${inv.id || 'N/A'}
                </div>
                ${storeLogo ? `<img src="${storeLogo}" alt="${storeName}" style="max-height:60px;margin-bottom:8px;">` : ''}
                <h1 style="margin:0;font-size:26px;font-weight:900;letter-spacing:1px;">${storeName}</h1>
                ${storeSlogan ? `<div style="color:#555;font-size:12px;margin-top:5px;">${storeSlogan}</div>` : ''}
            </div>

            <!-- Invoice & Customer Info -->
            <div style="display:flex;gap:20px;margin-bottom:15px;">
                <div style="flex:1;padding:10px;background:#f9f9f9;border:1px solid #eee;">
                    <div style="font-weight:800;font-size:13px;border-bottom:1px solid #000;padding-bottom:5px;margin-bottom:8px;">📄 تفاصيل الفاتورة</div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:5px;font-size:11px;"><span style="color:#555;">رقم الفاتورة:</span><strong style="font-size:13px;">#${inv.invoice_number}</strong></div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:5px;font-size:11px;"><span style="color:#555;">تاريخ الإصدار:</span><strong>${formatDate(inv.invoice_date)}</strong></div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:5px;font-size:11px;"><span style="color:#555;">نوع العملية:</span><strong>${translateType(inv.operation_type)}</strong></div>
                </div>
                <div style="flex:1;padding:10px;background:#f9f9f9;border:1px solid #eee;">
                    <div style="font-weight:800;font-size:13px;border-bottom:1px solid #000;padding-bottom:5px;margin-bottom:8px;">👤 بيانات العميل</div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:5px;font-size:11px;"><span style="color:#555;">الاسم:</span><strong style="font-size:13px;">${inv.customer_name || 'غير محدد'}</strong></div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:5px;font-size:11px;"><span style="color:#555;">جوال 1:</span><strong dir="ltr">${inv.phone_1 || '-'}</strong></div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:5px;font-size:11px;"><span style="color:#555;">جوال 2:</span><strong dir="ltr">${inv.phone_2 || '-'}</strong></div>
                </div>
            </div>

            <!-- Dates -->
            <div style="display:flex;justify-content:space-between;background:#f9f9f9;border:1px solid #eee;padding:10px;margin-bottom:15px;">
                <div style="flex:1;text-align:center;"><span style="font-size:10px;color:#555;display:block;">تاريخ المناسبة</span><strong style="font-size:14px;">${formatDate(inv.wedding_date)}</strong></div>
                <div style="flex:1;text-align:center;border-right:1px solid #ddd;border-left:1px solid #ddd;"><span style="font-size:10px;color:#555;display:block;">تاريخ الاستلام</span><strong style="font-size:12px;">${formatDate(inv.collection_date)}</strong></div>
                <div style="flex:1;text-align:center;"><span style="font-size:10px;color:#555;display:block;">تاريخ الإرجاع</span><strong style="font-size:12px;">${formatDate(inv.return_date)}</strong></div>
            </div>

            <!-- Dress Details & Measurements -->
            <div style="display:flex;gap:20px;margin-bottom:15px;">
                <div style="${dressColStyle}">
                    <div style="font-weight:800;font-size:13px;border-bottom:2px solid #000;padding-bottom:5px;margin-bottom:10px;">👗 تفاصيل الفستان</div>
                    <table style="width:100%;border-collapse:collapse;margin-bottom:10px;">
                        <thead><tr style="background:#f0f0f0;"><th style="padding:8px;border:1px solid #ddd;font-size:11px;text-align:right;">اسم الفستان</th><th style="padding:8px;border:1px solid #ddd;font-size:11px;">اللون</th><th style="padding:8px;border:1px solid #ddd;font-size:11px;">رقم الفستان</th></tr></thead>
                        <tbody><tr><td style="padding:8px;border:1px solid #ddd;font-weight:700;text-align:right;">${item.dress_name || 'غير محدد'}</td><td style="padding:8px;border:1px solid #ddd;text-align:center;">${item.dress_color || '-'}</td><td style="padding:8px;border:1px solid #ddd;text-align:center;font-family:sans-serif;">${item.dress_code || '-'}</td></tr></tbody>
                    </table>
                    <div><strong style="font-size:11px;">الملحقات:</strong>${accessoriesHtml}</div>
                </div>
                ${measurementsHTML}
            </div>

            <!-- Notes & Financial Summary -->
            <div style="display:flex;gap:20px;margin-bottom:15px;">
                <div style="flex:1;">
                    <div style="font-weight:800;font-size:13px;border-bottom:1px solid #000;padding-bottom:5px;margin-bottom:8px;">📝 ملاحظات</div>
                    <p style="margin:0;font-size:11px;color:#555;white-space:pre-wrap;">${inv.notes || 'لا توجد ملاحظات.'}</p>
                </div>
                <div style="flex:1;background:#f9f9f9;border:2px solid #000;padding:12px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:12px;"><span>السعر الإجمالي</span><strong>${formatCurrency(inv.total_price)}</strong></div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:12px;color:#555;"><span>العربون المدفوع</span><span>${formatCurrency(inv.deposit_amount)}</span></div>
                    <div style="display:flex;justify-content:space-between;border-top:2px solid #000;padding-top:10px;font-size:15px;font-weight:900;"><span>المبلغ المتبقي</span><span>${formatCurrency(inv.remaining_balance)}</span></div>
                </div>
            </div>

            <!-- Terms -->
            <div style="border-top:2px solid #000;padding-top:10px;">
                <div style="font-weight:800;font-size:12px;text-decoration:underline;margin-bottom:6px;">شروط التعاقد:</div>
                <ul style="margin:0;padding-right:18px;font-size:9px;color:#555;line-height:1.5;">
                    <li>العربون المدفوع غير قابل للاسترداد في حال إلغاء الطلب من قبل العميل.</li>
                    <li><strong>يحق للعميل طلب تغيير الموديل المختار، وفقاً لسياسة المحل.</strong></li>
                    <li>استلام الفستان إقرار بسلامته؛ لا تُقبل شكاوى بعد مغادرة المحل.</li>
                    <li>رسوم تأخير (500 ريال/يوم) في حال عدم الإرجاع بالموعد.</li>
                </ul>
            </div>

            <!-- Signatures -->
            <div style="display:flex;justify-content:space-between;margin-top:25px;padding:0 30px;">
                <div style="text-align:center;width:150px;"><strong>توقيع البائع</strong><div style="margin-top:30px;border-bottom:2px solid #000;"></div><span style="font-size:9px;color:#555;">Seller</span></div>
                <div style="text-align:center;width:150px;"><strong>توقيع العميل</strong><div style="margin-top:30px;border-bottom:2px solid #000;"></div><span style="font-size:9px;color:#555;">Customer</span></div>
            </div>
        </div>
        `;
    }

    /**
     * Capture invoice as Base64 JPG image
     * @param {Object} invoiceData - The invoice data from API
     * @returns {Promise<string>} Base64 JPG string
     */
    async function captureInvoiceAsImage(invoiceData) {
        await loadHtml2Canvas();

        // Create a temporary container
        const tempContainer = document.createElement('div');
        tempContainer.style.position = 'absolute';
        tempContainer.style.left = '-9999px';
        tempContainer.style.top = '0';
        tempContainer.innerHTML = generateInvoiceHTML(invoiceData);
        document.body.appendChild(tempContainer);

        const targetElement = tempContainer.querySelector('#invoice-capture-area');

        try {
            const canvas = await html2canvas(targetElement, {
                scale: 2, // High quality for readability
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false
            });

            // Convert canvas to Base64 JPG
            const base64Image = canvas.toDataURL('image/jpeg', 0.92);

            // Cleanup
            document.body.removeChild(tempContainer);

            return base64Image;
        } catch (error) {
            document.body.removeChild(tempContainer);
            throw error;
        }
    }

    /**
     * Main function: Generate invoice image and send via WhatsApp
     * @param {number} invoiceId - The invoice ID
     */
    async function sendInvoiceImageViaWhatsApp(invoiceId) {
        try {
            // Show loading
            const loadingMsg = '⏳ جاري تجهيز صورة الفاتورة...';
            console.log(loadingMsg);

            // 1. Fetch invoice data from API
            const response = await fetch(`${INVOICE_API}?action=get_invoice&id=${invoiceId}`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            const result = await response.json();
            if (result.status !== 'success' || !result.data) {
                throw new Error(result.message || 'فشل جلب بيانات الفاتورة');
            }

            const invoiceData = result.data;
            const customerPhone = invoiceData.invoice?.phone_1;

            if (!customerPhone) {
                throw new Error('رقم هاتف العميل غير متوفر');
            }

            // 2. Generate the invoice image
            console.log('📸 Capturing invoice image...');
            const base64Image = await captureInvoiceAsImage(invoiceData);

            // 3. Send to backend for WhatsApp delivery
            console.log('📤 Sending to WhatsApp API...');
            const sendResponse = await fetch(WHATSAPP_IMAGE_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    invoice_id: invoiceId,
                    phone: customerPhone,
                    image_base64: base64Image,
                    customer_name: invoiceData.invoice?.customer_name || 'عزيزي العميل',
                    invoice_number: invoiceData.invoice?.invoice_number || ''
                })
            });

            const sendResult = await sendResponse.json();

            if (sendResult.status === 'success') {
                console.log('✅ WhatsApp image sent successfully!');
                return { success: true, message: 'تم إرسال صورة الفاتورة بنجاح عبر واتساب' };
            } else {
                throw new Error(sendResult.message || 'فشل إرسال الرسالة');
            }

        } catch (error) {
            console.error('❌ Error:', error);
            return { success: false, message: error.message };
        }
    }

    // Public API
    return {
        send: sendInvoiceImageViaWhatsApp,
        captureAsImage: captureInvoiceAsImage,
        generateHTML: generateInvoiceHTML
    };

})();

// Expose globally
window.InvoiceImageSender = InvoiceImageSender;

/**
 * Invoice Printing System - Luxury Monochrome Edition
 * نظام طباعة الفواتير الاحترافي - تصميم ملكي عصري (أبيض وأسود)
 * يدعم تعدد المتاجر (بيانات ديناميكية)
 */

const PrintInvoice = (function () {
    // API Endpoint URL
    const API_URL = 'api/invoices.php';

    // ==========================================
    // 1. Helper Functions (دوال مساعدة للتنسيق)
    // ==========================================

    const formatCurrency = (amount) => {
        return Number(amount || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' <small>ريال</small>'; // استخدام small لتصغير كلمة ريال جمالياً
    };

    const formatDate = (dateString) => {
        if (!dateString || dateString === '0000-00-00' || dateString === null) return '-';
        // استخدام تنسيق YYYY/MM/DD الشائع في الفواتير الرسمية
        const date = new Date(dateString);
        return `${date.getFullYear()}/${String(date.getMonth() + 1).padStart(2, '0')}/${String(date.getDate()).padStart(2, '0')}`;
    };

    const translateType = (type) => {
        const types = {
            'sale': 'بيع', 'rent': 'إيجار', 'design': 'تصميم',
            'design-sale': 'تصميم وبيع', 'design-rent': 'تصميم وإيجار'
        };
        return types[type] || type || '-';
    };

    const translatePayment = (method) => {
        const methods = {
            'cash': 'نقداً', 'card': 'بطاقة / شبكة',
            'transfer': 'تحويل بنكي', 'mixed': 'مختلطة'
        };
        return methods[method] || method || '-';
    };

    // ==========================================
    // 2. CSS Styles (التصميم الاحترافي الجديد)
    // ==========================================

    const getStyles = () => `
        <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            /* --- تعريف متغيرات الألوان (مونوكروم) --- */
            :root {
                --bg-paper: #ffffff;
                --text-primary: #000000;   /* أسود داكن للعناوين والقيم */
                --text-secondary: #555555; /* رمادي غامق للعناوين الفرعية */
                --border-strong: #000000;  /* حدود سوداء سميكة */
                --border-light: #e0e0e0;   /* حدود رمادية رفيعة للفصل */
                --accent-gray: #f9f9f9;    /* خلفيات رمادية فاتحة جداً للتمييز */
            }

            /* --- إعدادات الطباعة الأساسية --- */
            * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            
            body {
                font-family: 'Tajawal', sans-serif;
                margin: 0; padding: 0;
                background: #f4f4f4; /* خلفية المتصفح (لن تظهر في الطباعة) */
                color: var(--text-primary);
                direction: rtl;
                font-size: 12px;
                line-height: 1.4;
            }

            /* حاوية الفاتورة (A4) */
            .invoice-container {
                max-width: 210mm;
                margin: 20px auto;
                padding: 15mm;
                background: var(--bg-paper);
                box-shadow: 0 10px 30px rgba(0,0,0,0.1); /* ظل خفيف للعرض على الشاشة */
            }

            /* --- تنسيق الترويسة (Header) --- */
            .header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 3px solid var(--border-strong);
                padding-bottom: 15px;
                position: relative;
            }
            
            .header h1 {
                margin: 0;
                font-size: 32px; font-weight: 900;
                color: var(--text-primary);
                letter-spacing: 2px; /* تباعد أحرف للفخامة */
                text-transform: uppercase;
            }
            
            .header .subtitle {
                color: var(--text-secondary);
                font-size: 14px; font-weight: 500;
                margin-top: 8px;
                letter-spacing: 1px;
            }

            /* معلومات الطباعة العلوية */
            .header .print-meta {
                position: absolute; left: 0; top: 0;
                font-size: 11px; color: var(--text-secondary);
                text-align: left; line-height: 1.4;
            }

            /* --- نظام الشبكة (Grid System) --- */
            .grid-row { display: flex; gap: 20px; margin-bottom: 20px; align-items: flex-start; }
            .col-4 { flex: 0 0 calc(33.333% - 17px); }
            .col-6 { flex: 0 0 calc(50% - 12.5px); }
            .col-8 { flex: 0 0 calc(66.666% - 12.5px); }
            .col-12 { flex: 0 0 100%; }

            /* --- عناوين الأقسام --- */
            .section-title {
                font-size: 16px; font-weight: 800;
                margin: 0 0 15px 0; padding-bottom: 8px;
                border-bottom: 2px solid var(--border-strong); /* خط أسود تحت العنوان */
                display: flex; align-items: center; gap: 10px;
            }
            .section-title i { font-size: 14px; }

            /* --- صفوف البيانات (Label : Value) --- */
            .data-row {
                display: flex; justify-content: space-between;
                margin-bottom: 8px; padding-bottom: 5px;
                border-bottom: 1px solid var(--border-light); /* خط رمادي رفيع */
            }
            .data-row:last-child { border-bottom: none; margin-bottom: 0; }
            .label { color: var(--text-secondary); font-weight: 500; font-size: 12px; }
            .value { color: var(--text-primary); font-weight: 800; font-size: 13px; }

            /* --- صندوق التواريخ المهمة --- */
            .dates-box {
                display: flex; justify-content: space-between;
                background: var(--accent-gray);
                border: 1px solid var(--border-light);
                padding: 10px 15px; margin-bottom: 20px;
            }
            .date-item { text-align: center; flex: 1; position: relative; }
            .date-item:not(:last-child)::after { /* خط فاصل عمودي بين التواريخ */
                content: ''; position: absolute; left: 0; top: 10%; height: 80%;
                border-left: 1px solid var(--border-light);
            }
            .date-label { display: block; font-size: 11px; color: var(--text-secondary); margin-bottom: 5px; }
            .date-value { display: block; font-size: 15px; font-weight: 800; }
            .date-value.highlight { font-size: 17px; } /* تمييز تاريخ المناسبة */

            /* --- جداول المنتجات --- */
            .product-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            .product-table th {
                background: var(--accent-gray);
                color: var(--text-primary);
                padding: 10px; font-size: 12px; font-weight: 800;
                border-bottom: 2px solid var(--border-strong);
            }
            .product-table td {
                padding: 8px 8px;
                border-bottom: 1px solid var(--border-light);
                text-align: center; font-weight: 700; font-size: 13px;
            }

            /* --- شبكة القياسات --- */
            .measurements-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
            .measure-item {
                background: var(--bg-paper);
                border: 2px solid var(--border-light); /* حدود واضحة للقياسات */
                padding: 8px; text-align: center;
            }
            .measure-label { display: block; font-size: 10px; color: var(--text-secondary); margin-bottom: 3px; }
            .measure-val { display: block; font-weight: 900; font-size: 14px; }

            /* --- الملخص المالي والملاحظات --- */
            .notes-content p { margin: 0; font-size: 12px; color: var(--text-secondary); white-space: pre-wrap; line-height: 1.6; }
            
            .summary-box {
                background: var(--accent-gray);
                border: 2px solid var(--border-strong); /* إطار أسود بارز للملخص */
                padding: 15px;
            }
            .summary-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; font-weight: 500; }
            .summary-row.total {
                border-top: 3px solid var(--border-strong);
                padding-top: 15px; margin-top: 15px;
                font-size: 18px; font-weight: 900;
            }

            /* --- الملحقات (Tags) --- */
            .tags { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
            .tag {
                background: #fff; padding: 5px 10px;
                border: 1px solid var(--border-strong); /* إطار أسود */
                font-size: 11px; font-weight: 700; color: var(--text-primary);
                display: flex; align-items: center; gap: 5px;
            }

            /* --- الشروط والتذييل --- */
            .terms-container {
                margin-top: 20px; padding-top: 15px;
                border-top: 3px solid var(--border-strong);
            }
            .terms-title { font-weight: 800; font-size: 13px; margin-bottom: 8px; text-decoration: underline; }
            .terms-list { margin: 0; padding-right: 20px; font-size: 10px; color: var(--text-secondary); line-height: 1.6; }

            .signatures {
                margin-top: 30px; display: flex; justify-content: space-between; padding: 0 40px;
            }
            .sign-box { text-align: center; width: 200px; }
            .sign-line { margin-top: 40px; border-bottom: 2px solid var(--border-strong); }
            .sign-label { font-size: 11px; margin-top: 5px; display: block; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; }

            /* --- تنسيقات خاصة بالطباعة --- */
            @media print {
                @page { size: A4; margin: 0; }
                body { padding: 0; background: #fff; }
                .invoice-container { width: 100%; max-width: none; margin: 0; padding: 10mm 15mm; box-shadow: none; }
                .grid-row, .summary-box, .dates-box { break-inside: avoid; }
            }
        </style>
    `;

    // ==========================================
    // 3. HTML Layout (هيكل الفاتورة الديناميكي)
    // ==========================================

    const getLayout = (data) => {
        // استخراج البيانات مع استخدام قيم افتراضية لتجنب الأخطاء
        const inv = data.invoice || {};
        const item = data.item || {};
        const acc = data.accessories || [];

        // ** هام: استخراج بيانات المتجر الديناميكية **
        // نفترض أن الـ API يعيد كائن اسمه store_info يحتوي على بيانات المتجر
        // إذا لم يكن موجوداً، نستخدم قيماً افتراضية عامة
        const store = data.store_info || {};
        const storeName = store.name || 'اسم المتجر'; // قيمة افتراضية في حال عدم وجود اسم
        const storeSlogan = store.slogan || '';
        const storeLogo = store.logo || null; // مسار الشعار

        // التحقق من وجود قياسات (تجاهل القيم الفارغة والأصفار)
        // توحيد مصادر القياسات (قد تكون في الفاتورة أو في تفاصيل الفستان)
        const measurements = {
            bust: inv.bust_measurement || item.bust_meas || null,
            waist: inv.waist_measurement || item.waist_meas || null,
            length: inv.dress_length || item.dress_len || null,
            sleeve: inv.sleeve_length || item.sleeve_len || null,
            shoulder: inv.shoulder_length || item.shoulder_len || null,
            other: inv.other_measurements || item.other_meas || null
        };

        // التحقق من وجود قياسات (تجاهل القيم الفارغة والأصفار)
        const hasMeasurements = (
            (measurements.bust && measurements.bust != '0') ||
            (measurements.waist && measurements.waist != '0') ||
            (measurements.length && measurements.length != '0') ||
            (measurements.sleeve && measurements.sleeve != '0') ||
            (measurements.shoulder && measurements.shoulder != '0') ||
            (measurements.other && measurements.other.trim().length > 0)
        );

        // تجهيز HTML الإكسسوارات (بتصميم مونوكروم - مع منع التكرار)
        let accessoriesHtml = '<span style="color:#999; font-style:italic; font-size:12px;">لا توجد إكسسوارات إضافية</span>';

        // دمج الملحقات الصريحة + أي منتجات إضافية (غير الفستان الرئيسي)
        let allAccessories = [...acc];

        // إضافة المنتجات الإضافية (مثل الحذاء، التاج..) إذا كانت محفوظة كمنتجات وليست كإكسسوارات
        if (data.items && Array.isArray(data.items)) {
            data.items.forEach(prod => {
                // تجاوز الفستان الرئيسي (الذي تم عرضه في الجدول)
                // نعتبر الفستان الرئيسي هو الذي يطابق بيانات item المعروضة
                if (prod.item_name !== item.dress_name && prod.id !== item.id) {
                    allAccessories.push(prod.item_name);
                }
            });
        }

        // تنظيف القائمة: إزالة الفراغات + إزالة التكرار
        const uniqueAccessories = [...new Set(allAccessories)]
            .filter(a => a && typeof a === 'string' && a.trim().length > 0);

        if (uniqueAccessories.length > 0) {
            accessoriesHtml = `<div class="tags">${uniqueAccessories.map(a => `<span class="tag"><i class="fas fa-check" style="color:#000; font-size:11px;"></i> ${a}</span>`).join('')}</div>`;
        }

        // تجهيز HTML صندوق القياسات
        let measurementsBoxHtml = '';
        if (hasMeasurements) {
            measurementsBoxHtml = `
                <div class="col-4">
                    <h3 class="section-title"><i class="fas fa-ruler-combined"></i> القياسات</h3>
                    <div class="measurements-grid">
                        <div class="measure-item"><span class="measure-label">الصدر</span><span class="measure-val">${measurements.bust || '-'}</span></div>
                        <div class="measure-item"><span class="measure-label">الخصر</span><span class="measure-val">${measurements.waist || '-'}</span></div>
                        <div class="measure-item"><span class="measure-label">الطول</span><span class="measure-val">${measurements.length || '-'}</span></div>
                        <div class="measure-item"><span class="measure-label">الأكمام</span><span class="measure-val">${measurements.sleeve || '-'}</span></div>
                        <div class="measure-item"><span class="measure-label">الأكتاف</span><span class="measure-val">${measurements.shoulder || '-'}</span></div>
                        ${measurements.other ? `<div class="measure-item" style="grid-column:span 2; text-align:right; display:flex; justify-content:space-between;"><span class="measure-label">أخرى:</span> <span class="measure-val">${measurements.other}</span></div>` : ''}
                    </div>
                </div>
            `;
        }

        // تحديد عرض قسم الفستان بناءً على وجود القياسات
        const dressColClass = hasMeasurements ? 'col-8' : 'col-12';

        return `
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title>فاتورة #${inv.invoice_number}</title>
            ${getStyles()}
        </head>
        <body>
            <div class="invoice-container">
                <header class="header">
                    <div class="print-meta">
                        تاريخ الفاتورة: ${formatDate(inv.invoice_date)}<br>
                        الرقم المرجعي: #${inv.id || 'N/A'}
                    </div>
                    ${storeLogo ? `<img src="${storeLogo}" alt="${storeName}" style="max-height: 100px; margin-bottom: 15px; display: block; margin-left: auto; margin-right: auto;">` : ''}
                    <h1>${storeName}</h1>
                    ${storeSlogan ? `<div class="subtitle">${storeSlogan}</div>` : ''}
                </header>

                <div class="grid-row">
                    <div class="col-6">
                        <h3 class="section-title"><i class="fas fa-file-invoice"></i> تفاصيل الفاتورة</h3>
                        <div class="data-row"><span class="label">رقم الفاتورة:</span><span class="value" style="font-size:15px;">#${inv.invoice_number}</span></div>
                        <div class="data-row"><span class="label">تاريخ الإصدار:</span><span class="value">${formatDate(inv.invoice_date)}</span></div>
                        <div class="data-row"><span class="label">نوع العملية:</span><span class="value">${translateType(inv.operation_type)}</span></div>
                        <div class="data-row"><span class="label">طريقة الدفع:</span><span class="value">${translatePayment(inv.payment_method)}</span></div>
                    </div>
                    <div class="col-6">
                        <h3 class="section-title"><i class="fas fa-user"></i> بيانات العميل</h3>
                        <div class="data-row"><span class="label">الاسم:</span><span class="value" style="font-size:15px;">${inv.customer_name || 'غير محدد'}</span></div>
                        <div class="data-row"><span class="label">جوال 1:</span><span class="value" style="direction:ltr;text-align:right">${inv.phone_1 || '-'}</span></div>
                        <div class="data-row"><span class="label">جوال 2:</span><span class="value" style="direction:ltr;text-align:right">${inv.phone_2 || '-'}</span></div>
                    </div>
                </div>

                <h3 class="section-title" style="border:none; margin-bottom:10px;"><i class="fas fa-calendar-alt"></i> التواريخ </h3>
                <div class="dates-box">
                    <div class="date-item">
                        <span class="date-label">تاريخ المناسبة</span>
                        <span class="date-value highlight">${formatDate(inv.wedding_date)}</span>
                    </div>
                    <div class="date-item">
                        <span class="date-label">تاريخ الاستلام</span>
                        <span class="date-value">${formatDate(inv.collection_date)}</span>
                    </div>
                    <div class="date-item">
                        <span class="date-label">تاريخ الإرجاع</span>
                        <span class="date-value">${formatDate(inv.return_date)}</span>
                    </div>
                </div>

                <div class="grid-row">
                    <div class="${dressColClass}">
                        <h3 class="section-title"><i class="fas fa-person-dress"></i> تفاصيل الفستان</h3>
                        <table class="product-table">
                            <thead>
                                <tr>
                                    <th style="text-align:right"> اسم الفستان</th>
                                    <th>اللون</th>
                                    <th>رقم الفستان</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="text-align:right; font-size:14px;">${item.dress_name || 'غير محدد'}</td>
                                    <td>${item.dress_color || '-'}</td>
                                    <td style="font-family:sans-serif; letter-spacing:1px;">${item.dress_code || '-'}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div style="margin-top: 15px;">
                            <strong style="font-size:12px; display:block; margin-bottom:8px;">الملحقات:</strong>
                            ${accessoriesHtml}
                        </div>
                    </div>
                    ${measurementsBoxHtml}
                </div>

                <div class="grid-row" style="align-items: stretch;">
                    <div class="col-6">
                        <h3 class="section-title"><i class="fas fa-pen"></i> ملاحظات</h3>
                        <div class="notes-content">
                            <p>${inv.notes || 'لا توجد ملاحظات.'}</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="summary-box">
                            <div class="summary-row"><span>السعر الإجمالي</span><span>${formatCurrency(inv.total_price)}</span></div>
                            <div class="summary-row" style="color:var(--text-secondary)"><span>العربون المدفوع</span><span>${formatCurrency(inv.deposit_amount)}</span></div>
                            <div class="summary-row total"><span>المبلغ المتبقي</span><span>${formatCurrency(inv.remaining_balance)}</span></div>
                        </div>
                    </div>
                </div>

                <div class="terms-container">
                    <div class="terms-title">شروط التعاقد:</div>
                    <ul class="terms-list">
                        <li>العربون المدفوع غير قابل للاسترداد في حال إلغاء الطلب من قبل العميل.</li>
                        <li><strong>يحق للعميل طلب تغيير الموديل المختار، وذلك وفقاً للشروط والأحكام المعتمدة في سياسة المحل.</strong></li>
                        <li>استلام الفستان إقرار بسلامته وخلوه من العيوب؛ لا تُقبل أي شكاوى بعد مغادرة المحل.</li>
                        <li>تُحتسب رسوم تأخير يومية قدرها (500) ريال في حال عدم إعادة الفستان في الموعد المتفق عليه.</li>
                        <li>في حال حدوث تلف أو بقع، تُخصم تكلفة الإصلاح الفعلي فقط من مبلغ التأمين المدفوع.</li>
                    </ul>
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
            </div>

            <script>
                // الطباعة التلقائية بعد تحميل المحتوى
                window.onload = function() {
                    setTimeout(function() { window.print(); }, 500);
                };
            </script>
        </body>
        </html>
        `;
    };

    // ==========================================
    // 4. Main Function (دالة التشغيل الرئيسية)
    // ==========================================

    async function printById(id) {
        if (!id) {
            alert('رقم الفاتورة غير صالح');
            return;
        }

        try {
            document.body.style.cursor = 'wait';
            // طلب البيانات من الـ API
            const response = await fetch(`${API_URL}?action=get_invoice&id=${id}`);

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            const result = await response.json();
            document.body.style.cursor = 'default';

            if (result.status === 'success' && result.data) {
                // فتح نافذة طباعة جديدة
                const printWindow = window.open('', `Invoice_${id}`, 'width=1024,height=1200,menubar=no,status=no,titlebar=no,scrollbars=yes');

                if (printWindow) {
                    printWindow.document.open();
                    // توليد الـ HTML بناءً على البيانات المستلمة (بما فيها بيانات المتجر)
                    printWindow.document.write(getLayout(result.data));
                    printWindow.document.close();
                    printWindow.focus();
                    // ملاحظة: أمر الطباعة window.print() موجود داخل الـ HTML المولد في window.onload
                } else {
                    alert('يرجى السماح بالنوافذ المنبثقة (Pop-ups) لتمكين الطباعة.');
                }
            } else {
                throw new Error(result.message || 'فشل جلب بيانات الفاتورة');
            }
        } catch (error) {
            document.body.style.cursor = 'default';
            console.error('Print Error:', error);
            alert('حدث خطأ أثناء محاولة الطباعة: ' + error.message);
        }
    }

    // Public API
    return {
        printById: printById
    };

})();
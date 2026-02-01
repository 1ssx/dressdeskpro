/**
 * Invoice Details Page - Main Script
 * 
 * يتعامل مع:
 * - تحميل تفاصيل الفاتورة من API
 * - عرض البيانات في الواجهة
 * - التحديث التلقائي
 */

// Global invoice data
let currentInvoice = null;

// Load invoice details on page load
document.addEventListener('DOMContentLoaded', function () {
    if (typeof invoiceId !== 'undefined' && invoiceId) {
        loadInvoiceDetails();
    } else {
        showError('معرف الفاتورة غير موجود');
    }
});

/**
 * Load invoice details from API
 */
async function loadInvoiceDetails() {
    try {
        showLoading(true);

        // استخدام مسار نسبي بدلاً من مطلق
        const response = await fetch(`api/invoices.php?action=details&id=${invoiceId}`);

        // التحقق من أن الاستجابة ناجحة (HTTP 200)
        if (!response.ok) {
            throw new Error(`HTTP Error: ${response.status}`);
        }

        // التحقق من نوع المحتوى (يجب أن يكون JSON)
        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            // قراءة النص لمعرفة سبب الخطأ (قد يكون HTML لصفحة 404 أو خطأ PHP)
            const textHTML = await response.text();
            console.error('Non-JSON response:', textHTML);
            throw new Error('الخادم أرجع بيانات غير متوقعة (HTML بدلاً من JSON). تحقق من المسار أو سجلات الخطأ.');
        }

        const data = await response.json();

        if (data.status === 'success') {
            currentInvoice = data.data;
            displayInvoiceDetails(currentInvoice);
            loadPayments();
            showLoading(false);
        } else {
            throw new Error(data.message || 'فشل تحميل الفاتورة');
        }
    } catch (error) {
        console.error('Error loading invoice:', error);
        showError('خطأ في تحميل تفاصيل الفاتورة: ' + error.message);
        showLoading(false);
    }
}

/**
 * Display invoice details in the page
 */
function displayInvoiceDetails(data) {
    const invoice = data.invoice;
    if (!invoice) {
        showError('بيانات الفاتورة غير موجودة');
        return;
    }

    // DEBUG: Log invoice data
    console.log('[InvoiceDetails] Full invoice object:', invoice);
    console.log('[InvoiceDetails] Date fields:', {
        wedding_date: invoice.wedding_date,
        collection_date: invoice.collection_date,
        return_date: invoice.return_date,
        delivered_at: invoice.delivered_at,
        returned_at: invoice.returned_at
    });

    //بيانات الفاتورة الأساسية
    const invoiceNumberEl = document.getElementById('invoice-number');
    const invoiceDateEl = document.getElementById('invoice-date');
    const customerNameEl = document.getElementById('customer-name');

    if (invoiceNumberEl) invoiceNumberEl.textContent = invoice.invoice_number || '-';
    if (invoiceDateEl) invoiceDateEl.textContent = formatDate(invoice.invoice_date) || '-';
    if (customerNameEl) customerNameEl.textContent = invoice.customer_name || '-';

    // حالات الفاتورة
    updateStatusBadge('invoice-status-badge', invoice.invoice_status, getInvoiceStatusText(invoice.invoice_status));
    updateStatusBadge('payment-status-badge', 'payment-' + invoice.payment_status, getPaymentStatusText(invoice.payment_status));

    // تفاصيل إضافية - with null checks
    const operationTypeEl = document.getElementById('operation-type');
    const paymentMethodEl = document.getElementById('payment-method');
    const weddingDateEl = document.getElementById('wedding-date');
    const collectionDateEl = document.getElementById('collection-date');
    const returnDateEl = document.getElementById('return-date');
    const deliveredAtEl = document.getElementById('delivered-at');
    const returnedAtEl = document.getElementById('returned-at');
    const returnConditionEl = document.getElementById('return-condition');
    const totalPriceEl = document.getElementById('total-price');

    if (operationTypeEl) operationTypeEl.textContent = getOperationTypeText(invoice.operation_type) || '-';
    if (paymentMethodEl) paymentMethodEl.textContent = getPaymentMethodText(invoice.payment_method) || '-';

    // FIX: Properly handle date formatting with explicit checks
    if (weddingDateEl) {
        const formattedWeddingDate = formatDate(invoice.wedding_date);
        weddingDateEl.textContent = formattedWeddingDate || '-';
    }

    if (collectionDateEl) {
        const formattedCollectionDate = formatDate(invoice.collection_date);
        collectionDateEl.textContent = formattedCollectionDate || '-';
    }

    if (returnDateEl) {
        const formattedReturnDate = formatDate(invoice.return_date);
        returnDateEl.textContent = formattedReturnDate || '-';
    }

    if (deliveredAtEl) {
        const formattedDeliveredAt = formatDateTime(invoice.delivered_at);
        deliveredAtEl.textContent = formattedDeliveredAt || '-';
    }

    if (returnedAtEl) {
        const formattedReturnedAt = formatDateTime(invoice.returned_at);
        returnedAtEl.textContent = formattedReturnedAt || '-';
    }

    if (returnConditionEl) {
        returnConditionEl.textContent = invoice.return_condition ? getReturnConditionText(invoice.return_condition) : '-';
    }

    // العناصر
    displayItems(data.items || []);

    // المبالغ
    if (totalPriceEl) totalPriceEl.textContent = formatCurrency(invoice.total_price);

    // Timeline
    displayTimeline(data.status_history || []);

    // تحديث حالة الأزرار
    updateActionButtons(invoice.invoice_status);
}

/**
 * Display invoice items
 */
function displayItems(items) {
    const tbody = document.querySelector('#items-table tbody');

    if (!items || items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4">لا توجد عناصر</td></tr>';
        return;
    }

    let html = '';
    items.forEach(item => {
        html += `
            <tr>
                <td>${item.item_name || '-'}</td>
                <td>${item.item_code || '-'}</td>
                <td>${item.quantity || 1}</td>
                <td>${formatCurrency(item.unit_price)}</td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

/**
 * Display timeline/history
 */
function displayTimeline(history) {
    const container = document.getElementById('timeline-container');

    if (!history || history.length === 0) {
        container.innerHTML = '<div style="text-align: center; color: #7f8c8d; padding: 20px;">لا يوجد سجل تغييرات</div>';
        return;
    }

    let html = '';
    history.forEach(item => {
        const statusText = item.status_to ? getInvoiceStatusText(item.status_to) : null;
        const paymentText = item.payment_status_to ? getPaymentStatusText(item.payment_status_to) : null;

        // Date formatting
        const dateObj = new Date(item.changed_at);
        const dateStr = dateObj.toLocaleDateString('en-GB');
        const timeStr = dateObj.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });

        html += `
            <div class="timeline-item">
                <div class="timeline-marker"></div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <div class="timeline-badges">
                            ${statusText ? `<span class="status-badge status-${item.status_to}">${statusText}</span>` : ''}
                            ${paymentText ? `<span class="status-badge payment-${item.payment_status_to}">${paymentText}</span>` : ''}
                            ${!statusText && !paymentText ? '<span class="status-badge status-draft">تحديث</span>' : ''}
                        </div>
                        <span class="timeline-time">${dateStr} - ${timeStr}</span>
                    </div>
                    <div class="timeline-meta">
                         <i class="fas fa-user-circle"></i> ${item.changed_by_name || 'النظام'}
                    </div>
                    ${item.notes ? `<div class="timeline-notes">${item.notes}</div>` : ''}
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

/**
 * Update action buttons based on invoice status
 */
function updateActionButtons(status) {
    const btnDeliver = document.getElementById('btn-deliver');
    const btnReturn = document.getElementById('btn-return');
    const btnClose = document.getElementById('btn-close');
    const btnCancel = document.getElementById('btn-cancel');

    // تعطيل الأزرار بناءً على الحالة
    if (status === 'closed' || status === 'canceled') {
        btnDeliver.disabled = true;
        btnReturn.disabled = true;
        btnClose.disabled = true;
        btnCancel.disabled = true;
    } else {
        // تسليم: فقط للحالات reserved
        btnDeliver.disabled = status !== 'reserved';

        // إرجاع: فقط للحالات out_with_customer
        btnReturn.disabled = status !== 'out_with_customer';

        // إقفال: للحالات returned
        btnClose.disabled = status !== 'returned';

        // إلغاء: لجميع الحالات ما عدا closed
        btnCancel.disabled = status === 'closed';
    }
}

/**
 * Update status badge
 */
function updateStatusBadge(elementId, cssClass, text) {
    const badge = document.getElementById(elementId);
    badge.className = 'status-badge status-' + cssClass.replace('status-', '');
    badge.textContent = text;
}

/**
 * Formatting functions
 */
function formatDate(dateString) {
    if (!dateString || dateString === '0000-00-00' || dateString === 'null') {
        console.log('[InvoiceDetails] formatDate: empty/null date received:', dateString);
        return null; // Return null instead of empty string
    }
    try {
        const date = new Date(dateString);
        // Check if date is valid
        if (isNaN(date.getTime())) {
            console.log('[InvoiceDetails] formatDate: invalid date:', dateString);
            return null;
        }
        const formatted = date.toLocaleDateString('en-GB'); // DD/MM/YYYY with English digits
        console.log('[InvoiceDetails] formatDate: formatted', dateString, '→', formatted);
        return formatted;
    } catch (e) {
        console.error('Date formatting error:', e, dateString);
        return null;
    }
}

function formatDateTime(dateString) {
    if (!dateString || dateString === '0000-00-00 00:00:00' || dateString === 'null') {
        console.log('[InvoiceDetails] formatDateTime: empty/null datetime received:', dateString);
        return null;
    }
    try {
        const date = new Date(dateString);
        // Check if date is valid
        if (isNaN(date.getTime())) {
            console.log('[InvoiceDetails] formatDateTime: invalid datetime:', dateString);
            return null;
        }
        const formatted = date.toLocaleString('en-GB', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
        console.log('[InvoiceDetails] formatDateTime: formatted', dateString, '→', formatted);
        return formatted;
    } catch (e) {
        console.error('DateTime formatting error:', e, dateString);
        return null;
    }
}

function formatCurrency(amount) {
    return parseFloat(amount || 0).toFixed(2) + ' ريال';
}

/**
 * Text mapping functions
 */
function getInvoiceStatusText(status) {
    const map = {
        'draft': 'مسودة',
        'reserved': 'محجوز',
        'out_with_customer': 'مع العميل',
        'returned': 'مرتجع',
        'closed': 'مقفلة',
        'canceled': 'ملغاة'
    };
    return map[status] || status;
}

function getPaymentStatusText(status) {
    const map = {
        'paid': 'مدفوع بالكامل',
        'partial': 'مدفوع جزئياً',
        'unpaid': 'غير مدفوع'
    };
    return map[status] || status;
}

function getOperationTypeText(type) {
    const map = {
        'sale': 'بيع',
        'rent': 'إيجار',
        'design': 'تصميم',
        'design-sale': 'تصميم وبيع',
        'design-rent': 'تصميم وإيجار'
    };
    return map[type] || type;
}

function getPaymentMethodText(method) {
    const map = {
        'cash': 'نقدي',
        'card': 'بطاقة',
        'transfer': 'تحويل',
        'mixed': 'مختلط'
    };
    return map[method] || method;
}

function getReturnConditionText(condition) {
    const map = {
        'excellent': 'ممتاز',
        'good': 'جيد',
        'needs_cleaning': 'يحتاج تنظيف',
        'damaged': 'متضرر',
        'missing_items': 'ناقص ملحقات'
    };
    return map[condition] || condition;
}

/**
 * Update action buttons based on invoice status
 * تفعيل/تعطيل الأزرار حسب حالة الفاتورة
 */
function updateActionButtons(status) {
    const btnDeliver = document.getElementById('btn-deliver');
    const btnReturn = document.getElementById('btn-return');
    const btnClose = document.getElementById('btn-close');
    const btnCancel = document.getElementById('btn-cancel');

    // Default: disable all
    if (btnDeliver) btnDeliver.disabled = true;
    if (btnReturn) btnReturn.disabled = true;
    if (btnClose) btnClose.disabled = true;
    if (btnCancel) btnCancel.disabled = true;

    // Enable based on status
    switch (status) {
        case 'draft':
            // مسودة: يمكن الإلغاء فقط
            if (btnCancel) btnCancel.disabled = false;
            break;

        case 'reserved':
            // محجوز: يمكن التسليم أو الإلغاء
            if (btnDeliver) btnDeliver.disabled = false;
            if (btnCancel) btnCancel.disabled = false;
            break;

        case 'out_with_customer':
            // مع العميل: يمكن الإرجاع فقط
            if (btnReturn) btnReturn.disabled = false;
            break;

        case 'returned':
            // تم الإرجاع: يمكن الإقفال
            if (btnClose) btnClose.disabled = false;
            break;

        case 'closed':
        case 'canceled':
            // مقفلة أو ملغاة: لا يمكن فعل أي شيء
            break;

        default:
            // حالة غير معروفة: السماح بالإلغاء فقط
            if (btnCancel) btnCancel.disabled = false;
    }

    // Add visual indication for disabled buttons
    [btnDeliver, btnReturn, btnClose, btnCancel].forEach(btn => {
        if (btn) {
            if (btn.disabled) {
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
            } else {
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            }
        }
    });
}



/**
 * Show/hide loading state
 */
function showLoading(show) {
    document.getElementById('loading-state').style.display = show ? 'block' : 'none';
    document.getElementById('invoice-content').style.display = show ? 'none' : 'block';
}

/**
 * Show success message
 */
function showSuccess(message) {
    const container = document.getElementById('alert-container');
    container.innerHTML = `
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> ${message}
        </div>
    `;
    setTimeout(() => {
        container.innerHTML = '';
    }, 5000);
}

/**
 * Show error message
 */
function showError(message) {
    const container = document.getElementById('alert-container');
    container.innerHTML = `
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> ${message}
        </div>
    `;
}

/**
 * Show warning message
 */
function showWarning(message) {
    const container = document.getElementById('alert-container');
    container.innerHTML = `
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> ${message}
        </div>
    `;
}

/**
 * Refresh page data
 */
function refreshData() {
    loadInvoiceDetails();
}

// Export for use in other scripts
window.invoiceDetailsUtils = {
    showSuccess,
    showError,
    showWarning,
    refreshData,
    formatCurrency,
    formatDate,
    formatDateTime
};

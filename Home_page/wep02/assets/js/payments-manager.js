/**
 * Payments Manager
 * 
 * يتعامل مع:
 * - عرض المدفوعات
 * - إضافة دفعات جديدة
 * - تحديث الملخص المالي
 */

/**
 * Load payments for current invoice
 */
async function loadPayments() {
    try {
        // استخدام مسار نسبي بدلاً من مطلق
        const response = await fetch(`api/payments.php?action=summary&invoice_id=${invoiceId}`);

        // التحقق من نجاح الاستجابة
        if (!response.ok) {
            throw new Error(`HTTP Error: ${response.status}`);
        }

        // التحقق من أن الرد JSON
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response:', text);
            throw new Error('Response is not JSON');
        }

        const data = await response.json();

        if (data.status === 'success') {
            displayPaymentSummary(data.data);
            loadPaymentsList();
        } else {
            console.error('Failed to load payment summary:', data.message);
            if (window.invoiceDetailsUtils) {
                window.invoiceDetailsUtils.showError('فشل تحميل ملخص المدفوعات: ' + data.message);
            }
        }
    } catch (error) {
        console.error('Error loading payments:', error);
        if (window.invoiceDetailsUtils) {
            window.invoiceDetailsUtils.showError('خطأ في تحميل المدفوعات: ' + error.message);
        }
    }
}

/**
 * Load payments list
 */
async function loadPaymentsList() {
    try {
        // استخدام مسار نسبي بدلاً من مطلق
        const response = await fetch(`api/payments.php?action=list&invoice_id=${invoiceId}`);

        // التحقق من نجاح الاستجابة
        if (!response.ok) {
            throw new Error(`HTTP Error: ${response.status}`);
        }

        // التحقق من أن الرد JSON
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response:', text);
            throw new Error('Response is not JSON');
        }

        const data = await response.json();

        if (data.status === 'success') {
            displayPaymentsList(data.data.payments || []);
        }
    } catch (error) {
        console.error('Error loading payments list:', error);
    }
}

/**
 * Display payment summary
 */
function displayPaymentSummary(summary) {
    document.getElementById('total-paid').textContent = formatCurrency(summary.net_paid || 0);
    document.getElementById('remaining-balance').textContent = formatCurrency(summary.remaining_balance || 0);
}

/**
 * Display payments list in table
 */
function displayPaymentsList(payments) {
    const tbody = document.querySelector('#payments-table tbody');

    if (!payments || payments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4">لا توجد مدفوعات</td></tr>';
        return;
    }

    let html = '';
    payments.forEach(payment => {
        const typeClass = payment.type === 'refund' ? 'color: #e74c3c;' :
            payment.type === 'penalty' ? 'color: #f39c12;' : '';
        const typeSymbol = payment.type === 'refund' ? '-' : '+';

        // علامة للعربون القديم
        const isLegacy = payment.is_legacy || payment.id === 'legacy_deposit';
        const legacyBadge = isLegacy ? ' <span style="background:#f0f0f0;padding:2px 6px;border-radius:4px;font-size:0.75rem;color:#888;">قديم</span>' : '';

        html += `
            <tr${isLegacy ? ' style="background:#fafafa;"' : ''}>
                <td>${formatDateTime(payment.payment_date)}</td>
                <td style="${typeClass}">
                    ${typeSymbol} ${formatCurrency(payment.amount)}
                </td>
                <td>${getPaymentMethodText(payment.method)}</td>
                <td>${getPaymentTypeText(payment.type)}${legacyBadge}</td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

/**
 * Open add payment modal
 */
function openAddPaymentModal() {
    document.getElementById('add-payment-modal').style.display = 'block';
    document.getElementById('add-payment-form').reset();
    // تعيين النوع الافتراضي إلى payment
    document.getElementById('payment-type').value = 'payment';
}

/**
 * Close add payment modal
 */
function closeAddPaymentModal() {
    document.getElementById('add-payment-modal').style.display = 'none';
    document.getElementById('add-payment-form').reset();
}

/**
 * Handle add payment form submit
 */
document.addEventListener('DOMContentLoaded', function () {
    const addPaymentForm = document.getElementById('add-payment-form');
    if (addPaymentForm) {
        addPaymentForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const amount = parseFloat(document.getElementById('payment-amount').value);
            const method = document.getElementById('payment-method-input').value;
            const type = document.getElementById('payment-type').value;
            const notes = document.getElementById('payment-notes').value;

            if (!amount || amount <= 0) {
                window.invoiceDetailsUtils.showError('المبلغ يجب أن يكون أكبر من صفر');
                return;
            }

            try {
                // استخدام مسار نسبي بدلاً من مطلق
                const response = await fetch('api/payments.php?action=add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        invoice_id: invoiceId,
                        amount: amount,
                        method: method,
                        type: type,
                        notes: notes || null
                    })
                });

                // التحقق من نجاح الاستجابة
                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status}`);
                }

                // التحقق من أن الرد JSON
                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response on add payment:', text);
                    throw new Error('الخادم أرجع بيانات غير صالحة');
                }

                const data = await response.json();

                if (data.status === 'success') {
                    closeAddPaymentModal();
                    window.invoiceDetailsUtils.showSuccess('تم إضافة الدفعة بنجاح');

                    // إعادة تحميل البيانات
                    setTimeout(() => {
                        loadPayments();
                        window.invoiceDetailsUtils.refreshData();
                    }, 1000);
                } else {
                    throw new Error(data.message || 'فشل إضافة الدفعة');
                }
            } catch (error) {
                console.error('Add payment error:', error);
                window.invoiceDetailsUtils.showError('خطأ: ' + error.message);
            }
        });
    }
});

/**
 * Get payment type text in Arabic
 */
function getPaymentTypeText(type) {
    const map = {
        'payment': 'دفعة',
        'refund': 'مرتجع',
        'penalty': 'غرامة'
    };
    return map[type] || type;
}

/**
 * Handle modal close on outside click
 */
window.addEventListener('click', function (event) {
    const modal = document.getElementById('add-payment-modal');
    if (event.target === modal) {
        closeAddPaymentModal();
    }
});

// Helper functions (reuse from invoice-details.js)
function formatCurrency(amount) {
    return parseFloat(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ريال';
}

function formatDateTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('en-GB');
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

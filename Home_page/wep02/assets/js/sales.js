/**
 * Sales Page JavaScript - V2 Schema Compatible
 * Fixed Scope and Initialization
 */

//    // --- Configuration ---
const API_URL = 'invoices.php';
// api-client API_BASE_URL='api/', so 'invoices.php' -> 'api/invoices.php'
// Previous files used 'api/invoices.php' explicitly when using fetch?
// Let's check sales.js content. It used 'api/invoices.php'.
// If api-client adds 'api/', then 'invoices.php' is correct.

// --- State ---
let currentStatusFilter = 'all';

// 2. Define Dashboard Logic Globally
window.SalesDashboard = {
    currentStatsPeriod: 'daily',

    setStatsPeriod: function (period) {
        console.log(`🔄 Switching stats period to: ${period}`);
        this.currentStatsPeriod = period;

        // Visual Update: Swap styles so active is Primary, inactive is Secondary
        const buttons = document.querySelectorAll('.stats-controls button');
        buttons.forEach(btn => {
            btn.classList.remove('active', 'primary');
            btn.classList.add('secondary'); // Reset all to secondary
        });

        const activeBtn = document.getElementById(`stats-${period}-btn`);
        if (activeBtn) {
            activeBtn.classList.remove('secondary');
            activeBtn.classList.add('primary', 'active'); // Make active one primary
        }

        // Logic Update
        this.loadStatistics(period);
    },

    loadStatistics: async function (period) {
        console.log(`📊 Loading statistics (${period})...`);

        try {
            const stats = await apiGet(API_URL, { action: 'stats', period: period });

            // Assuming the new stats structure from the provided loadStats snippet
            // and mapping to existing dashboard elements.
            // Note: The provided `loadStats` snippet uses different IDs than the original `updateText` calls.
            // I'm adapting to the original IDs for now, but if the HTML changed, these might need adjustment.
            updateText('stat-today-total', formatCurrency(stats.total_sales || 0));
            updateText('stat-today-count', (stats.total_invoices || 0) + ' فاتورة');
            updateText('stat-today-avg', formatCurrency(stats.today_avg || stats.average_invoice || 0));
            updateText('stat-revenue', formatCurrency(stats.total_revenue || 0));
            console.log('✅ Statistics loaded successfully for period:', period);

            // The provided loadStats snippet also had these, which might be for a different dashboard section
            if (document.getElementById('total-sales'))
                document.getElementById('total-sales').textContent = formatCurrency(stats.total_sales || 0);

            if (document.getElementById('invoices-count'))
                document.getElementById('invoices-count').textContent = stats.count || 0;

            if (document.getElementById('paid-invoices'))
                document.getElementById('paid-invoices').textContent = stats.paid_count || 0;

            if (document.getElementById('debts-amount'))
                document.getElementById('debts-amount').textContent = formatCurrency(stats.debts || 0);

        } catch (error) {
            console.error('❌ Failed to load statistics:', error);
            this.showErrorState();
        }
    },

    showErrorState: function () {
        updateText('stat-today-total', 'خطأ');
        updateText('stat-today-count', 'خطأ');
        updateText('stat-today-avg', 'خطأ');
        updateText('stat-revenue', 'خطأ');
    }
};

// 3. Define Table Logic Globally
async function loadInvoicesTable() {
    console.log('📋 Loading invoices table...');

    const tbody = document.getElementById('invoices-table-body');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">جارِ التحميل...</td></tr>';
    }

    try {
        const params = {
            action: 'list',
            // Assuming these variables exist or are passed as arguments if needed
            // page: currentPage,
            // limit: itemsPerPage,
            // search: searchInput ? searchInput.value : '',
            // date_from: dateFromInput ? dateFromInput.value : '',
            // date_to: dateToInput ? dateToInput.value : '',
            // status: statusFilter ? statusFilter.value : ''
        };

        if (currentStatusFilter && currentStatusFilter !== 'all') {
            params.invoice_status = currentStatusFilter;
        }

        const result = await apiGet(API_URL, params);

        // apiGet unwraps data.data if present.
        // If API returns { status: 'success', data: { invoices: [], total: 0 } }
        // Then data is { invoices: [], total: 0 }

        const invoices = result.data || []; // Assuming apiGet returns { data: [...] } or just [...]
        // const total = result.total || 0; // If pagination is implemented
        // const pages = result.pages || 1; // If pagination is implemented

        // allInvoices = invoices; // If global storage is needed
        if (tbody) {
            tbody.innerHTML = ''; // Clear loading message
            if (invoices.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;">لا توجد فواتير مسجلة</td></tr>';
            } else {
                renderTable(invoices);
            }
        }
        // renderPagination(total, currentPage, pages); // If pagination is implemented

    } catch (error) {
        console.error('❌ Failed to load invoices:', error);
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; color:red">فشل تحميل الفواتير: ${error.message || 'خطأ غير معروف'}</td></tr>`;
        }
    }
}

function renderTable(invoices) {
    const tableBody = document.getElementById('invoices-table-body');
    if (!tableBody) return;

    invoices.forEach(invoice => {
        const tr = document.createElement('tr');

        // Status Texts
        const invoiceStatusText = getInvoiceStatusText(invoice.invoice_status || 'reserved');
        const paymentStatusText = getPaymentStatusText(invoice.payment_status || 'unpaid');

        // Data Formatting
        const customerName = invoice.customer_name || 'غير محدد';
        const invoiceNumber = (invoice.invoice_number && invoice.invoice_number !== 'undefined') ? invoice.invoice_number : 'N/A';
        const invoiceDate = formatDate(invoice.invoice_date);

        tr.innerHTML = `
            <td>${escapeHtml(invoiceNumber)}</td>
            <td dir="ltr" style="text-align:right">${escapeHtml(invoiceDate)}</td>
            <td>${escapeHtml(customerName)}</td>
            <td>${formatCurrency(invoice.total_price)}</td>
            <td>${formatCurrency(invoice.deposit_amount)}</td>
            <td>${formatCurrency(invoice.remaining_balance)}</td>
            <td><span class="status-badge status-${invoice.invoice_status || 'reserved'}">${invoiceStatusText}</span></td>
            <td><span class="status-badge payment-${invoice.payment_status || 'unpaid'}">${paymentStatusText}</span></td>
            <td>
                <button class="btn-action details" onclick="viewInvoiceDetails(${invoice.id})" title="تفاصيل"><i class="fas fa-eye"></i></button>
                <button class="btn-action edit" onclick="editInvoice(${invoice.id})" title="تعديل"><i class="fas fa-edit"></i></button>
                <button class="btn-action cancel" onclick="cancelInvoice(${invoice.id})" title="إلغاء"><i class="fas fa-ban"></i></button>
                <button class="btn-action view" onclick="PrintInvoice.printById(${invoice.id})" title="طباعة"><i class="fas fa-print"></i></button>
                <button class="btn-action whatsapp" onclick="sendWhatsAppMessage(${invoice.id})" title="إرسال واتساب" style="color: #25D366;"><i class="fab fa-whatsapp"></i></button>
                <button class="btn-action delete" onclick="deleteInvoice(${invoice.id})" title="حذف"><i class="fas fa-trash"></i></button>
            </td>
        `;
        tableBody.appendChild(tr);
    });
}

// 4. Initialization
document.addEventListener('DOMContentLoaded', function () {
    console.log('🚀 Sales.js loaded (Global Scop Fix)');

    // Initial Load
    SalesDashboard.loadStatistics('daily');
    loadInvoicesTable();
    setupStatusFilters();

    // Search
    const searchInput = document.getElementById('invoice-search');
    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            filterInvoices(this.value.toLowerCase());
        });
    }
});

// 5. Helpers & Global Actions
function updateText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'SAR' }).format(amount || 0).replace('SAR', 'ر.س');
}

function formatDate(dateString) {
    if (!dateString) return '';
    try {
        return new Date(dateString).toLocaleDateString('en-GB', {
            year: 'numeric', month: '2-digit', day: '2-digit'
        });
    } catch (e) { return dateString; }
}

function escapeHtml(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

function getInvoiceStatusText(status) {
    const map = {
        'draft': 'مسودة', 'reserved': 'محجوز', 'out_with_customer': 'مع العميل',
        'returned': 'تم الاسترجاع', 'closed': 'مقفلة', 'canceled': 'ملغاة'
    };
    return map[status] || status;
}

function getPaymentStatusText(status) {
    const map = { 'paid': 'مدفوع', 'partial': 'جزئي', 'unpaid': 'غير مدفوع' };
    return map[status] || status;
}

function setupStatusFilters() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentStatusFilter = this.dataset.status;
            loadInvoicesTable();
        });
    });
}

function filterInvoices(keyword) {
    document.querySelectorAll('#invoices-table-body tr').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(keyword) ? '' : 'none';
    });
}

// Global Window Actions (for onclick handlers)
window.viewInvoiceDetails = id => window.location.href = `invoice_details.php?invoice_id=${id}`;
window.editInvoice = id => window.location.href = `new-invoice.php?id=${id}`;

window.cancelInvoice = async (id) => {
    const reason = prompt('يرجى إدخال سبب الإلغاء:');
    if (!reason || !reason.trim()) return alert('يجب إدخال سبب الإلغاء');
    if (!confirm('هل أنت متأكد من إلغاء هذه الفاتورة؟\nسيتم نقلها إلى الأرشيف.')) return;

    try {
        const data = await apiPost(API_URL, { action: 'cancel', invoice_id: id, reason: reason });
        if (data.status === 'success') { // Assuming apiPost might still return a status field
            alert('تم إلغاء الفاتورة بنجاح');
            loadInvoicesTable();
            SalesDashboard.loadStatistics(SalesDashboard.currentStatsPeriod);
        } else {
            alert('خطأ: ' + (data.message || 'حدث خطأ'));
        }
    } catch (err) {
        console.error('Cancel Error:', err);
        alert('حدث خطأ في الاتصال: ' + err.message);
    }
};

window.deleteInvoice = async function (id) {
    if (confirm('هل أنت متأكد من حذف هذه الفاتورة؟ لا يمكن التراجع عن هذا الإجراء.')) {
        try {
            await apiPost(API_URL, { action: 'delete', id: id });
            alert('تم حذف الفاتورة بنجاح');
            loadInvoicesTable();
            SalesDashboard.loadStatistics(SalesDashboard.currentStatsPeriod);
        } catch (error) {
            console.error('Delete error:', error);
            // Assuming handleApiError is a global function or needs to be defined
            alert('فشل الحذف: ' + (error.message || 'خطأ غير معروف'));
        }
    }
};

window.sendWhatsAppMessage = async (id) => {
    const sendOption = confirm('هل تريد إرسال صورة الفاتورة عبر واتساب؟\n\nاضغط "موافق" لإرسال صورة الفاتورة\nاضغط "إلغاء" لإرسال رسالة نصية فقط');

    if (sendOption === null) return; // User closed dialog

    // If user wants text only, send text
    if (!sendOption) {
        fallbackToTextMessage(id);
        return;
    }

    // Check if InvoiceImageSender is available
    if (typeof InvoiceImageSender !== 'undefined' && InvoiceImageSender.send) {
        try {
            // Show loading indicator
            const originalBtn = event?.target?.closest ? event.target.closest('button') : null;
            if (originalBtn) {
                originalBtn.disabled = true;
                originalBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }

            console.log('🚀 Starting invoice image generation for ID:', id);
            const result = await InvoiceImageSender.send(id);

            // Restore button
            if (originalBtn) {
                originalBtn.disabled = false;
                originalBtn.innerHTML = '<i class="fab fa-whatsapp"></i>';
            }

            if (result.success) {
                alert('✅ ' + result.message);
            } else {
                // Show error and ask if user wants to try text-only
                console.error('Image send failed:', result.message);
                const tryText = confirm('❌ فشل إرسال الصورة: ' + result.message + '\n\nهل تريد إرسال رسالة نصية بدلاً من ذلك؟');
                if (tryText) {
                    fallbackToTextMessage(id);
                }
            }
        } catch (error) {
            console.error('Image send error:', error);
            const tryText = confirm('❌ حدث خطأ: ' + error.message + '\n\nهل تريد إرسال رسالة نصية بدلاً من ذلك؟');
            if (tryText) {
                fallbackToTextMessage(id);
            }
        }
    } else {
        // InvoiceImageSender not loaded, show error and fallback
        console.warn('InvoiceImageSender not loaded');
        const tryText = confirm('⚠️ نظام إرسال الصور غير متوفر حالياً.\n\nهل تريد إرسال رسالة نصية بدلاً من ذلك؟');
        if (tryText) {
            fallbackToTextMessage(id);
        }
    }
};

// Fallback: Send text-only WhatsApp message
function fallbackToTextMessage(id) {
    console.log('📝 Sending text-only message for invoice ID:', id);
    fetch(`${API_URL}?action=send_invoice_message&id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                alert('✅ تم إرسال الرسالة النصية بنجاح');
            } else {
                alert('❌ فشل الإرسال: ' + (data.message || 'خطأ غير معروف'));
            }
        })
        .catch(err => alert('❌ فشل الاتصال: ' + err.message));
}

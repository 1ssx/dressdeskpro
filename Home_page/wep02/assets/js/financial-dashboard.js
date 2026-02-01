/**
 * Financial Dashboard JavaScript
 * Handles all financial data visualization and interactions
 * 
 * Features:
 * - Real-time KPI updates
 * - Interactive charts (Chart.js)
 * - Date range filtering
 * - Transaction log
 * - Export functionality
 */

document.addEventListener('DOMContentLoaded', function () {

    // ============================================================
    // CONFIGURATION
    // ============================================================

    const API_URL = 'api/financial_dashboard.php';
    const REFRESH_INTERVAL = 60000; // 1 minute

    // Chart instances
    let revenueExpenseChart = null;
    let paymentMethodChart = null;
    let expenseCategoryChart = null;
    let profitTrendChart = null;

    // Current date range
    let currentDateRange = {
        from: new Date().toISOString().split('T')[0],
        to: new Date().toISOString().split('T')[0]
    };

    // Chart colors
    const COLORS = {
        green: 'rgb(16, 185, 129)',
        greenLight: 'rgba(16, 185, 129, 0.1)',
        red: 'rgb(239, 68, 68)',
        redLight: 'rgba(239, 68, 68, 0.1)',
        blue: 'rgb(59, 130, 246)',
        blueLight: 'rgba(59, 130, 246, 0.1)',
        yellow: 'rgb(245, 158, 11)',
        purple: 'rgb(139, 92, 246)',
        gray: 'rgb(107, 114, 128)'
    };

    const PIE_COLORS = [
        COLORS.green,
        COLORS.blue,
        COLORS.purple,
        COLORS.yellow,
        COLORS.red,
        '#06b6d4',
        '#ec4899',
        '#84cc16'
    ];

    // ============================================================
    // INITIALIZATION
    // ============================================================

    init();

    function init() {
        setupEventListeners();
        loadDashboardData();

        // Auto-refresh
        setInterval(loadDashboardData, REFRESH_INTERVAL);
    }

    // ============================================================
    // EVENT LISTENERS
    // ============================================================

    function setupEventListeners() {
        // Quick filter buttons
        document.querySelectorAll('.quick-filter').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.quick-filter').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const range = this.dataset.range;
                handleQuickFilter(range);
            });
        });

        // Custom date range
        document.getElementById('apply-custom-range')?.addEventListener('click', applyCustomDateRange);

        // Transaction type filter
        document.getElementById('transaction-type-filter')?.addEventListener('change', filterTransactions);

        // Export buttons
        document.getElementById('export-excel-btn')?.addEventListener('click', exportToExcel);
        document.getElementById('export-pdf-btn')?.addEventListener('click', exportToPDF);
    }

    // ============================================================
    // DATE RANGE HANDLING
    // ============================================================

    function handleQuickFilter(range) {
        const today = new Date();
        let from, to;

        const customRangeEl = document.getElementById('custom-date-range');

        switch (range) {
            case 'today':
                from = to = formatDate(today);
                customRangeEl.style.display = 'none';
                break;

            case 'week':
                const weekStart = new Date(today);
                weekStart.setDate(today.getDate() - today.getDay());
                from = formatDate(weekStart);
                to = formatDate(today);
                customRangeEl.style.display = 'none';
                break;

            case 'month':
                from = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                to = formatDate(today);
                customRangeEl.style.display = 'none';
                break;

            case 'quarter':
                const quarterStart = new Date(today.getFullYear(), Math.floor(today.getMonth() / 3) * 3, 1);
                from = quarterStart.toISOString().split('T')[0];
                to = formatDate(today);
                customRangeEl.style.display = 'none';
                break;

            case 'custom':
                customRangeEl.style.display = 'flex';
                return; // Don't load data yet
        }

        currentDateRange = { from, to };
        loadDashboardData();
    }

    function applyCustomDateRange() {
        const from = document.getElementById('filter-date-from').value;
        const to = document.getElementById('filter-date-to').value;

        if (!from || !to) {
            alert('الرجاء تحديد تاريخ البداية والنهاية');
            return;
        }

        if (from > to) {
            alert('تاريخ البداية يجب أن يكون قبل تاريخ النهاية');
            return;
        }

        currentDateRange = { from, to };
        loadDashboardData();
    }

    function formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    // ============================================================
    // DATA LOADING
    // ============================================================

    async function loadDashboardData() {
        try {
            const url = `${API_URL}?action=dashboard&date_from=${currentDateRange.from}&date_to=${currentDateRange.to}`;
            const response = await fetch(url);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (result.status !== 'success') {
                throw new Error(result.message || 'Failed to load data');
            }

            const data = result.data;

            // Update all UI components
            updateKPICards(data.summary);
            updateRevenueExpenseChart(data.daily_breakdown);
            updatePaymentMethodChart(data.revenue_by_method);
            updateExpenseCategoryChart(data.expenses_by_category);
            updateProfitTrendChart(data.daily_breakdown);
            updateTransactionsTable(data.transactions);
            updateDebtsTable(data.unpaid_invoices);

            console.log('✅ Dashboard data loaded successfully');

        } catch (error) {
            console.error('❌ Failed to load dashboard data:', error);
            showError('فشل في تحميل البيانات. الرجاء المحاولة مرة أخرى.');
        }
    }

    // ============================================================
    // KPI CARDS UPDATE
    // ============================================================

    function updateKPICards(summary) {
        // Revenue
        document.getElementById('kpi-revenue').textContent = formatCurrency(summary.total_revenue);
        document.getElementById('kpi-revenue-detail').textContent =
            `${summary.payments_count} دفعة + ${summary.invoices_count} فاتورة`;

        // Expenses
        document.getElementById('kpi-expenses').textContent = formatCurrency(summary.total_expenses);
        document.getElementById('kpi-expenses-detail').textContent =
            `${summary.expenses_count} مصروف`;

        // Profit
        const profit = summary.net_profit;
        document.getElementById('kpi-profit').textContent = formatCurrency(profit);
        document.getElementById('kpi-profit').style.color = profit >= 0 ? COLORS.green : COLORS.red;

        const profitTrend = document.getElementById('kpi-profit-trend');
        if (profit > 0) {
            profitTrend.className = 'kpi-trend up';
            profitTrend.innerHTML = '<i class="fas fa-arrow-up"></i>';
        } else if (profit < 0) {
            profitTrend.className = 'kpi-trend down';
            profitTrend.innerHTML = '<i class="fas fa-arrow-down"></i>';
        } else {
            profitTrend.innerHTML = '<i class="fas fa-equals"></i>';
        }

        // Outstanding Debts
        document.getElementById('kpi-debts').textContent = formatCurrency(summary.outstanding_debts);
    }

    // ============================================================
    // CHART UPDATES
    // ============================================================

    function updateRevenueExpenseChart(dailyData) {
        const ctx = document.getElementById('revenue-expense-chart');
        if (!ctx) return;

        const labels = dailyData.map(d => formatDateLabel(d.date));
        const revenues = dailyData.map(d => d.revenue);
        const expenses = dailyData.map(d => d.expenses);

        if (revenueExpenseChart) {
            revenueExpenseChart.data.labels = labels;
            revenueExpenseChart.data.datasets[0].data = revenues;
            revenueExpenseChart.data.datasets[1].data = expenses;
            revenueExpenseChart.update();
        } else {
            revenueExpenseChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'الإيرادات',
                            data: revenues,
                            backgroundColor: COLORS.green,
                            borderRadius: 6,
                            maxBarThickness: 40
                        },
                        {
                            label: 'المصروفات',
                            data: expenses,
                            backgroundColor: COLORS.red,
                            borderRadius: 6,
                            maxBarThickness: 40
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => `${ctx.dataset.label}: ${formatCurrency(ctx.raw)}`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => formatCurrencyShort(value)
                            }
                        }
                    }
                }
            });
        }
    }

    function updatePaymentMethodChart(revenueByMethod) {
        const ctx = document.getElementById('payment-method-chart');
        if (!ctx) return;

        const methodLabels = {
            'cash': 'نقدي',
            'card': 'بطاقة',
            'transfer': 'تحويل',
            'mixed': 'متعدد'
        };

        const labels = Object.keys(revenueByMethod).map(k => methodLabels[k] || k);
        const values = Object.values(revenueByMethod);

        if (paymentMethodChart) {
            paymentMethodChart.data.labels = labels;
            paymentMethodChart.data.datasets[0].data = values;
            paymentMethodChart.update();
        } else {
            paymentMethodChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: PIE_COLORS.slice(0, labels.length),
                        borderWidth: 0,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 16,
                                font: { size: 12 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => `${ctx.label}: ${formatCurrency(ctx.raw)}`
                            }
                        }
                    }
                }
            });
        }
    }

    function updateExpenseCategoryChart(expensesByCategory) {
        const ctx = document.getElementById('expense-category-chart');
        if (!ctx) return;

        const labels = Object.keys(expensesByCategory);
        const values = Object.values(expensesByCategory);

        if (expenseCategoryChart) {
            expenseCategoryChart.data.labels = labels;
            expenseCategoryChart.data.datasets[0].data = values;
            expenseCategoryChart.update();
        } else {
            expenseCategoryChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: PIE_COLORS.slice(0, labels.length),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 12,
                                font: { size: 11 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => `${ctx.label}: ${formatCurrency(ctx.raw)}`
                            }
                        }
                    }
                }
            });
        }
    }

    function updateProfitTrendChart(dailyData) {
        const ctx = document.getElementById('profit-trend-chart');
        if (!ctx) return;

        const labels = dailyData.map(d => formatDateLabel(d.date));
        const profits = dailyData.map(d => d.profit);

        if (profitTrendChart) {
            profitTrendChart.data.labels = labels;
            profitTrendChart.data.datasets[0].data = profits;
            profitTrendChart.update();
        } else {
            profitTrendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'صافي الربح',
                        data: profits,
                        borderColor: COLORS.blue,
                        backgroundColor: COLORS.blueLight,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: COLORS.blue
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => `صافي الربح: ${formatCurrency(ctx.raw)}`
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: value => formatCurrencyShort(value)
                            }
                        }
                    }
                }
            });
        }
    }

    // ============================================================
    // TABLES UPDATE
    // ============================================================

    function updateTransactionsTable(transactions) {
        const tbody = document.getElementById('transactions-table-body');
        if (!tbody) return;

        if (!transactions || transactions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="loading-cell">لا توجد معاملات في هذه الفترة</td></tr>';
            return;
        }

        tbody.innerHTML = transactions.map(t => {
            const typeClass = t.type === 'income' ? 'income' : 'expense';
            const typeIcon = t.type === 'income' ? 'fa-arrow-down' : 'fa-arrow-up';
            const typeLabel = t.type === 'income' ? 'إيراد' : 'مصروف';
            const amountClass = t.type === 'income' ? 'amount-income' : 'amount-expense';
            const sign = t.type === 'income' ? '+' : '-';

            return `
                <tr data-type="${t.type}">
                    <td>
                        <span class="transaction-type ${typeClass}">
                            <i class="fas ${typeIcon}"></i> ${typeLabel}
                        </span>
                    </td>
                    <td>${formatDateLabel(t.date)}</td>
                    <td>${escapeHtml(t.description || '-')}</td>
                    <td>${escapeHtml(t.customer || t.category || '-')}</td>
                    <td class="${amountClass}">${sign} ${formatCurrency(t.amount)}</td>
                    <td>${escapeHtml(t.reference || '-')}</td>
                </tr>
            `;
        }).join('');
    }

    function updateDebtsTable(unpaidInvoices) {
        const tbody = document.getElementById('debts-table-body');
        if (!tbody) return;

        if (!unpaidInvoices || unpaidInvoices.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="loading-cell">✅ لا توجد فواتير غير مسددة</td></tr>';
            return;
        }

        tbody.innerHTML = unpaidInvoices.map(inv => {
            const statusClass = inv.payment_status === 'partial' ? 'partial' : 'unpaid';
            const statusLabel = inv.payment_status === 'partial' ? 'مدفوع جزئياً' : 'غير مدفوع';

            return `
                <tr>
                    <td><strong>${escapeHtml(inv.invoice_number)}</strong></td>
                    <td>${escapeHtml(inv.customer_name || 'غير معروف')}</td>
                    <td><a href="tel:${inv.customer_phone}">${inv.customer_phone || '-'}</a></td>
                    <td>${formatCurrency(inv.total_price)}</td>
                    <td class="amount-expense"><strong>${formatCurrency(inv.remaining_balance)}</strong></td>
                    <td><span class="status-badge ${statusClass}">${statusLabel}</span></td>
                </tr>
            `;
        }).join('');
    }

    // ============================================================
    // FILTER TRANSACTIONS
    // ============================================================

    function filterTransactions() {
        const filter = document.getElementById('transaction-type-filter').value;
        const rows = document.querySelectorAll('#transactions-table-body tr[data-type]');

        rows.forEach(row => {
            if (!filter || row.dataset.type === filter) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // ============================================================
    // EXPORT FUNCTIONS
    // ============================================================

    function exportToExcel() {
        // Create CSV content
        const lines = [];

        // Header
        lines.push('النوع,التاريخ,الوصف,العميل/الفئة,المبلغ,المرجع');

        // Get visible rows
        const rows = document.querySelectorAll('#transactions-table-body tr[data-type]');
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const cells = row.querySelectorAll('td');
                const rowData = Array.from(cells).map(cell => {
                    let text = cell.textContent.trim();
                    // Escape quotes and wrap in quotes if contains comma
                    if (text.includes(',') || text.includes('"')) {
                        text = '"' + text.replace(/"/g, '""') + '"';
                    }
                    return text;
                });
                lines.push(rowData.join(','));
            }
        });

        // Create and download
        const csv = '\uFEFF' + lines.join('\n'); // BOM for Arabic support
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `تقرير_مالي_${currentDateRange.from}_${currentDateRange.to}.csv`;
        link.click();

        alert('✅ تم تصدير التقرير بنجاح');
    }

    function exportToPDF() {
        // Use browser print for PDF
        window.print();
    }

    // ============================================================
    // UTILITY FUNCTIONS
    // ============================================================

    function formatCurrency(amount) {
        const num = parseFloat(amount) || 0;
        return num.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' ريال';
    }

    function formatCurrencyShort(amount) {
        const num = parseFloat(amount) || 0;
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toFixed(0);
    }

    function formatDateLabel(dateStr) {
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('ar-SA', {
                month: 'short',
                day: 'numeric'
            });
        } catch {
            return dateStr;
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showError(message) {
        // Could be replaced with a toast notification
        console.error(message);
    }

});

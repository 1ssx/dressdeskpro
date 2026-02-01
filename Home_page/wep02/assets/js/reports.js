/**
 * Reports & Analytics Module
 * Handles reports data loading, chart rendering, and filters
 * PHASE 7 - Reports Module Implementation
 */

document.addEventListener('DOMContentLoaded', function () {

    // API URLs
    const DASHBOARD_API_URL = 'api/dashboard.php';
    const BOOKINGS_API_URL = 'api/bookings.php';
    const DAILY_REPORT_API_URL = 'api/daily_report.php';
    const INVOICES_API_URL = 'api/invoices.php?action=list';
    const EXPENSES_API_URL = 'api/expenses.php?action=list';

    // DOM Elements
    const todaySalesEl = document.getElementById('today-sales');
    const todayBookingsEl = document.getElementById('today-bookings');
    const todayExpensesEl = document.getElementById('today-expenses');
    const todayProfitEl = document.getElementById('today-profit');

    // Filter elements
    const filterDateFrom = document.getElementById('filter-date-from');
    const filterDateTo = document.getElementById('filter-date-to');
    const filterBookingType = document.getElementById('filter-booking-type');
    const filterInvoiceType = document.getElementById('filter-invoice-type');
    const applyFiltersBtn = document.getElementById('apply-filters-btn');
    const clearFiltersBtn = document.getElementById('clear-filters-btn');

    // Table bodies
    const bookingsTableBody = document.getElementById('bookings-table-body');
    const salesTableBody = document.getElementById('sales-table-body');
    const expensesTableBody = document.getElementById('expenses-table-body');
    const profitTableBody = document.getElementById('profit-table-body');

    // Chart instances
    let bookingsBarChart = null;
    let typesPieChart = null;
    let revenueExpenseLineChart = null;

    // Ensure formatCurrency is available (from formatters.js)
    // If not available, define a fallback
    if (typeof formatCurrency === 'undefined') {
        window.formatCurrency = function (amount, currency = 'ريال') {
            const num = parseFloat(amount) || 0;
            return num.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + ' ' + currency;
        };
    }

    // ============================================================
    // INITIALIZATION
    // ============================================================

    init();

    async function init() {
        await loadSummaryCards();
        setupEventListeners();
        // Load all bookings initially (no date filter)
        await fetchWeeklyReport(null, null);
        // Then load other reports with today's date
        await handleFilters();
        // Initialize charts
        await renderCharts();
    }

    // ============================================================
    // EVENT LISTENERS
    // ============================================================

    function setupEventListeners() {
        applyFiltersBtn.addEventListener('click', handleFilters);
        clearFiltersBtn.addEventListener('click', clearFilters);

        // Print buttons
        document.getElementById('print-selected-date-btn')?.addEventListener('click', printSelectedDate);
        document.getElementById('print-range-btn')?.addEventListener('click', printDateRange);
        document.getElementById('print-week-btn')?.addEventListener('click', printThisWeek);
        document.getElementById('print-month-btn')?.addEventListener('click', printThisMonth);
        document.getElementById('print-all-btn')?.addEventListener('click', printAll);
    }

    // ============================================================
    // LOAD SUMMARY CARDS
    // ============================================================

    /**
     * Load summary cards (today's totals)
     */
    async function loadSummaryCards() {
        try {
            // Load dashboard stats
            const dashboardResponse = await fetch(DASHBOARD_API_URL);
            if (!dashboardResponse.ok) throw new Error(`HTTP ${dashboardResponse.status}`);

            const dashboardResult = await dashboardResponse.json();
            if (dashboardResult.status !== 'success') {
                throw new Error(dashboardResult.message || 'Failed to load dashboard');
            }

            const dashboard = dashboardResult.data;

            // Load bookings stats
            const bookingsResponse = await fetch(`${BOOKINGS_API_URL}?action=stats`);
            if (!bookingsResponse.ok) throw new Error(`HTTP ${bookingsResponse.status}`);

            const bookingsResult = await bookingsResponse.json();
            console.log('Bookings stats result:', bookingsResult); // Debug log

            let todayBookingsCount = 0;
            if (bookingsResult.status === 'success' && bookingsResult.data) {
                todayBookingsCount = parseInt(bookingsResult.data.today_count) || 0;
            }

            // Load today's expenses
            const today = new Date().toISOString().split('T')[0];
            const expensesResponse = await fetch(`${EXPENSES_API_URL}&date=${today}`);
            if (!expensesResponse.ok) throw new Error(`HTTP ${expensesResponse.status}`);

            const expensesResult = await expensesResponse.json();
            const expensesData = expensesResult.status === 'success' && expensesResult.data
                ? expensesResult.data
                : { summary: { total_expenses: 0 } };

            // Debug: Log received dashboard data
            console.log('Reports - Dashboard data received:', dashboard);

            // Calculate profit - try multiple field names for compatibility
            const todaySales = parseFloat(dashboard.daily_sales) || parseFloat(dashboard.total_sales) || 0;
            const todayRevenue = parseFloat(dashboard.today_revenue) || 0;
            const todayExpenses = expensesData.summary ? parseFloat(expensesData.summary.total_expenses) || 0 : 0;
            // FIX: Profit should be calculated from total sales, not just revenue (deposits)
            // Revenue is only deposits, but profit = total sales - expenses
            const todayProfit = todaySales - todayExpenses;

            console.log('Reports - Calculated values:', {
                todaySales,
                todayRevenue,
                todayExpenses,
                todayProfit,
                calculation: `Profit = Sales (${todaySales}) - Expenses (${todayExpenses}) = ${todayProfit}`
            });

            // Update UI
            if (todaySalesEl) todaySalesEl.textContent = formatCurrency(todaySales);
            if (todayBookingsEl) todayBookingsEl.textContent = todayBookingsCount;
            if (todayExpensesEl) todayExpensesEl.textContent = formatCurrency(todayExpenses);
            if (todayProfitEl) {
                todayProfitEl.textContent = formatCurrency(todayProfit);
                todayProfitEl.style.color = todayProfit >= 0 ? '#27ae60' : '#e74c3c';
            }
        } catch (error) {
            console.error('Failed to load summary cards:', error);
            todaySalesEl.textContent = 'خطأ';
            todayBookingsEl.textContent = 'خطأ';
            todayExpensesEl.textContent = 'خطأ';
            todayProfitEl.textContent = 'خطأ';
        }
    }

    // ============================================================
    // FILTER FUNCTIONS
    // ============================================================

    /**
     * Handle filters and reload all data
     */
    async function handleFilters() {
        // Get date filters (if empty, show all bookings)
        const dateFrom = filterDateFrom.value || null;
        const dateTo = filterDateTo.value || null;

        // For daily report, use today if no dates specified
        const dateFromToUse = dateFrom || new Date().toISOString().split('T')[0];
        const dateToToUse = dateTo || new Date().toISOString().split('T')[0];

        await Promise.all([
            fetchDailyReport(dateFromToUse, dateToToUse),
            fetchBookingTypeStats(),
            fetchWeeklyReport(dateFrom, dateTo) // Pass null if empty to show all bookings
        ]);

        updateCharts();
    }

    /**
     * Clear filters
     */
    function clearFilters() {
        filterDateFrom.value = '';
        filterDateTo.value = '';
        filterBookingType.value = '';
        filterInvoiceType.value = '';
        handleFilters();
    }

    // ============================================================
    // FETCH REPORT DATA
    // ============================================================

    /**
     * Fetch daily report for a specific date
     */
    async function fetchDailyReport(date, endDate = null) {
        try {
            const dateToUse = endDate || date;
            const response = await fetch(`${DAILY_REPORT_API_URL}?date=${dateToUse}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const result = await response.json();
            console.log('Daily Report API response:', result); // Debug log

            if (result.status !== 'success') {
                throw new Error(result.message || 'Failed to load daily report');
            }

            // Data is wrapped in result.data by sendSuccess function
            const data = result.data || result;

            // Render sales table
            renderSalesTable(data.invoices || data.sales || []);

            // Render expenses table
            renderExpensesTable(data.expenses || []);

            // Render profit table
            const summary = data.summary || data.totals || {};
            renderProfitTable([{
                date: data.date,
                total_sales: summary.total_sales_value || summary.total_sales || 0,
                total_expenses: summary.total_expenses || 0,
                net_profit: summary.net_cash || summary.net_income || 0
            }]);

            return data;
        } catch (error) {
            console.error('Failed to fetch daily report:', error);
            if (salesTableBody) salesTableBody.innerHTML = '<tr><td colspan="7" class="loading">خطأ في تحميل البيانات: ' + error.message + '</td></tr>';
            if (expensesTableBody) expensesTableBody.innerHTML = '<tr><td colspan="4" class="loading">خطأ في تحميل البيانات</td></tr>';
            if (profitTableBody) profitTableBody.innerHTML = '<tr><td colspan="4" class="loading">خطأ في تحميل البيانات</td></tr>';
        }
    }

    /**
     * Fetch weekly report (aggregate daily reports)
     */
    async function fetchWeeklyReport(startDate, endDate) {
        try {
            // Fetch bookings for date range
            const params = new URLSearchParams({
                action: 'list'
            });

            // Only add date filters if dates are provided
            // If no dates, API will return all bookings
            if (startDate) {
                params.append('date_from', startDate);
            }
            if (endDate) {
                params.append('date_to', endDate);
            }

            if (filterBookingType.value) {
                params.append('booking_type', filterBookingType.value);
            }

            const response = await fetch(`${BOOKINGS_API_URL}?${params.toString()}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const result = await response.json();
            if (result.status !== 'success') {
                throw new Error(result.message || 'Failed to load bookings');
            }

            const bookings = result.data || [];

            renderBookingsTable(bookings);

            return bookings;
        } catch (error) {
            console.error('Failed to fetch weekly report:', error);
            bookingsTableBody.innerHTML = '<tr><td colspan="5" class="loading">خطأ في تحميل البيانات: ' + error.message + '</td></tr>';
        }
    }

    /**
     * Fetch booking type statistics
     */
    async function fetchBookingTypeStats() {
        try {
            const dateFrom = filterDateFrom.value || new Date().toISOString().split('T')[0];
            const dateTo = filterDateTo.value || new Date().toISOString().split('T')[0];

            const params = new URLSearchParams({
                action: 'list',
                date_from: dateFrom,
                date_to: dateTo
            });

            const response = await fetch(`${BOOKINGS_API_URL}?${params.toString()}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const result = await response.json();
            if (result.status !== 'success') {
                throw new Error(result.message || 'Failed to load booking stats');
            }

            // Group by booking type
            const typeStats = {};
            (result.data || []).forEach(booking => {
                const type = booking.booking_type;
                typeStats[type] = (typeStats[type] || 0) + 1;
            });

            return typeStats;
        } catch (error) {
            console.error('Failed to fetch booking type stats:', error);
            return {};
        }
    }

    // ============================================================
    // RENDER TABLES
    // ============================================================

    /**
     * Render bookings table
     */
    function renderBookingsTable(bookings) {
        if (bookings.length === 0) {
            bookingsTableBody.innerHTML = '<tr><td colspan="5" class="loading">لا توجد حجوزات</td></tr>';
            return;
        }

        bookingsTableBody.innerHTML = bookings.map(booking => {
            const bookingDate = new Date(booking.booking_date);
            const dateStr = bookingDate.toLocaleDateString('en-GB');

            const statusLabels = {
                'pending': 'قيد الانتظار',
                'confirmed': 'مؤكد',
                'completed': 'مكتمل',
                'cancelled': 'ملغي',
                'late': 'متأخر'
            };

            return `
                <tr>
                    <td>#${booking.id}</td>
                    <td>${escapeHtml(booking.customer_name)}</td>
                    <td>${escapeHtml(booking.booking_type)}</td>
                    <td>${dateStr}</td>
                    <td>${statusLabels[booking.status] || booking.status}</td>
                </tr>
            `;
        }).join('');
    }

    /**
     * Render sales table
     */
    function renderSalesTable(invoices) {
        if (invoices.length === 0) {
            salesTableBody.innerHTML = '<tr><td colspan="7" class="loading">لا توجد فواتير</td></tr>';
            return;
        }

        salesTableBody.innerHTML = invoices.map(invoice => {
            const invoiceDate = new Date(invoice.invoice_date);
            const dateStr = invoiceDate.toLocaleDateString('en-GB');

            const operationLabels = {
                'sale': 'بيع',
                'rent': 'إيجار',
                'design': 'تصميم',
                'design-sale': 'تصميم وبيع',
                'design-rent': 'تصميم وإيجار'
            };

            return `
                <tr>
                    <td>${escapeHtml(invoice.invoice_number)}</td>
                    <td>${escapeHtml(invoice.customer_name || 'غير معروف')}</td>
                    <td>${operationLabels[invoice.operation_type] || invoice.operation_type}</td>
                    <td>${formatCurrency(invoice.total_price)}</td>
                    <td>${formatCurrency(invoice.deposit_amount)}</td>
                    <td>${formatCurrency(invoice.total_price - invoice.deposit_amount)}</td>
                    <td>${dateStr}</td>
                </tr>
            `;
        }).join('');
    }

    /**
     * Render expenses table
     */
    function renderExpensesTable(expenses) {
        if (expenses.length === 0) {
            expensesTableBody.innerHTML = '<tr><td colspan="4" class="loading">لا توجد مصروفات</td></tr>';
            return;
        }

        expensesTableBody.innerHTML = expenses.map(expense => {
            const expenseDate = new Date(expense.expense_date);
            const dateStr = expenseDate.toLocaleDateString('en-GB');

            return `
                <tr>
                    <td>${escapeHtml(expense.category)}</td>
                    <td>${escapeHtml(expense.description || '-')}</td>
                    <td>${formatCurrency(expense.amount)}</td>
                    <td>${dateStr}</td>
                </tr>
            `;
        }).join('');
    }

    /**
     * Render profit table
     */
    function renderProfitTable(profitData) {
        if (profitData.length === 0) {
            profitTableBody.innerHTML = '<tr><td colspan="4" class="loading">لا توجد بيانات</td></tr>';
            return;
        }

        profitTableBody.innerHTML = profitData.map(row => {
            const dateStr = new Date(row.date).toLocaleDateString('en-GB');
            const profit = row.net_profit || (row.total_sales - row.total_expenses);

            return `
                <tr>
                    <td>${dateStr}</td>
                    <td>${formatCurrency(row.total_sales)}</td>
                    <td>${formatCurrency(row.total_expenses)}</td>
                    <td style="color: ${profit >= 0 ? '#27ae60' : '#e74c3c'}">${formatCurrency(profit)}</td>
                </tr>
            `;
        }).join('');
    }

    // ============================================================
    // CHART FUNCTIONS
    // ============================================================

    /**
     * Initialize all charts
     */
    async function renderCharts() {
        await updateCharts();
    }

    /**
     * Update all charts with current data
     */
    async function updateCharts() {
        try {
            console.log('📊 Updating charts...');

            // Use last 7 days for better chart visibility
            const today = new Date();
            const weekAgo = new Date(today);
            weekAgo.setDate(weekAgo.getDate() - 7);

            const dateFrom = filterDateFrom?.value || weekAgo.toISOString().split('T')[0];
            const dateTo = filterDateTo?.value || today.toISOString().split('T')[0];

            console.log('📅 Date range:', dateFrom, 'to', dateTo);

            // Fetch data for charts
            const [bookingsData, typeStats, dailyReports] = await Promise.all([
                fetchBookingsForChart(dateFrom, dateTo),
                fetchBookingTypeStats(),
                fetchDailyReportsForChart(dateFrom, dateTo)
            ]);

            console.log('📈 Bookings data:', bookingsData);
            console.log('📊 Type stats:', typeStats);
            console.log('📉 Daily reports:', dailyReports);

            // Update or create charts
            updateBookingsBarChart(bookingsData);
            updateBookingTypesPieChart(typeStats);
            updateRevenueExpenseLineChart(dailyReports);

            console.log('✅ Charts updated successfully!');
        } catch (error) {
            console.error('❌ Failed to update charts:', error);
        }
    }

    /**
     * Fetch bookings data for bar chart
     */
    async function fetchBookingsForChart(dateFrom, dateTo) {
        try {
            const params = new URLSearchParams({
                action: 'list',
                date_from: dateFrom,
                date_to: dateTo
            });

            const response = await fetch(`${BOOKINGS_API_URL}?${params.toString()}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const result = await response.json();
            if (result.status !== 'success') {
                throw new Error(result.message || 'Failed to load bookings');
            }

            // Group by date
            const bookingsByDate = {};
            (result.data || []).forEach(booking => {
                const date = booking.booking_date.split(' ')[0]; // Get date part only
                bookingsByDate[date] = (bookingsByDate[date] || 0) + 1;
            });

            return bookingsByDate;
        } catch (error) {
            console.error('Failed to fetch bookings for chart:', error);
            return {};
        }
    }

    /**
     * Fetch daily reports for line chart
     */
    async function fetchDailyReportsForChart(dateFrom, dateTo) {
        try {
            const reports = [];
            const start = new Date(dateFrom);
            const end = new Date(dateTo);

            // Fetch reports for each day in range (limit to 30 days for performance)
            const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
            const maxDays = 30;
            const step = daysDiff > maxDays ? Math.ceil(daysDiff / maxDays) : 1;

            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + step)) {
                const dateStr = d.toISOString().split('T')[0];
                const response = await fetch(`${DAILY_REPORT_API_URL}?date=${dateStr}`);
                if (response.ok) {
                    const result = await response.json();
                    if (result.status === 'success') {
                        // Data is wrapped in result.data by sendSuccess function
                        const data = result.data || result;
                        const summary = data.summary || {};
                        reports.push({
                            date: dateStr,
                            revenue: summary.total_income || 0,
                            expenses: summary.total_expenses || 0
                        });
                    }
                }
            }

            return reports;
        } catch (error) {
            console.error('Failed to fetch daily reports for chart:', error);
            return [];
        }
    }

    /**
     * Update or create bookings bar chart
     */
    function updateBookingsBarChart(bookingsData) {
        const ctx = document.getElementById('bookings-bar-chart');
        if (!ctx) return;

        const dates = Object.keys(bookingsData).sort();
        const counts = dates.map(date => bookingsData[date]);

        if (bookingsBarChart) {
            bookingsBarChart.data.labels = dates;
            bookingsBarChart.data.datasets[0].data = counts;
            bookingsBarChart.update();
        } else {
            bookingsBarChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dates,
                    datasets: [{
                        label: 'عدد الحجوزات',
                        data: counts,
                        backgroundColor: 'rgba(52, 152, 219, 0.8)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    aspectRatio: 2,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
    }

    /**
     * Update or create booking types pie chart
     */
    function updateBookingTypesPieChart(typeStats) {
        const ctx = document.getElementById('booking-types-pie-chart');
        if (!ctx) return;

        const types = Object.keys(typeStats);
        const counts = types.map(type => typeStats[type]);

        const colors = [
            'rgba(52, 152, 219, 0.8)',
            'rgba(155, 89, 182, 0.8)',
            'rgba(46, 204, 113, 0.8)',
            'rgba(241, 196, 15, 0.8)',
            'rgba(231, 76, 60, 0.8)',
            'rgba(230, 126, 34, 0.8)',
            'rgba(149, 165, 166, 0.8)'
        ];

        if (typesPieChart) {
            typesPieChart.data.labels = types;
            typesPieChart.data.datasets[0].data = counts;
            typesPieChart.data.datasets[0].backgroundColor = colors.slice(0, types.length);
            typesPieChart.update();
        } else {
            typesPieChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: types,
                    datasets: [{
                        data: counts,
                        backgroundColor: colors.slice(0, types.length),
                        borderWidth: 2,
                        borderColor: '#2c3e50'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    aspectRatio: 1.5,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    /**
     * Update or create revenue vs expenses line chart
     */
    function updateRevenueExpenseLineChart(reports) {
        const ctx = document.getElementById('revenue-expense-line-chart');
        if (!ctx) return;

        const dates = reports.map(r => r.date);
        const revenues = reports.map(r => r.revenue);
        const expenses = reports.map(r => r.expenses);

        if (revenueExpenseLineChart) {
            revenueExpenseLineChart.data.labels = dates;
            revenueExpenseLineChart.data.datasets[0].data = revenues;
            revenueExpenseLineChart.data.datasets[1].data = expenses;
            revenueExpenseLineChart.update();
        } else {
            revenueExpenseLineChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [
                        {
                            label: 'الإيرادات',
                            data: revenues,
                            borderColor: 'rgba(46, 204, 113, 1)',
                            backgroundColor: 'rgba(46, 204, 113, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'المصروفات',
                            data: expenses,
                            borderColor: 'rgba(231, 76, 60, 1)',
                            backgroundColor: 'rgba(231, 76, 60, 0.1)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    aspectRatio: 2.5,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                boxWidth: 12,
                                padding: 10,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (value) {
                                    return formatCurrency(value);
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    // ============================================================
    // UTILITY FUNCTIONS
    // ============================================================

    /**
     * Format currency
     */
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ============================================================
    // PRINT FUNCTIONS
    // ============================================================

    /**
     * Print report for selected date
     */
    async function printSelectedDate() {
        const date = prompt('أدخل التاريخ (YYYY-MM-DD):');
        if (!date) return;

        try {
            const response = await fetch(`${DAILY_REPORT_API_URL}?date=${date}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const result = await response.json();
            if (result.status !== 'success') {
                throw new Error(result.message || 'Failed to load report');
            }

            printReport(result.data, `تقرير ${date}`);
        } catch (error) {
            alert('فشل تحميل التقرير: ' + error.message);
        }
    }

    /**
     * Print report for date range
     */
    async function printDateRange() {
        const dateFrom = filterDateFrom.value || prompt('من تاريخ (YYYY-MM-DD):');
        const dateTo = filterDateTo.value || prompt('إلى تاريخ (YYYY-MM-DD):');

        if (!dateFrom || !dateTo) {
            alert('الرجاء تحديد تاريخ البداية والنهاية');
            return;
        }

        try {
            // Fetch data for the range
            const response = await fetch(`${DAILY_REPORT_API_URL}?date_from=${dateFrom}&date_to=${dateTo}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const result = await response.json();
            if (result.status !== 'success') {
                throw new Error(result.message || 'Failed to load report');
            }

            printReport(result.data, `تقرير من ${dateFrom} إلى ${dateTo}`);
        } catch (error) {
            alert('فشل تحميل التقرير: ' + error.message);
        }
    }

    /**
     * Print this week's report
     */
    async function printThisWeek() {
        const today = new Date();
        const dayOfWeek = today.getDay();
        const diff = today.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1); // Monday
        const monday = new Date(today.setDate(diff));
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);

        const dateFrom = monday.toISOString().split('T')[0];
        const dateTo = sunday.toISOString().split('T')[0];

        try {
            const response = await fetch(`${DAILY_REPORT_API_URL}?date_from=${dateFrom}&date_to=${dateTo}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const result = await response.json();
            if (result.status !== 'success') {
                throw new Error(result.message || 'Failed to load report');
            }

            printReport(result.data, `تقرير هذا الأسبوع (${dateFrom} - ${dateTo})`);
        } catch (error) {
            alert('فشل تحميل التقرير: ' + error.message);
        }
    }

    /**
     * Print this month's report
     */
    async function printThisMonth() {
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

        const dateFrom = firstDay.toISOString().split('T')[0];
        const dateTo = lastDay.toISOString().split('T')[0];

        try {
            const response = await fetch(`${DAILY_REPORT_API_URL}?date_from=${dateFrom}&date_to=${dateTo}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const result = await response.json();
            if (result.status !== 'success') {
                throw new Error(result.message || 'Failed to load report');
            }

            printReport(result.data, `تقرير هذا الشهر (${dateFrom} - ${dateTo})`);
        } catch (error) {
            alert('فشل تحميل التقرير: ' + error.message);
        }
    }

    /**
     * Print all reports
     */
    async function printAll() {
        if (!confirm('هل تريد طباعة جميع التقارير؟ قد يستغرق هذا بعض الوقت.')) {
            return;
        }

        try {
            // Fetch today's data as default (API requires a date parameter)
            const today = new Date().toISOString().split('T')[0];
            const response = await fetch(`${DAILY_REPORT_API_URL}?date=${today}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const result = await response.json();
            if (result.status !== 'success') {
                throw new Error(result.message || 'Failed to load report');
            }

            // Validate data exists
            if (!result.data) {
                throw new Error('No data available for printing');
            }

            printReport(result.data, 'تقرير شامل - جميع البيانات');
        } catch (error) {
            alert('فشل تحميل التقرير: ' + error.message);
        }
    }

    /**
     * Print report HTML
     */
    function printReport(data, title) {
        // Validate data
        if (!data) {
            alert('لا توجد بيانات للطباعة');
            return;
        }

        const html = buildPrintHTML(data, title);
        const printWindow = window.open('', '_blank', 'width=1000,height=1200');
        if (!printWindow) {
            alert('فضلاً اسمح بالنوافذ المنبثقة (Pop-ups) لطباعة التقرير');
            return;
        }

        printWindow.document.write(html);
        printWindow.document.close();
        printWindow.focus();

        // Auto print after a short delay
        setTimeout(() => {
            printWindow.print();
        }, 500);
    }

    /**
     * Build print HTML - COMPREHENSIVE REPORT
     */
    function buildPrintHTML(data, title) {
        const formatCurrency = window.formatCurrency || function (amount) {
            return parseFloat(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' ريال';
        };

        // Safely extract data
        const summary = data.summary || {};
        const invoices = data.invoices || [];
        const expenses = data.expenses || [];
        const customers = data.customers || [];
        const products = data.products || [];
        const bookings = data.bookings || [];
        const bookingsByType = data.bookings_by_type || [];

        // Summary values
        const totalSales = summary.total_sales_value || 0;
        const totalIncome = summary.total_income || 0;
        const totalExpenses = summary.total_expenses || 0;
        const netCash = summary.net_cash || 0;
        const remainingBalance = summary.remaining_balance || 0;
        const totalCustomers = summary.total_customers || 0;
        const totalProducts = summary.total_products || 0;
        const inventoryValue = summary.inventory_value || 0;

        // Operation type labels
        const operationLabels = {
            'sale': 'بيع',
            'rent': 'إيجار',
            'design': 'تصميم',
            'design-sale': 'تصميم وبيع',
            'design-rent': 'تصميم وإيجار'
        };

        // Payment status labels
        const paymentLabels = {
            'paid': 'مدفوع',
            'partial': 'جزئي',
            'unpaid': 'غير مدفوع'
        };

        // Booking status labels
        const bookingStatusLabels = {
            'pending': 'قيد الانتظار',
            'confirmed': 'مؤكد',
            'completed': 'مكتمل',
            'cancelled': 'ملغي',
            'late': 'متأخر'
        };

        // Customer type labels
        const customerTypeLabels = {
            'new': 'جديد',
            'regular': 'عادي',
            'vip': 'مميز'
        };

        // Build invoices table rows
        const invoicesRows = invoices.length > 0
            ? invoices.map(inv => `
                <tr>
                    <td>${escapeHtml(inv.invoice_number || '')}</td>
                    <td>${escapeHtml(inv.customer_name || 'غير معروف')}</td>
                    <td>${operationLabels[inv.operation_type] || inv.operation_type}</td>
                    <td>${formatCurrency(inv.total_price)}</td>
                    <td>${formatCurrency(inv.deposit_amount)}</td>
                    <td>${paymentLabels[inv.payment_status] || inv.payment_status}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="6" style="text-align:center;">لا توجد فواتير</td></tr>';

        // Build expenses table rows
        const expensesRows = expenses.length > 0
            ? expenses.map(exp => `
                <tr>
                    <td>${escapeHtml(exp.category || '')}</td>
                    <td>${escapeHtml(exp.description || '-')}</td>
                    <td>${formatCurrency(exp.amount)}</td>
                    <td>${new Date(exp.expense_date).toLocaleDateString('en-GB')}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="4" style="text-align:center;">لا توجد مصروفات</td></tr>';

        // Build customers table rows
        const customersRows = customers.length > 0
            ? customers.map(cust => `
                <tr>
                    <td>${escapeHtml(cust.name || '')}</td>
                    <td>${escapeHtml(cust.phone_1 || '')}</td>
                    <td>${customerTypeLabels[cust.type] || cust.type}</td>
                    <td>${new Date(cust.created_at).toLocaleDateString('en-GB')}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="4" style="text-align:center;">لا يوجد عملاء جدد</td></tr>';

        // Build products table rows
        const productsRows = products.length > 0
            ? products.map(prod => `
                <tr>
                    <td>${escapeHtml(prod.code || '')}</td>
                    <td>${escapeHtml(prod.name || '')}</td>
                    <td>${escapeHtml(prod.category_name || '-')}</td>
                    <td>${formatCurrency(prod.price)}</td>
                    <td>${prod.quantity || 0}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="5" style="text-align:center;">لا توجد منتجات جديدة</td></tr>';

        // Build bookings table rows  
        const bookingsRows = bookings.length > 0
            ? bookings.map(book => `
                <tr>
                    <td>${escapeHtml(book.customer_name || 'غير معروف')}</td>
                    <td>${escapeHtml(book.booking_type || '')}</td>
                    <td>${new Date(book.booking_date).toLocaleDateString('en-GB')}</td>
                    <td>${bookingStatusLabels[book.status] || book.status}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="4" style="text-align:center;">لا توجد حجوزات</td></tr>';

        // Build bookings by type summary
        const bookingsTypeSummary = bookingsByType.length > 0
            ? bookingsByType.map(bt => `<span class="badge">${bt.booking_type}: ${bt.count}</span>`).join(' ')
            : 'لا توجد بيانات';

        return `
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>${title}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; color: #2c3e50; direction: rtl; font-size: 12px; }
        h1 { text-align: center; margin-bottom: 20px; color: #2c3e50; font-size: 22px; }
        h2 { color: #34495e; margin: 25px 0 15px 0; padding: 8px; background: #ecf0f1; border-radius: 5px; font-size: 16px; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 25px; }
        .summary-card { padding: 12px; background: #f8f9fa; border-radius: 8px; text-align: center; border: 1px solid #ddd; }
        .summary-card h3 { font-size: 11px; color: #7f8c8d; margin-bottom: 8px; }
        .summary-card .value { font-size: 16px; font-weight: bold; color: #2c3e50; }
        .summary-card .value.positive { color: #27ae60; }
        .summary-card .value.negative { color: #e74c3c; }
        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; }
        .stat-box { padding: 10px; background: #f0f0f0; border-radius: 5px; text-align: center; }
        .stat-box span { display: block; }
        .stat-box .label { font-size: 10px; color: #666; }
        .stat-box .number { font-size: 18px; font-weight: bold; color: #8e44ad; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        th { background: #34495e; color: white; font-weight: 600; }
        tr:nth-child(even) { background: #f8f9fa; }
        .badge { display: inline-block; padding: 3px 8px; background: #8e44ad; color: white; border-radius: 12px; margin: 2px; font-size: 11px; }
        .section { page-break-inside: avoid; margin-bottom: 20px; }
        .footer { text-align: center; color: #7f8c8d; margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 10px; }
        @media print { 
            body { padding: 0; } 
            @page { margin: 1cm; }
            .section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <h1>📊 ${title}</h1>
    
    <!-- ملخص عام -->
    <div class="summary">
        <div class="summary-card">
            <h3>💰 إجمالي المبيعات</h3>
            <div class="value">${formatCurrency(totalSales)}</div>
        </div>
        <div class="summary-card">
            <h3>📥 المقبوضات</h3>
            <div class="value positive">${formatCurrency(totalIncome)}</div>
        </div>
        <div class="summary-card">
            <h3>📤 المصروفات</h3>
            <div class="value negative">${formatCurrency(totalExpenses)}</div>
        </div>
        <div class="summary-card">
            <h3>💵 صافي الدرج</h3>
            <div class="value ${netCash >= 0 ? 'positive' : 'negative'}">${formatCurrency(netCash)}</div>
        </div>
    </div>
    
    <!-- إحصائيات عامة -->
    <div class="stats-row">
        <div class="stat-box">
            <span class="number">${totalCustomers}</span>
            <span class="label">إجمالي العملاء</span>
        </div>
        <div class="stat-box">
            <span class="number">${totalProducts}</span>
            <span class="label">إجمالي المنتجات</span>
        </div>
        <div class="stat-box">
            <span class="number">${formatCurrency(inventoryValue)}</span>
            <span class="label">قيمة المخزون</span>
        </div>
    </div>
    
    <!-- الفواتير -->
    <div class="section">
        <h2>📄 الفواتير (${invoices.length})</h2>
        <table>
            <thead>
                <tr>
                    <th>رقم الفاتورة</th>
                    <th>العميل</th>
                    <th>نوع العملية</th>
                    <th>الإجمالي</th>
                    <th>المدفوع</th>
                    <th>حالة الدفع</th>
                </tr>
            </thead>
            <tbody>
                ${invoicesRows}
            </tbody>
        </table>
    </div>
    
    <!-- المصروفات -->
    <div class="section">
        <h2>💸 المصروفات (${expenses.length})</h2>
        <table>
            <thead>
                <tr>
                    <th>التصنيف</th>
                    <th>الوصف</th>
                    <th>المبلغ</th>
                    <th>التاريخ</th>
                </tr>
            </thead>
            <tbody>
                ${expensesRows}
            </tbody>
        </table>
    </div>
    
    <!-- الحجوزات -->
    <div class="section">
        <h2>📅 الحجوزات (${bookings.length})</h2>
        <p style="margin-bottom: 10px;">توزيع الحجوزات: ${bookingsTypeSummary}</p>
        <table>
            <thead>
                <tr>
                    <th>العميل</th>
                    <th>نوع الحجز</th>
                    <th>التاريخ</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                ${bookingsRows}
            </tbody>
        </table>
    </div>
    
    <!-- العملاء الجدد -->
    <div class="section">
        <h2>👥 العملاء الجدد (${customers.length})</h2>
        <table>
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>رقم الهاتف</th>
                    <th>النوع</th>
                    <th>تاريخ التسجيل</th>
                </tr>
            </thead>
            <tbody>
                ${customersRows}
            </tbody>
        </table>
    </div>
    
    <!-- المنتجات الجديدة -->
    <div class="section">
        <h2>👗 المنتجات الجديدة (${products.length})</h2>
        <table>
            <thead>
                <tr>
                    <th>الكود</th>
                    <th>الاسم</th>
                    <th>التصنيف</th>
                    <th>السعر</th>
                    <th>الكمية</th>
                </tr>
            </thead>
            <tbody>
                ${productsRows}
            </tbody>
        </table>
    </div>
    
    <div class="footer">
        <p>تم استخراج التقرير آلياً بتاريخ ${new Date().toLocaleString('en-GB')}</p>
        <p>نظام إدارة محل فساتين الزفاف - الإصدار 2.1</p>
    </div>
</body>
</html>`;
    }

    // Initialize charts after a short delay to ensure DOM is ready and Chart.js is loaded
    setTimeout(() => {
        if (typeof Chart !== 'undefined') {
            renderCharts();
        } else {
            console.error('Chart.js library not loaded');
        }
    }, 500);

});


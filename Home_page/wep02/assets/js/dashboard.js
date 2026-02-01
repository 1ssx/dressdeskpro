/**
 * Dashboard Real-time Statistics
 * PHASE 4 - Refactored to use core utilities
 * Fetches and displays live data from the database
 */

document.addEventListener('DOMContentLoaded', function () {

    // Load dashboard statistics on page load
    loadDashboardStats();

    // Refresh every 60 seconds
    setInterval(loadDashboardStats, 60000);

    async function loadDashboardStats() {
        try {
            // index.php is in public/ folder, API is in public/api/
            const response = await fetch('api/dashboard.php');

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (result.status !== 'success') {
                throw new Error(result.message || 'Failed to load dashboard data');
            }

            const data = result.data;

            if (!data) {
                throw new Error('No data received from dashboard API');
            }

            // Helper function to format currency (English numbers)
            function formatCurrency(amount) {
                // Use English locale for numbers, but keep Arabic text
                const num = Number(amount || 0);
                return num.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + ' ريال';
            }

            // Debug: Log received data
            console.log('Dashboard data received:', data);

            // 1. Update Daily Sales Card (Total Sales Today)
            const salesAmount = document.querySelector('.sales-card .amount');
            const salesChange = document.querySelector('.sales-card .change');

            // Try multiple field names for compatibility
            const dailySales = parseFloat(data.daily_sales) || parseFloat(data.total_sales) || 0;
            console.log('Daily sales value:', dailySales, 'from fields:', {
                daily_sales: data.daily_sales,
                total_sales: data.total_sales
            });

            if (salesAmount) {
                salesAmount.textContent = formatCurrency(dailySales);
            }
            if (salesChange) {
                const growth = parseFloat(data.growth_percentage) || 0;
                salesChange.innerHTML = `<i class="fas fa-arrow-${growth >= 0 ? 'up' : 'down'}"></i> ${Math.abs(growth)}% عن الأمس`;
                salesChange.className = growth >= 0 ? 'change positive' : 'change negative';
            }

            // 2. Update Revenue Card (Total Revenue/Cash Today)
            const revenueAmount = document.querySelector('.revenue-card .amount');
            const revenueChange = document.querySelector('.revenue-card .change');
            if (revenueAmount) revenueAmount.textContent = formatCurrency(data.today_revenue || 0);
            if (revenueChange) revenueChange.textContent = 'عربونات اليوم';

            // 3. Update New Customers Card
            const customersAmount = document.querySelector('.customers-card .amount');
            const customersTotal = document.querySelector('.customers-card .month-total');
            if (customersAmount) customersAmount.textContent = `${data.new_customers || 0} عميل`;
            if (customersTotal) customersTotal.textContent = 'عملاء جدد اليوم';

            // 4. Update Invoices Count Card
            const invoicesAmount = document.querySelector('.invoices-card .amount');
            const invoicesChange = document.querySelector('.invoices-card .change');
            if (invoicesAmount) invoicesAmount.textContent = `${data.invoice_count || 0} فاتورة`;
            if (invoicesChange) invoicesChange.textContent = 'فواتير اليوم';

        } catch (error) {
            console.error('Failed to load dashboard stats:', error);
            showError(error.message || 'فشل تحميل البيانات');
        }
    }

    function showError(message) {
        document.querySelectorAll('.card .amount, .card .change, .card .month-total').forEach(el => {
            if (el.textContent === '...') {
                el.textContent = 'خطأ';
                el.style.color = '#e74c3c';
            }
        });
    }
});
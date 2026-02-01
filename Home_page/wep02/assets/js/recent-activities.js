/**
 * Recent Activities Module
 * Loads real recent activities from database
 * FIXED: Corrected API paths for proper loading
 */

document.addEventListener('DOMContentLoaded', function () {
    loadRecentActivities();

    // Refresh every 5 minutes
    setInterval(loadRecentActivities, 300000);

    async function loadRecentActivities() {
        const container = document.getElementById('recent-activities-list');
        if (!container) return;

        try {
            // FIX: Correct paths relative to public/overview.php
            // Load recent invoices (last 5)
            const invoicesResponse = await fetch('api/invoices.php?action=list&limit=5');
            if (!invoicesResponse.ok) {
                throw new Error(`HTTP ${invoicesResponse.status}`);
            }

            const invoicesResult = await invoicesResponse.json();
            const invoices = invoicesResult.data || invoicesResult || [];

            // Load recent customers (last 3)
            const customersResponse = await fetch('customers_api.php?action=list&limit=3');
            let customers = [];
            if (customersResponse.ok) {
                const customersResult = await customersResponse.json();
                customers = customersResult.data || customersResult || [];
            }

            // Render activities
            container.innerHTML = '';

            if (invoices.length === 0 && customers.length === 0) {
                container.innerHTML = `
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="activity-details">
                            <p>لا توجد نشاطات حديثة</p>
                        </div>
                    </div>
                `;
                return;
            }

            // Render invoices as activities
            invoices.slice(0, 5).forEach(invoice => {
                const activity = createActivityItem(
                    'fa-shopping-bag',
                    `تم إنشاء فاتورة #${invoice.invoice_number || invoice.id} بقيمة ${formatCurrency(invoice.total_price || 0)}`,
                    formatTimeAgo(invoice.invoice_date || invoice.created_at)
                );
                container.appendChild(activity);
            });

            // Render recent customers
            customers.slice(0, 2).forEach(customer => {
                const activity = createActivityItem(
                    'fa-user-plus',
                    `تم تسجيل عميل جديد: ${escapeHtml(customer.name || 'غير معروف')}`,
                    formatTimeAgo(customer.created_at)
                );
                container.appendChild(activity);
            });

        } catch (error) {
            console.error('Failed to load recent activities:', error);
            container.innerHTML = `
                <div class="activity-item activity-error">
                    <div class="activity-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="activity-details">
                        <p>فشل تحميل النشاطات الأخيرة</p>
                        <span class="activity-time">${error.message}</span>
                    </div>
                </div>
            `;
        }
    }

    function createActivityItem(icon, text, time) {
        const div = document.createElement('div');
        div.className = 'activity-item';
        div.innerHTML = `
            <div class="activity-icon">
                <i class="fas ${icon}"></i>
            </div>
            <div class="activity-details">
                <p>${text}</p>
                <span class="activity-time">${time}</span>
            </div>
        `;
        return div;
    }

    function formatCurrency(amount) {
        return Number(amount).toLocaleString('en-US', {
            style: 'currency',
            currency: 'SAR',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).replace('SAR', 'ريال');
    }

    function formatTimeAgo(dateString) {
        if (!dateString) return '';

        const date = new Date(dateString);
        if (isNaN(date.getTime())) return '';

        const now = new Date();
        const diffMs = now - date;
        const diffSec = Math.floor(diffMs / 1000);
        const diffMin = Math.floor(diffSec / 60);
        const diffHour = Math.floor(diffMin / 60);
        const diffDay = Math.floor(diffHour / 24);

        if (diffSec < 60) {
            return 'منذ أقل من دقيقة';
        } else if (diffMin < 60) {
            return diffMin === 1 ? 'منذ دقيقة واحدة' : `منذ ${diffMin} دقائق`;
        } else if (diffHour < 24) {
            return diffHour === 1 ? 'منذ ساعة واحدة' : `منذ ${diffHour} ساعات`;
        } else if (diffDay < 7) {
            return diffDay === 1 ? 'منذ يوم واحد' : `منذ ${diffDay} أيام`;
        } else {
            return date.toLocaleDateString('en-GB');
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});

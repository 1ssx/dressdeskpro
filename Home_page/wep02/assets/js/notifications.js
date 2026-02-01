/**
 * Notifications Module
 * Fetches and displays customer and delivery notifications
 * PHASE 3 - Updated to use dedicated delivery-notifications endpoint
 */

(function () {
    'use strict';

    const NOTIFICATIONS_CONTAINER_ID = 'notifications-container';
    const DELIVERY_API_URL = 'api/delivery-notifications.php';
    const CUSTOMER_API_URL = 'api/notifications.php'; // For customer notifications only
    const REFRESH_INTERVAL = 5 * 60 * 1000; // 5 minutes
    const BADGE_ID = 'delivery-badge';
    const BADGE_COUNT_ID = 'badge-count';

    /**
     * Initialize notifications system
     */
    function init() {
        loadDeliveryNotifications();
        loadCustomerNotifications(); // Keep customer notifications if they exist

        // Auto-refresh every 5 minutes
        setInterval(() => {
            loadDeliveryNotifications();
            loadCustomerNotifications();
        }, REFRESH_INTERVAL);
    }

    /**
     * Load delivery notifications from dedicated API
     */
    async function loadDeliveryNotifications() {
        try {
            console.log('[Notifications] Fetching delivery notifications from:', DELIVERY_API_URL);
            const response = await fetch(DELIVERY_API_URL);

            if (!response.ok) {
                console.error('[Notifications] HTTP Error:', response.status, response.statusText);
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();
            console.log('[Notifications] API Response:', result);

            if (result.status === 'success' && result.data) {
                console.log('[Notifications] Delivery data:', result.data);
                console.log('[Notifications] Deliveries count:', result.data.deliveries?.length || 0);
                renderDeliveryNotifications(result.data);
                updateDeliveryBadge(result.data);
            } else {
                console.error('[Notifications] Delivery Notifications API Error:', result.message || 'Unknown error');
                console.error('[Notifications] Full result:', result);
                showDeliveryError('فشل تحميل إشعارات التسليم');
            }
        } catch (error) {
            console.error('[Notifications] Failed to load delivery notifications:', error);
            console.error('[Notifications] Error stack:', error.stack);
            showDeliveryError('خطأ في الاتصال بالسيرفر: ' + error.message);
        }
    }

    /**
     * Load customer notifications (if still using old endpoint)
     */
    async function loadCustomerNotifications() {
        try {
            const response = await fetch(CUSTOMER_API_URL);

            if (!response.ok) {
                // If endpoint doesn't exist or fails, just skip customer notifications
                return;
            }

            const result = await response.json();

            if (result.status === 'success' && result.data && result.data.customer_notifications) {
                renderCustomerNotifications(result.data.customer_notifications);
            }
        } catch (error) {
            // Silently fail for customer notifications (optional feature)
            console.debug('Customer notifications not available:', error.message);
        }
    }

    /**
     * Render delivery notifications
     */
    function renderDeliveryNotifications(data) {
        const container = document.getElementById(NOTIFICATIONS_CONTAINER_ID);
        if (!container) {
            console.warn('Notifications container not found');
            return;
        }

        // Clear existing delivery notifications (keep customer notifications if any)
        const existingCustomerNotifications = container.querySelectorAll('.notification-customer');
        container.innerHTML = '';

        const deliveries = data.deliveries || [];
        const windowDays = data.window_days || 7;

        // If no deliveries, show empty state
        if (deliveries.length === 0) {
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'notification-item notification-empty';
            emptyDiv.innerHTML = `
                <i class="fas fa-check-circle"></i>
                <p>لا توجد تسليمات قادمة في الأيام الـ ${windowDays} القادمة</p>
            `;
            container.appendChild(emptyDiv);

            // Re-append customer notifications if they existed
            existingCustomerNotifications.forEach(el => container.appendChild(el));
            return;
        }

        // Render delivery notifications (higher priority, shown first)
        deliveries.forEach(notification => {
            container.appendChild(createDeliveryNotification(notification));
        });

        // Re-append customer notifications after delivery notifications
        existingCustomerNotifications.forEach(el => container.appendChild(el));
    }

    /**
     * Render customer notifications (append to existing container)
     */
    function renderCustomerNotifications(customerNotifications) {
        const container = document.getElementById(NOTIFICATIONS_CONTAINER_ID);
        if (!container) {
            return;
        }

        // Remove existing customer notifications
        container.querySelectorAll('.notification-customer').forEach(el => el.remove());

        // Append new customer notifications
        customerNotifications.forEach(notification => {
            container.appendChild(createCustomerNotification(notification));
        });
    }

    /**
     * Create delivery notification element
     */
    function createDeliveryNotification(notification) {
        const div = document.createElement('div');
        div.className = `notification-item notification-delivery notification-priority-${notification.priority}`;

        if (notification.is_urgent) {
            div.classList.add('notification-urgent');
        }

        const icon = notification.is_urgent ? 'fa-exclamation-triangle' : 'fa-calendar-check';
        const priorityText = notification.priority === 'high' ? 'عاجل' :
            notification.priority === 'medium' ? 'قريب' : 'تنبيه';

        div.innerHTML = `
            <div class="notification-icon">
                <i class="fas ${icon}"></i>
            </div>
            <div class="notification-content">
                <p class="notification-title">${priorityText}: موعد تسليم فستان</p>
                <p class="notification-message">
                    فستان "<strong>${escapeHtml(notification.dress_name)}</strong>" 
                    للعميل <strong>${escapeHtml(notification.customer_name)}</strong>
                </p>
                <div class="notification-meta">
                    <span class="notification-invoice">رقم الفاتورة: ${escapeHtml(notification.invoice_number)}</span>
                    <span class="notification-date">التاريخ: ${notification.date_formatted}</span>
                </div>
            </div>
            ${notification.is_urgent ? '<div class="notification-badge urgent">عاجل</div>' : ''}
        `;

        // Make clickable to view invoice
        div.style.cursor = 'pointer';
        div.addEventListener('click', () => {
            window.location.href = notification.link || `sales.php?invoice=${notification.invoice_id}`;
        });

        return div;
    }

    /**
     * Create customer notification element
     */
    function createCustomerNotification(notification) {
        const div = document.createElement('div');
        div.className = 'notification-item notification-customer';

        div.innerHTML = `
            <div class="notification-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="notification-content">
                <p class="notification-message">
                    تم إضافة عميل جديد: <strong>${escapeHtml(notification.customer_name)}</strong>
                </p>
                <span class="notification-time">${notification.time_ago}</span>
            </div>
        `;

        // Make clickable to view customer
        div.style.cursor = 'pointer';
        div.addEventListener('click', () => {
            window.location.href = `customer.php?id=${notification.customer_id}`;
        });

        return div;
    }

    /**
     * Show error message for delivery notifications
     */
    function showDeliveryError(message) {
        const container = document.getElementById(NOTIFICATIONS_CONTAINER_ID);
        if (container) {
            // Only clear delivery notifications, keep customer notifications if any
            container.querySelectorAll('.notification-delivery, .notification-empty').forEach(el => el.remove());

            const errorDiv = document.createElement('div');
            errorDiv.className = 'notification-item notification-error';
            errorDiv.innerHTML = `
                <i class="fas fa-exclamation-circle"></i>
                <p>${escapeHtml(message)}</p>
            `;
            container.insertBefore(errorDiv, container.firstChild);
        }
    }

    /**
     * Update delivery badge in header
     */
    function updateDeliveryBadge(data) {
        const badge = document.getElementById(BADGE_ID);
        const badgeCount = document.getElementById(BADGE_COUNT_ID);

        if (!badge || !badgeCount) {
            return; // Badge not found, skip
        }

        const count = data.count || 0;
        const deliveries = data.deliveries || [];
        const hasUrgent = deliveries.some(d => d.is_urgent === true);

        if (count > 0) {
            // Show badge and update count
            badge.style.display = 'flex'; // or 'block' depending on CSS
            badgeCount.textContent = count;

            // Add urgent class if any delivery is urgent (today)
            if (hasUrgent) {
                badge.classList.add('urgent');
            } else {
                badge.classList.remove('urgent');
            }
        } else {
            // Hide badge when count is 0
            badge.style.display = 'none';
            badge.classList.remove('urgent');
        }
    }

    /**
     * Setup badge click handler (scroll to notifications)
     */
    function setupBadgeClick() {
        const badge = document.getElementById(BADGE_ID);
        if (!badge) {
            return;
        }

        badge.addEventListener('click', () => {
            const container = document.getElementById(NOTIFICATIONS_CONTAINER_ID);
            if (container) {
                // Smooth scroll to notifications section
                container.scrollIntoView({ behavior: 'smooth', block: 'start' });

                // Optional: Highlight delivery notifications briefly
                const deliveryNotifications = container.querySelectorAll('.notification-delivery');
                deliveryNotifications.forEach(el => {
                    el.style.transition = 'border 0.3s ease';
                    el.style.border = '2px solid #3498db';
                    setTimeout(() => {
                        el.style.border = '';
                    }, 2000);
                });
            }
        });
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            init();
            setupBadgeClick();
        });
    } else {
        init();
        setupBadgeClick();
    }

})();


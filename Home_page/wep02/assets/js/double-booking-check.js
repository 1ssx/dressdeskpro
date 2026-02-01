/**
 * Double Booking Prevention Script
 * 
 * يتحقق من توفر الفساتين قبل حفظ الفاتورة
 * يمنع حجز نفس الفستان في فترات متداخلة
 */

(function () {
    'use strict';

    const API_URL = 'api/invoices.php';
    let checkTimeout = null;

    /**
     * Initialize double booking checker
     */
    function initDoubleBookingChecker() {
        // Listen for changes in relevant fields
        const productField = document.querySelector('[name="product_id"], #dress_code, #product_select');
        const collectionDateField = document.querySelector('[name="collection_date"], #collection_date');
        const returnDateField = document.querySelector('[name="return_date"], #return_date');
        const operationTypeField = document.querySelector('[name="operation_type"], #operation_type');

        if (!productField || !collectionDateField || !returnDateField) {
            console.log('Double booking checker: Required fields not found');
            return;
        }

        // Add event listeners
        [productField, collectionDateField, returnDateField].forEach(field => {
            if (field) {
                field.addEventListener('change', () => {
                    clearTimeout(checkTimeout);
                    checkTimeout = setTimeout(checkAvailability, 500);
                });

                field.addEventListener('blur', checkAvailability);
            }
        });

        console.log('✅ Double booking checker initialized');
    }

    /**
     * Check product availability
     */
    async function checkAvailability() {
        // Get operation type
        const operationTypeField = document.querySelector('[name="operation_type"], #operation_type');
        const operationType = operationTypeField ? operationTypeField.value : '';

        // Only check for rent/design-rent operations
        if (!['rent', 'design-rent'].includes(operationType)) {
            clearWarning();
            return;
        }

        // Get product ID
        const productId = getProductId();
        if (!productId) {
            clearWarning();
            return;
        }

        // Get dates
        const collectionDate = getFieldValue('[name="collection_date"], #collection_date');
        const returnDate = getFieldValue('[name="return_date"], #return_date');

        if (!collectionDate || !returnDate) {
            clearWarning();
            return;
        }

        // Validate dates
        if (new Date(returnDate) <= new Date(collectionDate)) {
            showWarning('تاريخ الإرجاع يجب أن يكون بعد تاريخ الاستلام', 'warning');
            return;
        }

        // Check availability via API
        try {
            showChecking();

            const response = await fetch(`${API_URL}?action=check_availability`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_id: productId,
                    collection_date: collectionDate,
                    return_date: returnDate,
                    current_invoice_id: getCurrentInvoiceId() // For edit mode
                })
            });

            const data = await response.json();

            if (data.status === 'success') {
                if (data.data.available) {
                    showSuccess('الفستان متاح في هذه الفترة ✓');
                } else {
                    showConflict(data.data.conflicts || []);
                }
            } else {
                clearWarning();
            }
        } catch (error) {
            console.error('Error checking availability:', error);
            clearWarning();
        }
    }

    /**
     * Get product ID from form
     */
    function getProductId() {
        const productIdField = document.querySelector('[name="product_id"], #product_id');
        if (productIdField && productIdField.value) {
            return productIdField.value;
        }

        const dressCodeField = document.querySelector('[name="dress_code"], #dress_code');
        if (dressCodeField && dressCodeField.value) {
            // Try to get product ID from dress code
            // This would need to be implemented based on your system
            return dressCodeField.value;
        }

        return null;
    }

    /**
     * Get current invoice ID (for edit mode)
     */
    function getCurrentInvoiceId() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('id') || null;
    }

    /**
     * Get field value
     */
    function getFieldValue(selector) {
        const field = document.querySelector(selector);
        return field ? field.value : null;
    }

    /**
     * Show checking indicator
     */
    function showChecking() {
        showWarning('جار التحقق من التوفر...', 'info');
    }

    /**
     * Show success message
     */
    function showSuccess(message) {
        showWarning(message, 'success');
    }

    /**
     * Show conflict warning
     */
    function showConflict(conflicts) {
        if (!conflicts || conflicts.length === 0) {
            showWarning('الفستان محجوز في هذه الفترة', 'error');
            disableSaveButton();
            return;
        }

        let message = '<strong>⚠️ تعارض في الحجوزات:</strong><br>';
        conflicts.forEach(conflict => {
            message += `• الفستان محجوز في فاتورة ${conflict.invoice_number || 'N/A'}`;
            if (conflict.customer_name) {
                message += ` للعميل ${conflict.customer_name}`;
            }
            message += ` من ${formatDate(conflict.collection_date)} إلى ${formatDate(conflict.return_date)}<br>`;
        });

        showWarning(message, 'error');
        disableSaveButton();
    }

    /**
     * Show warning message
     */
    function showWarning(message, type = 'warning') {
        // Remove existing warnings
        const existing = document.querySelector('.booking-warning');
        if (existing) {
            existing.remove();
        }

        // Create warning element
        const warning = document.createElement('div');
        warning.className = `booking-warning booking-warning-${type}`;
        warning.innerHTML = message;

        // Style based on type
        const colors = {
            'error': { bg: '#fee', border: '#e74c3c', text: '#c0392b' },
            'warning': { bg: '#ffeaa7', border: '#f39c12', text: '#e67e22' },
            'success': { bg: '#d4edda', border: '#28a745', text: '#155724' },
            'info': { bg: '#d1ecf1', border: '#17a2b8', text: '#0c5460' }
        };

        const color = colors[type] || colors['warning'];

        warning.style.cssText = `
            padding: 15px 20px;
            margin: 15px 0;
            background: ${color.bg};
            border: 2px solid ${color.border};
            border-radius: 8px;
            color: ${color.text};
            font-weight: 600;
            animation: fadeIn 0.3s;
        `;

        // Insert after product/date fields
        const insertAfter = document.querySelector('[name="return_date"], #return_date');
        if (insertAfter && insertAfter.parentNode) {
            insertAfter.parentNode.insertBefore(warning, insertAfter.nextSibling);
        } else {
            // Fallback: insert at top of form
            const form = document.querySelector('form');
            if (form) {
                form.insertBefore(warning, form.firstChild);
            }
        }
    }

    /**
     * Clear warning
     */
    function clearWarning() {
        const warning = document.querySelector('.booking-warning');
        if (warning) {
            warning.remove();
        }
        enableSaveButton();
    }

    /**
     * Disable save button
     */
    function disableSaveButton() {
        const saveButton = document.querySelector('button[type="submit"], #save-invoice-btn, .btn-save');
        if (saveButton) {
            saveButton.disabled = true;
            saveButton.style.opacity = '0.5';
            saveButton.style.cursor = 'not-allowed';
            saveButton.setAttribute('data-booking-disabled', 'true');
        }
    }

    /**
     * Enable save button
     */
    function enableSaveButton() {
        const saveButton = document.querySelector('button[type="submit"], #save-invoice-btn, .btn-save');
        if (saveButton && saveButton.getAttribute('data-booking-disabled') === 'true') {
            saveButton.disabled = false;
            saveButton.style.opacity = '1';
            saveButton.style.cursor = 'pointer';
            saveButton.removeAttribute('data-booking-disabled');
        }
    }

    /**
     * Format date for display
     */
    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-GB');
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDoubleBookingChecker);
    } else {
        initDoubleBookingChecker();
    }

    // Export for manual initialization
    window.DoubleBookingChecker = {
        init: initDoubleBookingChecker,
        check: checkAvailability
    };

})();

/**
 * Formatters - Data Formatting Utilities
 * PHASE 4 - JS Restructuring
 * 
 * Provides consistent formatting functions across all modules
 */

/**
 * Format currency amount
 * @param {number|string} amount - Amount to format
 * @param {string} currency - Currency symbol (default: 'ريال')
 * @returns {string} Formatted currency string
 */
function formatCurrency(amount, currency = 'ريال') {
    const num = parseFloat(amount) || 0;
    return num.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }) + ' ' + currency;
}

/**
 * Format number with thousand separators
 * @param {number|string} num - Number to format
 * @param {number} decimals - Number of decimal places
 * @returns {string} Formatted number
 */
function formatNumber(num, decimals = 0) {
    const number = parseFloat(num) || 0;
    return number.toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

/**
 * Format date in Arabic format
 * @param {string|Date} date - Date to format
 * @param {boolean} includeTime - Include time in output
 * @returns {string} Formatted date string
 */
function formatArabicDate(date, includeTime = false) {
    if (!date) return '';

    // Force English Locale for Digits
    const d = new Date(date);
    if (isNaN(d.getTime())) return date;

    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    let formatted = d.toLocaleDateString('en-GB', options);

    if (includeTime) {
        formatted += ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false });
    }

    return formatted;
}

/**
 * Format date as relative time (e.g., "منذ ساعتين")
 * @param {string|Date} date - Date to format
 * @returns {string} Relative time string
 */
function formatTimeAgo(date) {
    if (!date) return '';

    const d = new Date(date);
    if (isNaN(d.getTime())) return date;

    const now = new Date();
    const diffMs = now - d;
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
        return formatArabicDate(date);
    }
}

/**
 * Format phone number
 * @param {string} phone - Phone number
 * @returns {string} Formatted phone
 */
function formatPhone(phone) {
    if (!phone) return '';
    // Remove non-digits
    const digits = phone.replace(/\D/g, '');
    // Format as: 05X XXX XXXX
    if (digits.length === 10 && digits.startsWith('05')) {
        return `${digits.substring(0, 3)} ${digits.substring(3, 6)} ${digits.substring(6)}`;
    }
    return phone;
}

/**
 * Truncate text with ellipsis
 * @param {string} text - Text to truncate
 * @param {number} maxLength - Maximum length
 * @returns {string} Truncated text
 */
function truncateText(text, maxLength = 50) {
    if (!text || text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

/**
 * Format invoice number
 * @param {string|number} number - Invoice number
 * @returns {string} Formatted invoice number
 */
function formatInvoiceNumber(number) {
    if (!number) return '';
    const str = String(number);
    return str.startsWith('INV-') ? str : `INV-${str.padStart(4, '0')}`;
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        formatCurrency,
        formatNumber,
        formatArabicDate,
        formatTimeAgo,
        formatPhone,
        truncateText,
        formatInvoiceNumber
    };
}


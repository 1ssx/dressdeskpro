/**
 * Validators - Form Validation Utilities
 * PHASE 4 - JS Restructuring
 * 
 * Provides consistent validation functions
 */

/**
 * Validate email format
 * @param {string} email - Email address
 * @returns {boolean}
 */
function isValidEmail(email) {
    if (!email) return false;
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Validate phone number (Saudi format: 05XXXXXXXX)
 * @param {string} phone - Phone number
 * @returns {boolean}
 */
function isValidPhone(phone) {
    if (!phone) return false;
    const digits = phone.replace(/\D/g, '');
    return digits.length === 10 && digits.startsWith('05');
}

/**
 * Validate required field
 * @param {*} value - Field value
 * @returns {boolean}
 */
function isRequired(value) {
    if (value === null || value === undefined) return false;
    if (typeof value === 'string') return value.trim().length > 0;
    return true;
}

/**
 * Validate number
 * @param {*} value - Value to validate
 * @param {number} min - Minimum value (optional)
 * @param {number} max - Maximum value (optional)
 * @returns {boolean}
 */
function isValidNumber(value, min = null, max = null) {
    const num = parseFloat(value);
    if (isNaN(num)) return false;
    if (min !== null && num < min) return false;
    if (max !== null && num > max) return false;
    return true;
}

/**
 * Validate date
 * @param {string|Date} date - Date to validate
 * @returns {boolean}
 */
function isValidDate(date) {
    if (!date) return false;
    const d = new Date(date);
    return !isNaN(d.getTime());
}

/**
 * Validate form field
 * @param {Element} field - Form field element
 * @param {Function} validator - Validation function
 * @param {string} errorMessage - Error message
 * @returns {boolean}
 */
function validateField(field, validator, errorMessage) {
    const value = field.value;
    const isValid = validator(value);
    
    const errorEl = field.parentElement.querySelector('.error-message');
    
    if (!isValid) {
        if (errorEl) {
            errorEl.textContent = errorMessage;
            errorEl.style.display = 'block';
        }
        field.classList.add('error');
        return false;
    } else {
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.style.display = 'none';
        }
        field.classList.remove('error');
        return true;
    }
}

/**
 * Validate entire form
 * @param {HTMLFormElement} form - Form element
 * @param {Object} rules - Validation rules object
 * @returns {boolean}
 */
function validateForm(form, rules) {
    let isValid = true;
    
    Object.keys(rules).forEach(fieldName => {
        const field = form.querySelector(`[name="${fieldName}"]`);
        if (!field) return;
        
        const rule = rules[fieldName];
        const fieldValid = validateField(field, rule.validator, rule.message);
        
        if (!fieldValid) {
            isValid = false;
        }
    });
    
    return isValid;
}

/**
 * Clear form validation errors
 * @param {HTMLFormElement} form - Form element
 */
function clearFormErrors(form) {
    const errorMessages = form.querySelectorAll('.error-message');
    errorMessages.forEach(el => {
        el.textContent = '';
        el.style.display = 'none';
    });
    
    const errorFields = form.querySelectorAll('.error');
    errorFields.forEach(field => {
        field.classList.remove('error');
    });
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        isValidEmail,
        isValidPhone,
        isRequired,
        isValidNumber,
        isValidDate,
        validateField,
        validateForm,
        clearFormErrors
    };
}


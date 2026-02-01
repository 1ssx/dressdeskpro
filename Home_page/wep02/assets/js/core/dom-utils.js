/**
 * DOM Utilities - DOM Manipulation Helpers
 * PHASE 4 - JS Restructuring
 * 
 * Provides common DOM manipulation functions
 */

/**
 * Wait for DOM to be ready
 * @param {Function} callback - Callback function
 */
function domReady(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
    } else {
        callback();
    }
}

/**
 * Get element by selector (with optional parent)
 * @param {string} selector - CSS selector
 * @param {Element} parent - Parent element (optional)
 * @returns {Element|null}
 */
function $(selector, parent = document) {
    return parent.querySelector(selector);
}

/**
 * Get all elements by selector
 * @param {string} selector - CSS selector
 * @param {Element} parent - Parent element (optional)
 * @returns {NodeList}
 */
function $$(selector, parent = document) {
    return parent.querySelectorAll(selector);
}

/**
 * Create element with attributes and content
 * @param {string} tag - HTML tag name
 * @param {Object} attrs - Attributes object
 * @param {string|Element} content - Inner content
 * @returns {Element}
 */
function createElement(tag, attrs = {}, content = '') {
    const el = document.createElement(tag);

    // Set attributes
    Object.keys(attrs).forEach(key => {
        if (key === 'className') {
            el.className = attrs[key];
        } else if (key === 'textContent') {
            el.textContent = attrs[key];
        } else if (key.startsWith('data-')) {
            el.setAttribute(key, attrs[key]);
        } else {
            el[key] = attrs[key];
        }
    });

    // Set content
    if (content) {
        if (typeof content === 'string') {
            el.innerHTML = content;
        } else if (content instanceof Element) {
            el.appendChild(content);
        } else if (Array.isArray(content)) {
            content.forEach(child => {
                if (child instanceof Element) {
                    el.appendChild(child);
                }
            });
        }
    }

    return el;
}

/**
 * Show element
 * @param {Element|string} element - Element or selector
 */
function show(element) {
    const el = typeof element === 'string' ? $(element) : element;
    if (el) {
        el.style.display = '';
        el.classList.remove('hidden');
    }
}

/**
 * Hide element
 * @param {Element|string} element - Element or selector
 */
function hide(element) {
    const el = typeof element === 'string' ? $(element) : element;
    if (el) {
        el.style.display = 'none';
        el.classList.add('hidden');
    }
}

/**
 * Toggle element visibility
 * @param {Element|string} element - Element or selector
 */
function toggle(element) {
    const el = typeof element === 'string' ? $(element) : element;
    if (el) {
        if (el.style.display === 'none' || el.classList.contains('hidden')) {
            show(el);
        } else {
            hide(el);
        }
    }
}

/**
 * Add class to element
 * @param {Element|string} element - Element or selector
 * @param {string} className - Class name
 */
function addClass(element, className) {
    const el = typeof element === 'string' ? $(element) : element;
    if (el) {
        el.classList.add(className);
    }
}

/**
 * Remove class from element
 * @param {Element|string} element - Element or selector
 * @param {string} className - Class name
 */
function removeClass(element, className) {
    const el = typeof element === 'string' ? $(element) : element;
    if (el) {
        el.classList.remove(className);
    }
}

/**
 * Toggle class on element
 * @param {Element|string} element - Element or selector
 * @param {string} className - Class name
 */
function toggleClass(element, className) {
    const el = typeof element === 'string' ? $(element) : element;
    if (el) {
        el.classList.toggle(className);
    }
}

/**
 * Update element text content
 * @param {Element|string} element - Element or selector
 * @param {string} text - Text content
 */
function setText(element, text) {
    const el = typeof element === 'string' ? $(element) : element;
    if (el) {
        el.textContent = text;
    }
}

/**
 * Update element HTML
 * @param {Element|string} element - Element or selector
 * @param {string} html - HTML content
 */
function setHTML(element, html) {
    const el = typeof element === 'string' ? $(element) : element;
    if (el) {
        el.innerHTML = html;
    }
}

/**
 * Show loading state
 * @param {Element|string} element - Element or selector
 */
function showLoading(element) {
    const el = typeof element === 'string' ? $(element) : element;
    if (el) {
        addClass(el, 'loading');
        el.setAttribute('disabled', 'disabled');
    }
}

/**
 * Hide loading state
 * @param {Element|string} element - Element or selector
 */
function hideLoading(element) {
    const el = typeof element === 'string' ? $(element) : element;
    if (el) {
        removeClass(el, 'loading');
        el.removeAttribute('disabled');
    }
}

/**
 * Show toast/notification message
 * @param {string} message - Message text
 * @param {string} type - Type: 'success', 'error', 'info', 'warning'
 * @param {number} duration - Duration in milliseconds
 */
function showToast(message, type = 'info', duration = 3000) {
    // Create toast element
    const toast = createElement('div', {
        className: `toast toast-${type}`
    }, message);

    document.body.appendChild(toast);

    // Show with animation
    setTimeout(() => addClass(toast, 'show'), 10);

    // Remove after duration
    setTimeout(() => {
        removeClass(toast, 'show');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

/**
 * Debounce function - delays execution until after wait time
 * Perfect for search inputs, window resize, etc.
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in milliseconds
 * @returns {Function} Debounced function
 */
function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle function - limits execution frequency
 * Perfect for scroll events, mousemove, etc.
 * @param {Function} func - Function to throttle
 * @param {number} limit - Minimum time between executions in milliseconds
 * @returns {Function} Throttled function
 */
function throttle(func, limit = 300) {
    let inThrottle;
    return function executedFunction(...args) {
        if (!inThrottle) {
            func(...args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Escape HTML to prevent XSS attacks
 * @param {string} text - Text to escape
 * @returns {string} Escaped HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Open modal dialog
 * @param {Element|string} modal - Modal element or ID
 */
function openModal(modal) {
    const el = typeof modal === 'string' ? document.getElementById(modal) : modal;
    if (el) {
        el.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Close modal dialog
 * @param {Element|string} modal - Modal element or ID
 */
function closeModal(modal) {
    const el = typeof modal === 'string' ? document.getElementById(modal) : modal;
    if (el) {
        el.style.display = 'none';
        document.body.style.overflow = '';
    }
}

/**
 * Get form input value
 * @param {string} id - Element ID
 * @returns {string} Input value
 */
function getValue(id) {
    const el = document.getElementById(id);
    return el ? el.value : '';
}

/**
 * Set form input value
 * @param {string} id - Element ID
 * @param {string} value - Value to set
 */
function setValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value;
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        domReady,
        $,
        $$,
        createElement,
        show,
        hide,
        toggle,
        addClass,
        removeClass,
        toggleClass,
        setText,
        setHTML,
        showLoading,
        hideLoading,
        showToast,
        debounce,
        throttle,
        escapeHtml,
        openModal,
        closeModal,
        getValue,
        setValue
    };
}



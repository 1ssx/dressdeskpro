/**
 * API Client - Centralized Fetch Wrapper
 * PHASE 4 - JS Restructuring
 * 
 * Provides consistent API communication across all modules
 */

const API_BASE_URL = 'api/';

/**
 * Default fetch options
 */
const defaultOptions = {
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
};

/**
 * Make API request with error handling
 * @param {string} endpoint - API endpoint (relative to api/)
 * @param {Object} options - Fetch options
 * @returns {Promise<Object>} Parsed JSON response
 */
async function apiRequest(endpoint, options = {}) {
    // Check if endpoint is absolute or explicitly relative
    const isAbsoluteOrRelative = endpoint.startsWith('http') ||
        endpoint.startsWith('/') ||
        endpoint.startsWith('./') ||
        endpoint.startsWith('../');

    const url = isAbsoluteOrRelative ? endpoint : API_BASE_URL + endpoint;

    const config = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...(options.headers || {})
        }
    };

    try {
        const response = await fetch(url, config);

        // Get response text first to check content type
        const text = await response.text();

        // Handle non-JSON responses
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            console.error('Non-JSON response received:', text.substring(0, 500));
            throw new Error(`Expected JSON response, got ${contentType}. First 200 chars: ${text.substring(0, 200)}`);
        }

        // Parse as JSON
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('JSON parse error. Response text:', text.substring(0, 500));
            throw new Error(`Invalid JSON response: ${parseError.message}`);
        }

        // Check for API-level errors
        if (data.status === 'error') {
            throw new Error(data.message || 'API error occurred');
        }

        // Return data (unwrap if wrapped in 'data' property)
        return data.data !== undefined ? data.data : data;

    } catch (error) {
        console.error('API Request Error:', error);
        throw error;
    }
}

/**
 * GET request
 * @param {string} endpoint - API endpoint
 * @param {Object} params - Query parameters
 * @returns {Promise<Object>}
 */
async function apiGet(endpoint, params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const url = queryString ? `${endpoint}?${queryString}` : endpoint;

    return apiRequest(url, {
        method: 'GET'
    });
}

/**
 * POST request
 * @param {string} endpoint - API endpoint
 * @param {Object} data - Request body
 * @returns {Promise<Object>}
 */
async function apiPost(endpoint, data = {}) {
    return apiRequest(endpoint, {
        method: 'POST',
        body: JSON.stringify(data)
    });
}

/**
 * PUT request
 * @param {string} endpoint - API endpoint
 * @param {Object} data - Request body
 * @returns {Promise<Object>}
 */
async function apiPut(endpoint, data = {}) {
    return apiRequest(endpoint, {
        method: 'PUT',
        body: JSON.stringify(data)
    });
}

/**
 * DELETE request
 * @param {string} endpoint - API endpoint
 * @param {Object} data - Request body (optional)
 * @returns {Promise<Object>}
 */
async function apiDelete(endpoint, data = null) {
    const options = {
        method: 'DELETE'
    };

    if (data) {
        options.body = JSON.stringify(data);
    }

    return apiRequest(endpoint, options);
}

/**
 * Handle API errors with user-friendly messages
 * @param {Error} error - Error object
 * @param {Function} callback - Optional callback function
 */
function handleApiError(error, callback = null) {
    const message = error.message || 'حدث خطأ في الاتصال بالسيرفر';

    if (callback && typeof callback === 'function') {
        callback(message);
    } else {
        console.error('API Error:', message);
        // Default: show alert (can be replaced with toast/notification)
        alert(message);
    }
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { apiGet, apiPost, apiPut, apiDelete, apiRequest, handleApiError };
}


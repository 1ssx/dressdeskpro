/**
 * Global Search Module - Handles smart search for Invoices and Customers
 * Dependencies: index-style.css (or search.css)
 */

document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('global-search-input');

    if (!searchInput) {
        console.warn('Global search input not found: #global-search-input');
        return;
    }

    const searchContainer = searchInput.parentElement; // .top-search-bar
    let resultsDropdown = null;
    let searchTimeout = null;

    // Create dropdown element
    function createDropdown() {
        const dropdown = document.createElement('div');
        dropdown.className = 'search-results-dropdown';
        searchContainer.appendChild(dropdown);
        return dropdown;
    }

    // Debounced Search
    searchInput.addEventListener('input', function (e) {
        const query = e.target.value.trim();

        // Clear existing timeout
        if (searchTimeout) clearTimeout(searchTimeout);

        if (query.length < 2) {
            hideResults();
            return;
        }

        // Set debounce (400ms)
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 400);
    });

    // Handle focus to reshow results if they exist
    searchInput.addEventListener('focus', function () {
        if (searchInput.value.trim().length >= 2 && resultsDropdown && resultsDropdown.innerHTML !== '') {
            resultsDropdown.classList.add('active');
        }
    });

    // Close on click outside
    document.addEventListener('click', function (e) {
        if (!searchContainer.contains(e.target)) {
            hideResults();
        }
    });

    async function performSearch(query) {
        try {
            // Show loading state (optional)

            // Determine API path (Works if we are in public/ directory)
            const apiPath = 'api/global_search.php';

            const response = await fetch(`${apiPath}?q=${encodeURIComponent(query)}`);
            if (!response.ok) throw new Error('Search failed');

            const data = await response.json();

            if (data.status === 'success') {
                displayResults(data.results);
            } else {
                console.error('Search API error:', data.message);
            }
        } catch (error) {
            console.error('Search error:', error);
        }
    }

    function displayResults(results) {
        if (!resultsDropdown) {
            resultsDropdown = createDropdown();
        }

        resultsDropdown.innerHTML = '';

        if (results.length === 0) {
            resultsDropdown.innerHTML = `
                <div class="search-no-results">
                    لا توجد نتائج مطابقة
                </div>
            `;
        } else {
            results.forEach(result => {
                const item = document.createElement('a');
                item.className = 'search-result-item';
                item.href = result.link;
                item.classList.add(`search-result-type-${result.type}`);

                // Icon based on type
                let iconClass = 'fa-search';
                if (result.type === 'invoice') iconClass = 'fa-file-invoice';
                if (result.type === 'customer') iconClass = 'fa-user';

                item.innerHTML = `
                    <div class="search-result-icon">
                        <i class="fas ${iconClass}"></i>
                    </div>
                    <div class="search-result-info">
                        <span class="search-result-title">${escapeHtml(result.title)}</span>
                        <span class="search-result-subtitle">${escapeHtml(result.subtitle || '')}</span>
                    </div>
                    <i class="fas fa-arrow-left" style="color: #ddd; font-size: 0.8rem;"></i>
                `;

                resultsDropdown.appendChild(item);
            });
        }

        resultsDropdown.classList.add('active');
    }

    function hideResults() {
        if (resultsDropdown) {
            resultsDropdown.classList.remove('active');
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});

// main.js copied from public/assets/js/index-javascript.js
// This provides core UI behaviors for new-index.html and new-customer.html

document.addEventListener('DOMContentLoaded', function() {
    // reuse the same behaviors from index-javascript.js
    try {
        const storedName = localStorage.getItem('userName');
        if (storedName) {
            const userInfo = document.querySelector('.user-info');
            if (userInfo) {
                const span = userInfo.querySelector('span');
                if (span) {
                    span.textContent = `�?�?�?�?�? ${storedName}`;
                }
            }
        } else {
            const doFetchUser = () => {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 1500);

                fetch('api/get_user.php', { signal: controller.signal })
                    .then(res => res.json())
                    .then(json => {
                        clearTimeout(timeoutId);
                        if (json && json.status === 'success' && json.name) {
                            try {
                                localStorage.setItem('userName', json.name);
                            } catch (e) {
                                console.warn('Could not save userName to localStorage', e);
                            }

                            const userInfo = document.querySelector('.user-info');
                            if (userInfo) {
                                const span = userInfo.querySelector('span');
                                if (span) span.textContent = `�?�?�?�?�? ${json.name}`;
                            }
                        }
                    })
                    .catch(err => console.debug('get_user failed/aborted:', err));
            };

            if ('requestIdleCallback' in window) {
                requestIdleCallback(doFetchUser, { timeout: 500 });
            } else {
                setTimeout(doFetchUser, 80);
            }
        }
    } catch (e) {
        console.warn('Unable to read userName from localStorage', e);
    }

    // Tabs and general UI behaviors (same as index-javascript.js)
    const tabs = document.querySelectorAll('.tab');
    const pageContents = document.querySelectorAll('.page-content');
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            tabs.forEach(t => t.classList.remove('active'));
            pageContents.forEach(content => content.classList.remove('active'));
            this.classList.add('active');
            const target = document.getElementById(tabId + '-page');
            if (target) target.classList.add('active');
        });
    });

    if (window.location.hash) {
        const hash = window.location.hash.substring(1);
        const tab = document.querySelector(`.tab[data-tab="${hash}"]`);
        if (tab) tab.click();
    }

    // modal handling (add-dress-modal)
    const modal = document.getElementById('add-dress-modal');
    const addDressBtns = document.querySelectorAll('#add-dress-btn');
    const closeBtn = document.querySelector('.close');
    if (modal && closeBtn) {
        addDressBtns.forEach(btn => btn.addEventListener('click', () => modal.style.display = 'block'));
        closeBtn.addEventListener('click', () => modal.style.display = 'none');
        window.addEventListener('click', function(event) { if (event.target === modal) modal.style.display = 'none'; });
    }

    // form submission - add-dress-form
    const addDressForm = document.getElementById('add-dress-form');
    if (addDressForm) {
        addDressForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const name = document.getElementById('dress-name').value;
            const category = document.getElementById('dress-category').value;
            const price = document.getElementById('dress-price').value;
            const size = document.getElementById('dress-size').value;
            const color = document.getElementById('dress-color').value;
            const quantity = document.getElementById('dress-quantity').value;
            addDressToTable(name, getCategoryName(category), price, size, color, quantity);
            addDressForm.reset();
            if (modal) modal.style.display = 'none';
        });
    }

    function addDressToTable(name, category, price, size, color, quantity) {
        const table = document.querySelector('.inventory-table tbody');
        if (!table) return;
        const newRow = document.createElement('tr');
        const status = quantity > 0 ? 'available' : 'unavailable';
        const statusText = quantity > 0 ? '�?�?�?�?' : '�?�?�? �?�?�?�?';
        newRow.innerHTML = `...`;
        table.appendChild(newRow);
    }

    function getCategoryName(value) {
        switch(value) {
            case 'wedding': return '�?�?�?�?�?�? ���?�?�?';
            case 'evening': return '�?�?�?�?�?�? �?�?�?�?';
            case 'engagement': return '�?�?�?�?�?�? �?���?�?�?';
            default: return '';
        }
    }

});

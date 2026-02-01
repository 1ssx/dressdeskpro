// انتظر حتى يتم تحميل المستند بالكامل
document.addEventListener('DOMContentLoaded', function() {
    
    // --- 1. إعداد اسم المستخدم (من التخزين المحلي فقط) ---
    try {
        const storedName = localStorage.getItem('userName');
        if (storedName) {
            const userInfo = document.querySelector('.user-info');
            if (userInfo) {
                const span = userInfo.querySelector('span');
                if (span) {
                    span.textContent = `مرحبا ${storedName}`;
                }
            }
        }
    } catch (e) {
        console.warn('Unable to read userName from localStorage', e);
    }

    // --- 2. منطق التبويبات الداخلية (Tabs Logic) ---
    // هذا الجزء خاص فقط بالأزرار الصغيرة الموجودة في وسط الصفحة (لوحة التحكم، المخزون، العملاء...)
    const tabs = document.querySelectorAll('.tab');
    const pageContents = document.querySelectorAll('.page-content');
    
    function activateTab(tabId) {
        tabs.forEach(t => t.classList.remove('active'));
        pageContents.forEach(content => content.classList.remove('active'));
        
        const targetTab = document.querySelector(`.tab[data-tab="${tabId}"]`);
        if (targetTab) {
            targetTab.classList.add('active');
        }

        const targetPage = document.getElementById(tabId + '-page');
        if (targetPage) {
            targetPage.classList.add('active');
        }
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            activateTab(tabId);
        });
    });

    // --- 3. (تم الحذف) ---
    // تم حذف الكود الذي كان يمنع الروابط العلوية من العمل.
    // الآن عند الضغط على "إدارة المخزون" في الأعلى، سيأخذك المتصفح إلى ملف inventory.html مباشرة.

    
    // --- 4. وظائف الأزرار السريعة (Quick Actions) ---
    // زر إضافة فستان جديد - إعادة توجيه إلى صفحة المخزون
    const addDressBtns = document.querySelectorAll('#add-dress-btn, #add-dress-btn-2'); 
    addDressBtns.forEach(btn => {
        if (btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                // إعادة توجيه إلى صفحة المخزون مع معامل لفتح نافذة إضافة فستان
                window.location.href = 'inventory.php?open=add-dress';
            });
        }
    });

    // زر إضافة عميل جديد - إعادة توجيه إلى صفحة العملاء
    const addCustomerBtnIndex = document.getElementById('add-customer-btn');
    if (addCustomerBtnIndex) {
        addCustomerBtnIndex.addEventListener('click', function(e) {
            e.preventDefault();
            // إعادة توجيه إلى صفحة العملاء مع معامل لفتح نافذة إضافة عميل
            window.location.href = 'customer.php?open=add-customer';
        });
    }
    
    // معالجة النافذة المنبثقة إذا كانت موجودة (لصفحات أخرى)
    const modal = document.getElementById('add-dress-modal');
    const closeBtn = document.querySelector('.close');
    
    if (modal && closeBtn) {
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
    
    // --- 5. معالجة نموذج إضافة فستان ---
    const addDressForm = document.getElementById('add-dress-form');
    
    if (addDressForm) {
        addDressForm.addEventListener('submit', function(event) {
            event.preventDefault(); 
            
            const nameInput = document.getElementById('dress-name');
            const categoryInput = document.getElementById('dress-category');
            const priceInput = document.getElementById('dress-price');
            const sizeInput = document.getElementById('dress-size');
            const colorInput = document.getElementById('dress-color');
            const quantityInput = document.getElementById('dress-quantity');

            if (nameInput && categoryInput && priceInput) {
                addDressToTable(
                    nameInput.value, 
                    getCategoryName(categoryInput.value), 
                    priceInput.value, 
                    sizeInput ? sizeInput.value : '-', 
                    colorInput ? colorInput.value : '-', 
                    quantityInput ? quantityInput.value : 1
                );
                
                addDressForm.reset();
                if(modal) modal.style.display = 'none';
            }
        });
    }
    
    // --- 6. وظيفة إضافة صف للجدول ---
    function addDressToTable(name, category, price, size, color, quantity) {
        const table = document.querySelector('.inventory-table tbody');
        if (!table) return;
        
        const newRow = document.createElement('tr');
        const status = quantity > 0 ? 'available' : 'unavailable';
        const statusText = quantity > 0 ? 'متاح' : 'غير متاح';
        
        newRow.innerHTML = `
            <td><img src="https://via.placeholder.com/50" alt="${name}" width="50" height="50"></td>
            <td>${name}</td>
            <td>${category}</td>
            <td>${price} ريال</td>
            <td>${size}</td>
            <td>${color}</td>
            <td>${quantity}</td>
            <td><span class="status ${status}">${statusText}</span></td>
            <td class="actions">
                <div class="action-btn view-btn"><i class="fas fa-eye"></i></div>
                <div class="action-btn edit-btn"><i class="fas fa-edit"></i></div>
                <div class="action-btn delete-btn"><i class="fas fa-trash"></i></div>
            </td>
        `;
        
        setupRowActions(newRow);
        table.appendChild(newRow);
    }
    
    function getCategoryName(value) {
        switch(value) {
            case 'wedding': return 'فساتين زفاف';
            case 'evening': return 'فساتين سهرة';
            case 'engagement': return 'فساتين خطوبة';
            default: return value;
        }
    }

    function getCategoryValue(categoryText) {
        if (!categoryText) return '';
        categoryText = categoryText.trim().toLowerCase();
        if (categoryText.includes('زفاف')) return 'wedding';
        if (categoryText.includes('سهرة')) return 'evening';
        if (categoryText.includes('خطوبة')) return 'engagement';
        return '';
    }

    function setupRowActions(row) {
        const deleteBtn = row.querySelector('.delete-btn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                if (confirm('هل أنت متأكد من حذف هذا الفستان؟')) {
                    row.remove();
                }
            });
        }

        const viewBtn = row.querySelector('.view-btn');
        if (viewBtn) {
            viewBtn.addEventListener('click', function() {
                const cells = row.cells;
                if (cells && cells.length > 3) {
                    alert(`تفاصيل الفستان:\nالاسم: ${cells[1].textContent}\nالفئة: ${cells[2].textContent}\nالسعر: ${cells[3].textContent}`);
                }
            });
        }
    }

    const existingRows = document.querySelectorAll('.inventory-table tbody tr');
    existingRows.forEach(row => setupRowActions(row));
    
    // --- 7. البحث والتصفية ---
    const searchInput = document.querySelector('.search-box input');
    
    function filterItems() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const categorySelect = document.querySelector('.filter-select:nth-child(2) select');
        const sizeSelect = document.querySelector('.filter-select:nth-child(3) select');
        
        const selectedCategory = categorySelect ? categorySelect.value : '';
        const selectedSize = sizeSelect ? sizeSelect.value : '';
        
        const rows = document.querySelectorAll('.inventory-table tbody tr');
        
        rows.forEach(row => {
            const name = row.cells[1].textContent.toLowerCase();
            const categoryText = row.cells[2].textContent;
            const size = row.cells[4].textContent;
            
            const matchesSearch = name.includes(searchTerm) || categoryText.includes(searchTerm);
            const matchesCategory = selectedCategory === '' || getCategoryValue(categoryText) === selectedCategory;
            const matchesSize = selectedSize === '' || size === selectedSize;
            
            if (matchesSearch && matchesCategory && matchesSize) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    if (searchInput) searchInput.addEventListener('input', filterItems);
    const categoryFilter = document.querySelector('.filter-select:nth-child(2) select');
    if (categoryFilter) categoryFilter.addEventListener('change', filterItems);
    const sizeFilter = document.querySelector('.filter-select:nth-child(3) select');
    if (sizeFilter) sizeFilter.addEventListener('change', filterItems);
});
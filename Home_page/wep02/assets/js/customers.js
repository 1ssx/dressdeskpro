// Note: debounce() is available globally from core/dom-utils.js

document.addEventListener('DOMContentLoaded', function () {
    // --- DOM Elements ---
    const tableBody = document.getElementById('customers-table-body');
    const cardsContainer = document.getElementById('customers-cards-container');

    const addCustomerBtn = document.getElementById('add-customer-btn');
    const modal = document.getElementById('customer-modal');
    const modalTitle = document.getElementById('modal-title');
    const modalCloseBtn = modal ? modal.querySelector('.close') : null;
    const cancelBtn = document.getElementById('cancel-btn');
    const customerForm = document.getElementById('customer-form');

    const detailsModal = document.getElementById('customer-details-modal');
    const detailsCloseBtn = detailsModal ? detailsModal.querySelector('.close') : null;
    const editCustomerBtn = document.getElementById('edit-customer-btn');
    const addPurchaseBtn = document.getElementById('add-purchase-btn');
    const deleteCustomerBtn = document.getElementById('delete-customer-btn');

    const deleteConfirmModal = document.getElementById('delete-confirm-modal');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    const cancelDeleteBtn = document.getElementById('cancel-delete-btn');

    const viewToggleBtns = document.querySelectorAll('.view-toggle .view-btn');
    const tableView = document.querySelector('.customers-table-view');
    const cardsView = document.querySelector('.customers-cards-view');

    // بحث وفلترة
    const searchInput = document.getElementById('customer-search');
    const typeFilter = document.getElementById('customer-type-filter');
    const sortFilter = document.getElementById('customer-sort');

    // إحصائيات
    const statTotal = document.getElementById('stat-total');
    const statNew = document.getElementById('stat-new');
    const statVip = document.getElementById('stat-vip');
    const statActive = document.getElementById('stat-active');

    // متغيّرات الحالة
    let allCustomers = [];
    let selectedCustomerId = null;
    let customerIdToDelete = null;

    // --- Load Customers ---
    async function loadCustomers() {
        if (!tableBody) return;

        tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center;">جارِ التحميل...</td></tr>';

        try {
            const customers = await apiGet('./customers_api.php');
            if (customers && Array.isArray(customers)) {
                allCustomers = customers;
                renderCustomers(allCustomers);
                updateStats(allCustomers);
            } else {
                throw new Error('بيانات غير صحيحة');
            }
        } catch (error) {
            console.error('فشل الجلب:', error);
            tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center; color:red;">فشل جلب البيانات</td></tr>';
            handleApiError(error);
        }
    }

    // --- Statistics ---
    function updateStats(customers) {
        if (!customers || !Array.isArray(customers)) return;

        if (statTotal) statTotal.textContent = customers.length;

        const currentMonth = new Date().getMonth();
        const currentYear = new Date().getFullYear();

        const newCustomersCount = customers.filter(c => {
            if (!c.created_at) return false;
            const regDate = new Date(c.created_at);
            return regDate.getMonth() === currentMonth && regDate.getFullYear() === currentYear;
        }).length;

        if (statNew) statNew.textContent = newCustomersCount;

        const vipCount = customers.filter(c => c.type === 'vip').length;
        if (statVip) statVip.textContent = vipCount;

        const activeCount = customers.length; // حالياً كل العملاء نشطين
        if (statActive) statActive.textContent = activeCount;
    }

    // --- Render ---
    function renderCustomers(customers) {
        if (!tableBody || !cardsContainer) return;

        tableBody.innerHTML = '';
        cardsContainer.innerHTML = '';

        if (!customers || customers.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" style="text-align:center;">لا يوجد عملاء لعرضهم</td></tr>';
            return;
        }

        customers.forEach(customer => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><div class="customer-avatar-small"><i class="fas fa-user"></i></div></td>
                <td>${customer.name || ''}</td>
                <td>${customer.phone_1 || ''}</td>
                <td>${customer.phone_2 || '-'}</td>
                <td>${customer.created_at ? new Date(customer.created_at).toLocaleDateString('ar-EG') : '-'}</td>
                <td><span class="status-badge status-${customer.type || 'regular'}">${getTypeName(customer.type)}</span></td>
                <td class="actions">
                    <button class="action-btn view-btn" onclick="viewCustomer(${customer.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="action-btn delete-btn" onclick="deleteCustomer(${customer.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tableBody.appendChild(row);

            const card = document.createElement('div');
            card.className = 'customer-card';
            card.innerHTML = `
                <div class="card-header">
                    <div class="card-avatar"><i class="fas fa-user-tie"></i></div>
                    <div class="card-title">
                        <h3>${customer.name || 'عميل'}</h3>
                        <span class="customer-type type-${customer.type || 'regular'}">${getTypeName(customer.type)}</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="info-item">
                        <i class="fas fa-phone"></i> 
                        <span>${customer.phone_1 || 'لا يوجد رقم'}</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i> 
                        <span>${customer.address || 'العنوان غير محدد'}</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-calendar-alt"></i> 
                        <span>${customer.created_at ? new Date(customer.created_at).toLocaleDateString('ar-EG') : '-'}</span>
                    </div>
                </div>
                <div class="card-actions">
                    <button class="card-btn view-btn" onclick="viewCustomer(${customer.id})">
                        <i class="fas fa-eye"></i> عرض
                    </button>
                    <button class="card-btn edit-btn" onclick="openEditCustomerModal({
                        id: '${customer.id}',
                        name: '${customer.name || ''}', 
                        phone_1: '${customer.phone_1 || ''}',
                        phone_2: '${customer.phone_2 || ''}',
                        address: '${customer.address || ''}',
                        type: '${customer.type || 'regular'}',
                        notes: '${customer.notes || ''}'
                    })">
                        <i class="fas fa-edit"></i> تعديل
                    </button>
                     <button class="card-btn delete-btn" onclick="deleteCustomer(${customer.id})">
                        <i class="fas fa-trash"></i> حذف
                    </button>
                </div>
            `;
            cardsContainer.appendChild(card);
        });
    }

    // =========================
    // 4. إضافة / تعديل عميل
    // =========================
    function openAddCustomerModal() {
        console.log('🔓 openAddCustomerModal called');
        if (!modal || !customerForm) {
            console.error('❌ Modal or form not found');
            return;
        }
        customerForm.reset();
        const idInput = document.getElementById('customer-id');
        if (idInput) idInput.value = '';
        if (modalTitle) modalTitle.textContent = 'إضافة عميل جديد';
        modal.style.display = 'block';
    }

    // Make function globally accessible
    window.openAddCustomerModal = openAddCustomerModal;

    function openEditCustomerModal(customer) {
        if (!modal || !customerForm || !customer) return;

        const idInput = document.getElementById('customer-id');
        const nameInput = document.getElementById('customer-name');
        const phone1Input = document.getElementById('customer-phone');
        const phone2Input = document.getElementById('customer-phone-2');
        const addressInput = document.getElementById('customer-address');
        const typeSelect = document.getElementById('customer-type');
        const notesInput = document.getElementById('customer-notes');

        if (idInput) idInput.value = customer.id || '';
        if (nameInput) nameInput.value = customer.name || '';
        if (phone1Input) phone1Input.value = customer.phone_1 || '';
        if (phone2Input) phone2Input.value = customer.phone_2 || '';
        if (addressInput) addressInput.value = customer.address || '';
        if (typeSelect) typeSelect.value = customer.type || 'regular';
        if (notesInput) notesInput.value = customer.notes || '';

        if (modalTitle) modalTitle.textContent = 'تعديل بيانات العميل';
        modal.style.display = 'block';
    }

    if (customerForm) {
        customerForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const idInput = document.getElementById('customer-id');
            const nameInput = document.getElementById('customer-name');
            const phone1Input = document.getElementById('customer-phone');
            const phone2Input = document.getElementById('customer-phone-2');
            const addressInput = document.getElementById('customer-address');
            const typeSelect = document.getElementById('customer-type');
            const notesInput = document.getElementById('customer-notes');

            const payload = {
                name: nameInput ? nameInput.value.trim() : '',
                phone_1: phone1Input ? phone1Input.value.trim() : '',
                phone_2: phone2Input ? phone2Input.value.trim() : '',
                address: addressInput ? addressInput.value.trim() : '',
                type: typeSelect ? typeSelect.value : 'regular',
                notes: notesInput ? notesInput.value.trim() : ''
            };

            const isEdit = idInput && idInput.value;
            if (isEdit) {
                payload.action = 'update';
                payload.id = idInput.value;
            } else {
                // Ensure action is explicitly set for create if needed by PHP, or PHP defaults to create
                // customers_api.php usually handles POST as create/update based on ID or action
                // Let's add action just in case validation expects it
                payload.action = 'create';
            }

            try {
                await apiPost('./customers_api.php', payload);
                alert(isEdit ? '✅ تم تحديث بيانات العميل بنجاح' : '✅ تم إضافة العميل بنجاح');
                modal.style.display = 'none';
                customerForm.reset();
                loadCustomers();
            } catch (error) {
                console.error(error);
                handleApiError(error);
            }
        });
    }

    // =========================
    // 5. عرض التفاصيل (نافذة العين)
    // =========================
    window.viewCustomer = function (id) {
        if (!detailsModal) return;

        const customer = allCustomers.find(c => String(c.id) === String(id));
        if (!customer) return;

        selectedCustomerId = customer.id;

        const nameElem = document.getElementById('detail-customer-name');
        const typeElem = document.getElementById('detail-customer-type');
        const phone1Elem = document.getElementById('detail-customer-phone');
        const phone2Elem = document.getElementById('detail-customer-phone-2');
        const addressElem = document.getElementById('detail-customer-address');
        const notesElem = document.getElementById('detail-customer-notes');

        if (nameElem) nameElem.textContent = customer.name || '';
        if (typeElem) typeElem.textContent = getTypeName(customer.type);
        if (phone1Elem) phone1Elem.textContent = customer.phone_1 || '';
        if (phone2Elem) phone2Elem.textContent = customer.phone_2 || '-';
        if (addressElem) addressElem.textContent = customer.address || '-';
        if (notesElem) notesElem.textContent = customer.notes || 'لا توجد ملاحظات';

        detailsModal.style.display = 'block';
    };

    // زر تعديل داخل نافذة التفاصيل
    if (editCustomerBtn) {
        editCustomerBtn.addEventListener('click', function () {
            if (!selectedCustomerId) return;
            const customer = allCustomers.find(c => String(c.id) === String(selectedCustomerId));
            if (!customer) return;

            detailsModal.style.display = 'none';
            openEditCustomerModal(customer);
        });
    }

    // زر إضافة مشتريات داخل نافذة التفاصيل (حاليًا فقط تنبيه)
    if (addPurchaseBtn) {
        addPurchaseBtn.addEventListener('click', function () {
            alert('ميزة إضافة المشتريات سيتم ربطها لاحقاً 🤝');
        });
    }

    // =========================
    // 6. الحذف + نافذة التأكيد
    // =========================
    async function performDelete(id) {
        if (!id) return;

        try {
            await apiPost('./customers_api.php', { action: 'delete', id: id });
            alert('تم حذف العميل بنجاح');
            loadCustomers();
        } catch (error) {
            console.error(error);
            handleApiError(error);
        }
    }

    // تُستدعى من أزرار الحذف في الجدول/الكروت
    window.deleteCustomer = function (id) {
        if (!deleteConfirmModal) {
            // في حال ما كانت نافذة التأكيد موجودة نستخدم confirm العادي
            if (confirm('هل أنت متأكد من حذف هذا العميل نهائياً؟')) {
                performDelete(id);
            }
            return;
        }

        customerIdToDelete = id;
        deleteConfirmModal.style.display = 'block';
    };

    if (deleteCustomerBtn) {
        deleteCustomerBtn.addEventListener('click', function () {
            if (!selectedCustomerId) return;
            window.deleteCustomer(selectedCustomerId);
        });
    }

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            if (customerIdToDelete) {
                performDelete(customerIdToDelete);
            }
            customerIdToDelete = null;
            if (deleteConfirmModal) deleteConfirmModal.style.display = 'none';
            if (detailsModal) detailsModal.style.display = 'none';
        });
    }

    if (cancelDeleteBtn) {
        cancelDeleteBtn.addEventListener('click', function () {
            customerIdToDelete = null;
            if (deleteConfirmModal) deleteConfirmModal.style.display = 'none';
        });
    }

    // =========================
    // 7. البحث / الفلترة / طريقة العرض
    // =========================
    function getTypeName(type) {
        switch (type) {
            case 'vip': return 'عميل مميز';
            case 'new': return 'عميل جديد';
            case 'regular': return 'عميل عادي';
            default: return type || 'عميل';
        }
    }

    function filterCustomers() {
        if (!allCustomers || !Array.isArray(allCustomers)) return;

        const term = (searchInput ? searchInput.value : '').toLowerCase();
        const type = typeFilter ? typeFilter.value : '';
        const sort = sortFilter ? sortFilter.value : '';

        let filtered = allCustomers.filter(c => {
            const name = (c.name || '').toLowerCase();
            const phone = c.phone_1 || '';
            const matchesTerm = !term || name.includes(term) || phone.includes(term);
            const matchesType = !type || c.type === type;
            return matchesTerm && matchesType;
        });

        filtered.sort((a, b) => {
            if (sort === 'name-asc') return (a.name || '').localeCompare(b.name || '');
            if (sort === 'name-desc') return (b.name || '').localeCompare(a.name || '');
            if (sort === 'date-asc') return new Date(a.created_at || 0) - new Date(b.created_at || 0);
            if (sort === 'date-desc') return new Date(b.created_at || 0) - new Date(a.created_at || 0);
            return 0;
        });

        renderCustomers(filtered);
    }

    // Apply debouncing to search input for better performance
    // Debounce delays the filter until user stops typing (400ms)
    const debouncedFilterCustomers = debounce(filterCustomers, 400);

    if (searchInput) searchInput.addEventListener('input', debouncedFilterCustomers);
    if (typeFilter) typeFilter.addEventListener('change', filterCustomers);
    if (sortFilter) sortFilter.addEventListener('change', filterCustomers);

    if (viewToggleBtns && tableView && cardsView) {
        viewToggleBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                viewToggleBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const viewType = btn.getAttribute('data-view');
                if (viewType === 'table') {
                    tableView.classList.add('active');
                    cardsView.classList.remove('active');
                } else {
                    tableView.classList.remove('active');
                    cardsView.classList.add('active');
                }
            });
        });
    }

    // =========================
    // 8. فتح / إغلاق النوافذ
    // =========================
    console.log('🔍 Looking for add-customer-btn:', addCustomerBtn);
    if (addCustomerBtn) {
        console.log('✅ Add customer button found, attaching event listener');
        addCustomerBtn.addEventListener('click', openAddCustomerModal);
    } else {
        console.error('❌ Add customer button NOT found in DOM');
    }

    if (modalCloseBtn && modal) {
        modalCloseBtn.addEventListener('click', function () {
            modal.style.display = 'none';
        });
    }

    if (cancelBtn && modal) {
        cancelBtn.addEventListener('click', function () {
            modal.style.display = 'none';
        });
    }

    if (detailsCloseBtn && detailsModal) {
        detailsCloseBtn.addEventListener('click', function () {
            detailsModal.style.display = 'none';
        });
    }

    window.addEventListener('click', function (event) {
        if (event.target === modal) modal.style.display = 'none';
        if (event.target === detailsModal) detailsModal.style.display = 'none';
        if (event.target === deleteConfirmModal) deleteConfirmModal.style.display = 'none';
    });

    // =========================
    // 9. فتح نافذة الإضافة من ?open=add-customer
    // =========================
    try {
        const params = new URLSearchParams(window.location.search);
        const openParam = params.get('open');
        if (openParam === 'add-customer' || openParam === 'addCustomer') {
            openAddCustomerModal();
        }
    } catch (e) {
        console.warn(e);
    }

    // تشغيل أول تحميل
    loadCustomers();
});

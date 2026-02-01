/**
 * Bookings Management Module
 * Handles booking CRUD operations, calendar view, and filters
 */

document.addEventListener('DOMContentLoaded', function () {

    // API URLs (bookings.php is in public/ folder)
    // API URLs (Relative to api/ for standard APIs, explicitly relative for others)
    const BOOKINGS_API_URL = 'bookings.php';
    const CUSTOMERS_API_URL = './customers_api.php';
    const INVENTORY_API_URL = 'inventory.php';

    // DOM Elements
    const addBookingBtn = document.getElementById('add-booking-btn');
    const bookingModal = document.getElementById('booking-modal');
    const closeModalBtn = document.getElementById('close-modal-btn');
    const cancelBookingBtn = document.getElementById('cancel-booking-btn');
    const bookingForm = document.getElementById('booking-form');
    const bookingsTableBody = document.getElementById('bookings-table-body');
    const toggleViewBtn = document.getElementById('toggle-view-btn');
    const tableView = document.getElementById('table-view');
    const calendarView = document.getElementById('calendar-view');
    const calendarEl = document.getElementById('calendar');

    // Filter elements
    const filterDateFrom = document.getElementById('filter-date-from');
    const filterDateTo = document.getElementById('filter-date-to');
    const filterStatus = document.getElementById('filter-status');
    const filterBookingType = document.getElementById('filter-booking-type');
    const applyFiltersBtn = document.getElementById('apply-filters-btn');
    const clearFiltersBtn = document.getElementById('clear-filters-btn');

    // Stats elements
    const todayCountEl = document.getElementById('today-count');
    const weekCountEl = document.getElementById('week-count');
    const cancelledCountEl = document.getElementById('cancelled-count');
    const lateCountEl = document.getElementById('late-count');

    // Form elements
    const bookingIdInput = document.getElementById('booking-id');
    const bookingCustomerSelect = document.getElementById('booking-customer');
    const bookingTypeSelect = document.getElementById('booking-type');
    const bookingDateInput = document.getElementById('booking-date');
    const bookingStatusSelect = document.getElementById('booking-status');
    const bookingInvoiceSelect = document.getElementById('booking-invoice');
    const bookingNotesTextarea = document.getElementById('booking-notes');
    const modalTitle = document.getElementById('modal-title');

    // State
    let isEditMode = false;
    let currentBookingId = null;
    let calendar = null;
    let isCalendarView = false;
    let customersData = [];
    let dressesData = [];

    // --- Initialization ---

    init();

    async function init() {
        await loadCustomers();
        await loadDresses(); // Changed from loadInvoices to loadDresses
        await loadStats();
        await loadBookings();
        setupEventListeners();
    }

    // --- Event Listeners ---

    function setupEventListeners() {
        if (addBookingBtn) addBookingBtn.addEventListener('click', openAddBookingModal);
        if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
        if (cancelBookingBtn) cancelBookingBtn.addEventListener('click', closeModal);
        if (bookingForm) bookingForm.addEventListener('submit', handleSaveBooking);
        if (applyFiltersBtn) applyFiltersBtn.addEventListener('click', applyFilters);
        if (clearFiltersBtn) clearFiltersBtn.addEventListener('click', clearFilters);
        if (toggleViewBtn) toggleViewBtn.addEventListener('click', toggleView);

        // Close modal on outside click
        if (bookingModal) {
            bookingModal.addEventListener('click', function (e) {
                if (e.target === bookingModal) {
                    closeModal();
                }
            });
        }
    }

    // --- Data Loading ---

    /**
     * Load booking statistics
     */
    async function loadStats() {
        try {
            const stats = await apiGet(BOOKINGS_API_URL, { action: 'stats' });

            if (todayCountEl) todayCountEl.textContent = stats.today_count || 0;
            if (weekCountEl) weekCountEl.textContent = stats.week_count || 0;
            if (cancelledCountEl) cancelledCountEl.textContent = stats.cancelled_count || 0;
            if (lateCountEl) lateCountEl.textContent = stats.late_count || 0;
        } catch (error) {
            console.error('Failed to load stats:', error);
            if (todayCountEl) todayCountEl.textContent = 'خطأ';
            if (weekCountEl) weekCountEl.textContent = 'خطأ';
            if (cancelledCountEl) cancelledCountEl.textContent = 'خطأ';
            if (lateCountEl) lateCountEl.textContent = 'خطأ';
        }
    }

    /**
     * Load bookings list
     */
    async function loadBookings() {
        try {
            const params = {};

            if (filterDateFrom && filterDateFrom.value) params.date_from = filterDateFrom.value;
            if (filterDateTo && filterDateTo.value) params.date_to = filterDateTo.value;
            if (filterStatus && filterStatus.value) params.status = filterStatus.value;
            if (filterBookingType && filterBookingType.value) params.booking_type = filterBookingType.value;
            params.action = 'list';

            const bookings = await apiGet(BOOKINGS_API_URL, params);
            renderBookingsTable(bookings || []);

            // Update calendar if in calendar view
            if (isCalendarView && calendar) {
                updateCalendar(bookings || []);
            }
        } catch (error) {
            console.error('Failed to load bookings:', error);
            if (bookingsTableBody) {
                bookingsTableBody.innerHTML = '<tr><td colspan="9" class="loading">خطأ في تحميل البيانات</td></tr>';
            }
        }
    }

    /**
     * Load customers for dropdown
     */
    async function loadCustomers() {
        try {
            const customers = await apiGet(CUSTOMERS_API_URL);
            if (customers) {
                customersData = customers;
                populateCustomerDropdown();
            }
        } catch (error) {
            console.error('Failed to load customers:', error);
        }
    }

    /**
     * Load dresses from inventory for dropdown
     */
    async function loadDresses() {
        try {
            const dresses = await apiGet(INVENTORY_API_URL, { action: 'list' });
            if (dresses) {
                dressesData = Array.isArray(dresses) ? dresses : [];
                populateDressDropdown();
            }
        } catch (error) {
            console.error('Failed to load dresses:', error);
        }
    }

    // --- Rendering ---

    /**
     * Render bookings table
     */
    function renderBookingsTable(bookings) {
        if (!bookingsTableBody) return;

        if (bookings.length === 0) {
            bookingsTableBody.innerHTML = '<tr><td colspan="9" class="loading">لا توجد حجوزات</td></tr>';
            return;
        }

        bookingsTableBody.innerHTML = bookings.map(booking => {
            const bookingDate = new Date(booking.booking_date);
            const dateStr = bookingDate.toLocaleDateString('en-GB');
            const timeStr = bookingDate.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });

            const statusLabels = {
                'pending': 'قيد الانتظار',
                'confirmed': 'مؤكد',
                'completed': 'مكتمل',
                'cancelled': 'ملغي',
                'late': 'متأخر'
            };

            return `
                <tr>
                    <td>#${booking.id}</td>
                    <td>${escapeHtml(booking.customer_name)}</td>
                    <td>${escapeHtml(booking.phone_1 || '-')}</td>
                    <td>${escapeHtml(booking.booking_type)}</td>
                    <td>${dateStr} ${timeStr}</td>
                    <td><span class="status-badge ${booking.status}">${statusLabels[booking.status] || booking.status}</span></td>
                    <td>${booking.dress_name ? escapeHtml(booking.dress_name) : (booking.invoice_number ? `فاتورة ${escapeHtml(booking.invoice_number)}` : '-')}</td>
                    <td>${escapeHtml(booking.notes || '-')}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-action edit" onclick="window.editBooking(${booking.id})" title="تعديل">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-action delete" onclick="window.deleteBooking(${booking.id})" title="حذف">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    /**
     * Populate customer dropdown
     */
    function populateCustomerDropdown() {
        if (!bookingCustomerSelect) return;

        bookingCustomerSelect.innerHTML = '<option value="">اختر العميل</option>';
        customersData.forEach(customer => {
            const option = document.createElement('option');
            option.value = customer.id;
            option.textContent = `${customer.name} - ${customer.phone_1 || ''}`;
            bookingCustomerSelect.appendChild(option);
        });
    }

    /**
     * Populate dress dropdown (changed from invoice dropdown)
     */
    function populateDressDropdown() {
        if (!bookingInvoiceSelect) return;

        bookingInvoiceSelect.innerHTML = '<option value="">لا يوجد</option>';
        if (Array.isArray(dressesData) && dressesData.length > 0) {
            dressesData.forEach(dress => {
                const option = document.createElement('option');
                option.value = dress.id || dress.product_id || '';
                const dressName = dress.name || dress.product_name || 'فستان';
                const dressCode = dress.sku || dress.code || '';
                option.textContent = dressCode ? `${dressName} (${dressCode})` : dressName;
                bookingInvoiceSelect.appendChild(option);
            });
        }
    }

    // --- Modal Functions ---

    /**
     * Open add booking modal
     */
    function openAddBookingModal() {
        if (!bookingModal) return;

        isEditMode = false;
        currentBookingId = null;
        if (modalTitle) modalTitle.textContent = 'إضافة حجز جديد';
        if (bookingForm) bookingForm.reset();
        if (bookingIdInput) bookingIdInput.value = '';
        if (bookingStatusSelect) bookingStatusSelect.value = 'pending';

        // Set minimum date to today
        if (bookingDateInput) {
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            bookingDateInput.min = now.toISOString().slice(0, 16);
        }

        bookingModal.style.display = 'flex';
    }

    /**
     * Open edit booking modal
     */
    window.editBooking = async function (id) {
        if (!bookingModal) return;

        try {
            const booking = await apiGet(BOOKINGS_API_URL, { action: 'get', id: id });

            isEditMode = true;
            currentBookingId = id;
            if (modalTitle) modalTitle.textContent = 'تعديل حجز';

            if (bookingIdInput) bookingIdInput.value = booking.id;
            if (bookingCustomerSelect) bookingCustomerSelect.value = booking.customer_id;
            if (bookingTypeSelect) bookingTypeSelect.value = booking.booking_type;

            // Format datetime for input
            if (bookingDateInput) {
                const bookingDate = new Date(booking.booking_date);
                bookingDate.setMinutes(bookingDate.getMinutes() - bookingDate.getTimezoneOffset());
                bookingDateInput.value = bookingDate.toISOString().slice(0, 16);
            }

            if (bookingStatusSelect) bookingStatusSelect.value = booking.status;
            if (bookingInvoiceSelect) bookingInvoiceSelect.value = booking.invoice_id || '';
            if (bookingNotesTextarea) bookingNotesTextarea.value = booking.notes || '';

            bookingModal.style.display = 'flex';
        } catch (error) {
            console.error('Failed to load booking:', error);
            handleApiError(error);
        }
    };

    /**
     * Close modal
     */
    function closeModal() {
        if (bookingModal) bookingModal.style.display = 'none';
        if (bookingForm) bookingForm.reset();
        isEditMode = false;
        currentBookingId = null;
    }

    /**
     * Handle save booking (create or update)
     */
    async function handleSaveBooking(e) {
        e.preventDefault();

        // Validation
        if (!bookingCustomerSelect || !bookingCustomerSelect.value) {
            alert('يرجى اختيار العميل');
            return;
        }

        if (!bookingTypeSelect || !bookingTypeSelect.value) {
            alert('يرجى اختيار نوع الحجز');
            return;
        }

        if (!bookingDateInput || !bookingDateInput.value) {
            alert('يرجى اختيار التاريخ والوقت');
            return;
        }

        // Validate date is in future (only for new bookings, not edits)
        if (!isEditMode && bookingDateInput) {
            const selectedDate = new Date(bookingDateInput.value);
            const now = new Date();
            if (selectedDate <= now) {
                alert('يجب أن يكون التاريخ والوقت في المستقبل');
                return;
            }
        }

        const formData = {
            customer_id: parseInt(bookingCustomerSelect.value),
            booking_type: bookingTypeSelect.value,
            booking_date: bookingDateInput.value.replace('T', ' ') + ':00',
            status: bookingStatusSelect ? bookingStatusSelect.value : 'pending',
            invoice_id: bookingInvoiceSelect && bookingInvoiceSelect.value ? parseInt(bookingInvoiceSelect.value) : null,
            notes: bookingNotesTextarea ? (bookingNotesTextarea.value.trim() || null) : null
        };

        try {
            const saveBtn = document.getElementById('save-booking-btn');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
            }

            let url = BOOKINGS_API_URL;
            let action = isEditMode ? 'update' : 'create';

            // apiPost data handles body
            const data = { ...formData, action: action };
            if (isEditMode) data.id = currentBookingId;

            await apiPost(url, data);

            alert(isEditMode ? 'تم تحديث الحجز بنجاح' : 'تم إنشاء الحجز بنجاح');
            closeModal();
            await loadBookings();
            await loadStats();

            // Update calendar if in calendar view
            if (isCalendarView && calendar) {
                calendar.refetchEvents();
            }
        } catch (error) {
            console.error('Failed to save booking:', error);
            handleApiError(error);
        } finally {
            const saveBtn = document.getElementById('save-booking-btn');
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> حفظ';
            }
        }
    }

    // --- Delete ---

    /**
     * Delete booking
     */
    window.deleteBooking = async function (id) {
        if (!confirm('هل أنت متأكد من حذف هذا الحجز؟')) {
            return;
        }

        try {
            await apiDelete(BOOKINGS_API_URL, { action: 'delete', id: id });

            alert('تم حذف الحجز بنجاح');
            await loadBookings();
            await loadStats();

            // Update calendar if in calendar view
            if (isCalendarView && calendar) {
                calendar.refetchEvents();
            }
        } catch (error) {
            console.error('Failed to delete booking:', error);
            handleApiError(error);
        }
    };

    // --- Filters ---

    /**
     * Apply filters
     */
    function applyFilters() {
        loadBookings();
    }

    /**
     * Clear filters
     */
    function clearFilters() {
        if (filterDateFrom) filterDateFrom.value = '';
        if (filterDateTo) filterDateTo.value = '';
        if (filterStatus) filterStatus.value = '';
        if (filterBookingType) filterBookingType.value = '';
        loadBookings();
    }

    // --- Calendar ---

    /**
     * Toggle between table and calendar view
     */
    function toggleView() {
        isCalendarView = !isCalendarView;

        if (isCalendarView) {
            if (tableView) tableView.style.display = 'none';
            if (calendarView) calendarView.style.display = 'block';
            if (toggleViewBtn) toggleViewBtn.innerHTML = '<i class="fas fa-table"></i> عرض الجدول';

            if (!calendar && calendarEl) {
                initCalendar();
            } else if (calendar) {
                calendar.render();
            }
        } else {
            if (tableView) tableView.style.display = 'block';
            if (calendarView) calendarView.style.display = 'none';
            if (toggleViewBtn) toggleViewBtn.innerHTML = '<i class="fas fa-calendar-alt"></i> عرض التقويم';
        }
    }

    /**
     * Initialize FullCalendar
     */
    function initCalendar() {
        if (!calendarEl || typeof FullCalendar === 'undefined') return;

        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'ar',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: async function (fetchInfo, successCallback, failureCallback) {
                try {
                    const params = {
                        action: 'calendar',
                        start: fetchInfo.startStr,
                        end: fetchInfo.endStr
                    };

                    const events = await apiGet(BOOKINGS_API_URL, params);
                    successCallback(events || []);
                } catch (error) {
                    console.error('Failed to load calendar events:', error);
                    failureCallback(error);
                }
            },
            eventClick: function (info) {
                const bookingId = info.event.extendedProps.booking_id;
                if (bookingId && window.editBooking) {
                    window.editBooking(bookingId);
                }
            },
            eventContent: function (arg) {
                const props = arg.event.extendedProps;
                return {
                    html: `<div style="padding: 2px;">
                        <strong>${arg.event.title}</strong><br>
                        <small>${props.booking_type || ''}</small>
                    </div>`
                };
            }
        });

        calendar.render();
    }

    /**
     * Update calendar with bookings data
     */
    function updateCalendar(bookings) {
        if (!calendar) return;

        const events = bookings.map(booking => {
            const color = getStatusColor(booking.status);
            return {
                id: booking.id.toString(),
                title: `${booking.customer_name} - ${booking.booking_type}`,
                start: booking.booking_date,
                color: color,
                extendedProps: {
                    booking_id: booking.id,
                    customer_name: booking.customer_name,
                    booking_type: booking.booking_type,
                    status: booking.status
                }
            };
        });

        calendar.removeAllEvents();
        calendar.addEventSource(events);
    }

    /**
     * Get color for status
     */
    function getStatusColor(status) {
        const colors = {
            'pending': '#f39c12',
            'confirmed': '#3498db',
            'completed': '#27ae60',
            'cancelled': '#95a5a6',
            'late': '#e74c3c'
        };
        return colors[status] || '#3498db';
    }

    // Note: escapeHtml is now available globally from core/dom-utils.js

});

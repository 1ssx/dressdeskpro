/**
 * Inventory Management Module
 * Handles product CRUD, stock management, and category filtering
 */

document.addEventListener('DOMContentLoaded', function () {

    // --- 1. Settings ---
    const INVENTORY_API_URL = 'inventory.php';

    // --- Data Variables ---
    let inventoryData = [];
    let currentInventoryPage = 1;
    const itemsPerPage = 10;
    let selectedDressId = null;

    // --- DOM Elements ---
    const inventoryTableBody = document.getElementById("inventory-table-body");
    const paginationNumbers = document.getElementById("pagination-numbers");
    const prevPageBtn = document.getElementById("prev-page");
    const nextPageBtn = document.getElementById("next-page");

    // Filters
    const dressSearch = document.getElementById("dress-search");
    const categoryFilter = document.getElementById("category-filter");
    const statusFilter = document.getElementById("status-filter");

    // Modals
    const dressModal = document.getElementById("dress-modal");
    const dressForm = document.getElementById("dress-form");
    const dressModalTitle = document.getElementById("dress-modal-title");
    const addDressBtn = document.getElementById("add-dress-btn");
    const cancelDressBtn = document.getElementById("cancel-dress-btn");

    const dressDetailsModal = document.getElementById("dress-details-modal");
    const editDressBtn = document.getElementById("edit-dress-btn");
    const deleteDressBtn = document.getElementById("delete-dress-btn");
    const addStockBtn = document.getElementById("add-stock-btn");

    const addStockModal = document.getElementById("add-stock-modal");
    const addStockForm = document.getElementById("add-stock-form");
    const cancelStockBtn = document.getElementById("cancel-stock-btn");

    const deleteConfirmModal = document.getElementById("delete-confirm-modal");
    const confirmDeleteBtn = document.getElementById("confirm-delete-btn");
    const cancelDeleteBtn = document.getElementById("cancel-delete-btn");

    // Tabs
    const tabs = document.querySelectorAll(".tab");
    const tabContents = document.querySelectorAll(".tab-content");

    function getValue(id) {
        const el = typeof id === 'string' ? document.getElementById(id) : id;
        return el ? el.value : '';
    }

    function getCategoryName(cat) {
        if (cat == 1) return 'فساتين زفاف';
        if (cat == 2) return 'فساتين سهرة';
        if (cat == 3) return 'طرح';
        if (cat == 4) return 'اكسسوارات';
        if (cat == 5) return 'عبايات';
        return cat || '-';
    }

    // Note: escapeHtml, openModal, closeModal, formatCurrency, setValue, getValue 
    // are now available globally from core/dom-utils.js and core/formatters.js

    // --- 6. Event Listeners ---

    // Tabs
    tabs.forEach(tab => {
        tab.addEventListener("click", function () {
            tabs.forEach(t => t.classList.remove("active"));
            tabContents.forEach(c => c.classList.remove("active"));
            this.classList.add("active");
            const contentId = `${this.dataset.tab}-content`;
            const content = document.getElementById(contentId);
            if (content) content.classList.add("active");
        });
    });

    // Add Dress Button
    if (addDressBtn) {
        addDressBtn.addEventListener('click', function () {
            if (dressForm) dressForm.reset();
            setValue('dress-id', '');
            if (dressModalTitle) dressModalTitle.textContent = "إضافة فستان جديد";
            openModal(dressModal);
        });
    }

    // Close Buttons
    if (cancelDressBtn) cancelDressBtn.onclick = () => closeModal(dressModal);
    if (cancelStockBtn) cancelStockBtn.onclick = () => closeModal(addStockModal);
    if (cancelDeleteBtn) cancelDeleteBtn.onclick = () => closeModal(deleteConfirmModal);

    document.querySelectorAll('.close').forEach(el => {
        el.onclick = function () { closeModal(el.closest('.modal')); };
    });

    window.onclick = (e) => {
        if (e.target.classList.contains("modal")) closeModal(e.target);
    };

    // Filters
    if (dressSearch) dressSearch.addEventListener('input', renderInventory);
    if (categoryFilter) categoryFilter.addEventListener('change', renderInventory);
    if (statusFilter) statusFilter.addEventListener('change', renderInventory);

    // Initial Load
    console.log('Inventory page initialized.');
    loadAllData();
    setupSkuGeneration();

});
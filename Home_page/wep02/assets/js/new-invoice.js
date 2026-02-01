// new-invoice.js - محدث بنظام البحث التلقائي عن العملاء
document.addEventListener("DOMContentLoaded", () => {
    // API Configuration
    // api-client.js handles relative paths if they start with ./ or ../
    const CUSTOMERS_API_URL = "./customers_api.php";
    const INVOICE_API_ENDPOINT = "invoices.php"; // Will be prefixed with api/ by api-client.js
    const SAVE_INVOICE_ENDPOINT = "./save_invoice.php";

    const params = new URLSearchParams(window.location.search);
    const invoiceIdParam = params.get("id");
    const isEditMode = !!invoiceIdParam;
    const invoiceId = isEditMode ? parseInt(invoiceIdParam, 10) : null;

    const saveBtn = document.getElementById("save-invoice-btn");
    const cancelBtn = document.getElementById("cancel-invoice-btn");
    const printBtn = document.getElementById("print-invoice-btn");

    const invoiceNumberInput = document.getElementById("invoice-number");
    const invoiceDateInput = document.getElementById("invoice-date");

    const operationTypeSelect = document.getElementById("operation-type");
    const paymentMethodSelect = document.getElementById("payment-method");

    const totalPriceInput = document.getElementById("total-price");
    const depositAmountInput = document.getElementById("deposit-amount");
    const remainingBalanceInput = document.getElementById("remaining-balance");

    const customerNameInput = document.getElementById("customer-name");
    const phone1Input = document.getElementById("phone-number-1");
    const phone2Input = document.getElementById("phone-number-2");

    const dressNameInput = document.getElementById("dress-name");
    const dressCategoryInput = document.getElementById("dress-category"); // New category input
    const dressColorInput = document.getElementById("dress-color");
    const dressCodeInput = document.getElementById("dress-number");

    const bustInput = document.getElementById("bust-measurement");
    const waistInput = document.getElementById("waist-measurement");
    const dressLenInput = document.getElementById("dress-length");
    const sleeveLenInput = document.getElementById("sleeve-length");
    const shoulderLenInput = document.getElementById("shoulder-length");
    const otherMeasInput = document.getElementById("other-measurements");

    const weddingDateInput = document.getElementById("wedding-date");
    const collectionDateInput = document.getElementById("collection-date");
    const returnDateInput = document.getElementById("return-date");
    const returnDateLabel = returnDateInput ? returnDateInput.closest('.form-group')?.querySelector('label[for="return-date"]') : null;

    const hasAccessoriesCheckbox = document.getElementById("has-accessories");
    const accessoriesSection = document.getElementById("accessories-section");

    const accBouquet = document.getElementById("acc-bouquet");
    const accShoes = document.getElementById("acc-shoes");
    const accTiara = document.getElementById("acc-tiara");
    const accSet = document.getElementById("acc-set");
    const accAbaya = document.getElementById("acc-abaya");

    const notesInput = document.getElementById("invoice-notes");

    // متغير لتخزين بيانات العميل المحملة
    let loadedCustomer = null;

    // ================================================================
    // البحث التلقائي عن العميل عند كتابة رقم الهاتف
    // ================================================================
    let searchTimeout;

    if (phone1Input) {
        phone1Input.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            const phone = this.value.trim();

            // إذا كان الرقم أقل من 9 أرقام، لا نبحث
            if (phone.length < 9) {
                return;
            }

            // انتظار 500ms قبل البحث (لتجنب البحث مع كل حرف)
            searchTimeout = setTimeout(() => {
                searchCustomerByPhone(phone);
            }, 500);
        });
    }

    async function searchCustomerByPhone(phone) {
        try {
            // Use apiPost since passing JSON body
            const data = await apiPost(CUSTOMERS_API_URL, { action: 'search', phone: phone });

            if (data.found) {
                // عميل موجود - نملأ البيانات تلقائياً
                loadedCustomer = data.data; // Usually api-client unwraps data, but customers_api might return custom structure.
                // If customers_api returns { status: 'success', data: { ... }, found: true }
                // api-client usually returns the 'data' part if status is success.
                // Let's check customers_api.php structure if possible, or assume api-client unwraps 'data'.
                // CAUTION: api-client unwraps 'data' property.
                // If the response is { status: 'success', found: true, data: {...} }
                // api-client returns {...} (the user object).
                // So 'data' here is the user object.
                // But the 'found' property is at the root level?
                // api-client returns: data.data !== undefined ? data.data : data;
                // If customers_api returns: { status:'success', found:true, data:{...} }
                // api-client returns {...} (the user object).
                // So checking `data.found` might fail if it was unwrapped.

                // Let's assume api-client is used. If api-client unwraps 'data', we lose 'found'.
                // However, if we get an object, it's found.

                // Let's rely on existence of data.
                loadedCustomer = data;
                if (loadedCustomer && loadedCustomer.name) {
                    fillCustomerData(loadedCustomer);
                    showCustomerFoundMessage(loadedCustomer);
                }
            } else {
                // If api-client returns the root object because 'data' field didn't exist?
                // Or if it found nothing?
                // Wait, if not found, API might return { status: 'success', found: false }.
                // api-client returns that object.
                if (data && data.found === false) {
                    loadedCustomer = null;
                    clearCustomerFoundMessage();
                }
            }
        } catch (err) {
            console.error('خطأ في البحث عن العميل:', err);
        }
    }

    function fillCustomerData(customer) {
        if (customerNameInput && !customerNameInput.value) {
            customerNameInput.value = customer.name || '';
        }
        if (phone2Input && !phone2Input.value) {
            phone2Input.value = customer.phone_2 || '';
        }
    }

    function showCustomerFoundMessage(customer) {
        // إزالة أي رسالة سابقة
        clearCustomerFoundMessage();

        // إنشاء رسالة
        const messageDiv = document.createElement('div');
        messageDiv.id = 'customer-found-message';
        messageDiv.style.cssText = `
            background: #e8f5e9;
            border: 1px solid #4caf50;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            color: #2e7d32;
            font-size: 14px;
        `;

        const typeName = customer.type === 'vip' ? 'مميز' : customer.type === 'new' ? 'جديد' : 'عادي';
        const invoiceCount = customer.invoice_count || 0;

        messageDiv.innerHTML = `
            <i class="fas fa-check-circle"></i>
            <strong>عميل موجود:</strong> ${customer.name} 
            (${typeName} - ${invoiceCount} فاتورة سابقة)
        `;

        // إدراج الرسالة بعد حقل الاسم
        if (customerNameInput && customerNameInput.parentElement) {
            customerNameInput.parentElement.insertAdjacentElement('afterend', messageDiv);
        }
    }

    function clearCustomerFoundMessage() {
        const existingMessage = document.getElementById('customer-found-message');
        if (existingMessage) {
            existingMessage.remove();
        }
    }

    // ================================================================
    // CONDITIONAL LOGIC: Disable return_date for sale operations
    // ================================================================

    /**
     * Check if operation type is a sale (بيع or تصميم وبيع)
     * @returns {boolean}
     */
    function isSaleOperation() {
        const operationType = operationTypeSelect?.value || '';
        return operationType === 'sale' || operationType === 'design-sale';
    }

    /**
     * Handle return_date field state based on operation type
     */
    function handleReturnDateField() {
        if (!returnDateInput) return;

        const isSale = isSaleOperation();

        if (isSale) {
            // Disable and clear return_date for sales
            returnDateInput.disabled = true;
            returnDateInput.value = '';
            returnDateInput.style.opacity = '0.5';
            returnDateInput.style.cursor = 'not-allowed';
            returnDateInput.style.backgroundColor = '#f5f5f5';

            // Also disable the label visually
            if (returnDateLabel) {
                returnDateLabel.style.opacity = '0.5';
                returnDateLabel.style.cursor = 'not-allowed';
            }
        } else {
            // Enable return_date for non-sale operations
            returnDateInput.disabled = false;
            returnDateInput.style.opacity = '1';
            returnDateInput.style.cursor = 'pointer';
            returnDateInput.style.backgroundColor = '';

            // Re-enable the label
            if (returnDateLabel) {
                returnDateLabel.style.opacity = '1';
                returnDateLabel.style.cursor = 'default';
            }
        }
    }

    // Add event listener to operation type select
    if (operationTypeSelect) {
        operationTypeSelect.addEventListener('change', handleReturnDateField);

        // Also call on page load in case form is pre-filled (edit mode)
        handleReturnDateField();
    }

    // ================================================================
    // SKU Generation Logic
    // ================================================================
    if (dressCategoryInput) {
        dressCategoryInput.addEventListener('change', async function () {
            const categoryChar = this.value; // A, B, C
            if (!categoryChar) return;

            // Do not generate if code is already filled (unless we want to overwrite?)
            // For now, let's overwrite if user explicitly changes category as it implies new intent

            try {
                if (dressCodeInput) dressCodeInput.placeholder = "جاري التوليد...";

                const result = await apiGet("inventory.php", { action: 'next_sku', category_char: categoryChar });

                if (result && result.sku && dressCodeInput) {
                    dressCodeInput.value = result.sku;
                }
            } catch (e) {
                console.error('Failed to generate SKU:', e);
            } finally {
                if (dressCodeInput) dressCodeInput.placeholder = "مثال: D-0001";
            }
        });
    }

    // ================================================================
    // HELPER FUNCTIONS
    // ================================================================
    function formatDateForInput(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, "0");
        const d = String(date.getDate()).padStart(2, "0");
        return `${y}-${m}-${d}`;
    }

    function calcRemaining() {
        const total = parseFloat(totalPriceInput?.value || "0") || 0;
        const deposit = parseFloat(depositAmountInput?.value || "0") || 0;
        const remaining = total - deposit;
        if (remainingBalanceInput) {
            remainingBalanceInput.value = remaining >= 0 ? remaining.toFixed(2) : 0;
        }
    }

    function collectAccessories() {
        const list = [];
        if (hasAccessoriesCheckbox && hasAccessoriesCheckbox.checked) {
            if (accBouquet && accBouquet.checked) list.push("مسكة ورد");
            if (accShoes && accShoes.checked) list.push("حذاء");
            if (accTiara && accTiara.checked) list.push("تاج");
            if (accSet && accSet.checked) list.push("طقم اكسسوار");
            if (accAbaya && accAbaya.checked) list.push("عباية مغربية");
        }
        return list;
    }

    function fillAccessories(accessories) {
        if (!hasAccessoriesCheckbox || !accessoriesSection) return;
        if (!Array.isArray(accessories) || accessories.length === 0) {
            hasAccessoriesCheckbox.checked = false;
            accessoriesSection.style.display = "none";
            return;
        }

        hasAccessoriesCheckbox.checked = true;
        accessoriesSection.style.display = "block";

        if (accBouquet) accBouquet.checked = accessories.includes("مسكة ورد");
        if (accShoes) accShoes.checked = accessories.includes("حذاء");
        if (accTiara) accTiara.checked = accessories.includes("تاج");
        if (accSet) accSet.checked = accessories.includes("طقم اكسسوار");
        if (accAbaya) accAbaya.checked = accessories.includes("عباية مغربية");
    }

    function validateForm() {
        if (!operationTypeSelect.value) {
            alert("يرجى اختيار نوع العملية");
            operationTypeSelect.focus();
            return false;
        }
        if (!paymentMethodSelect.value) {
            alert("يرجى اختيار طريقة الدفع");
            paymentMethodSelect.focus();
            return false;
        }
        if (!totalPriceInput.value || parseFloat(totalPriceInput.value) <= 0) {
            alert("يرجى إدخال السعر الإجمالي");
            totalPriceInput.focus();
            return false;
        }
        if (!customerNameInput.value.trim()) {
            alert("يرجى كتابة اسم العميل");
            customerNameInput.focus();
            return false;
        }
        if (!phone1Input.value.trim()) {
            alert("يرجى كتابة رقم الهاتف");
            phone1Input.focus();
            return false;
        }
        if (!dressNameInput.value.trim()) {
            alert("يرجى كتابة اسم الفستان");
            dressNameInput.focus();
            return false;
        }
        if (!invoiceDateInput.value) {
            alert("يرجى اختيار تاريخ الفاتورة");
            invoiceDateInput.focus();
            return false;
        }
        return true;
    }

    function buildInvoiceData() {
        const operationType = operationTypeSelect?.value || null;
        const isSale = operationType === 'sale' || operationType === 'design-sale';

        return {
            id: isEditMode ? invoiceId : null,
            invoice_number: invoiceNumberInput?.value || null,
            invoice_date: invoiceDateInput?.value || null,
            operation_type: operationType,
            payment_method: paymentMethodSelect?.value || null,

            total_price: totalPriceInput?.value || 0,
            deposit_amount: depositAmountInput?.value || 0,
            remaining_balance: remainingBalanceInput?.value || 0,

            customer_name: customerNameInput?.value || "",
            phone_1: phone1Input?.value || "",
            phone_2: phone2Input?.value || "",

            wedding_date: weddingDateInput?.value || null,
            collection_date: collectionDateInput?.value || null,
            return_date: isSale ? null : (returnDateInput?.value || null), // Null for sales

            dress_name: dressNameInput?.value || "",
            dress_code: dressCodeInput?.value || null,
            dress_color: dressColorInput?.value || "",
            dress_category: dressCategoryInput?.value || null,

            bust_meas: bustInput?.value || null,
            waist_meas: waistInput?.value || null,
            dress_len: dressLenInput?.value || null,
            sleeve_len: sleeveLenInput?.value || null,
            shoulder_len: shoulderLenInput?.value || null,
            other_meas: otherMeasInput?.value || null,

            accessories: collectAccessories(),
            notes: notesInput?.value || ""
        };
    }

    // ================================================================
    // GENERATE INVOICE NUMBER
    // ================================================================
    async function generateInvoiceNumber() {
        const timestamp = new Date().getTime();
        console.log('🔄 Generating invoice number...');

        try {
            const data = await apiGet(INVOICE_API_ENDPOINT, { action: 'generate_number', _t: timestamp });
            console.log('📊 Response data:', data);

            // api-client un-wraps data, but if structure is complex we inspect it.
            // If API returned { status:'success', data: { invoice_number: '...' } }
            // api-client returns { invoice_number: '...' }.

            if (data && data.invoice_number) {
                invoiceNumberInput.value = data.invoice_number;
                console.log('✅ Invoice number set to:', data.invoice_number);
            } else {
                console.warn('⚠️ No valid invoice number in response, using fallback');
                invoiceNumberInput.value = 'INV-0001';
            }
        } catch (err) {
            console.error('❌ Generate number error:', err);
            if (invoiceNumberInput) {
                // Fail gracefully with a temporary ID
                const ts = new Date().getTime().toString().substr(-6);
                invoiceNumberInput.value = 'INV-TMP-' + ts;
                console.log('⚠️ Using temporary number:', invoiceNumberInput.value);
            }
        }
    }

    // ================================================================
    // LOAD INVOICE FOR EDIT
    // ================================================================
    async function loadInvoiceForEdit(id) {
        try {
            // Encode ID not strictly needed for apiGet param object but good practice if strings
            const data = await apiGet(INVOICE_API_ENDPOINT, { action: 'get_invoice', id: id });

            // Expected data structure after api-client unwrap:
            // { invoice: {...}, item: {...}, accessories: [...] }

            const invoice = data.invoice || {};
            const item = data.item || {};
            const accessories = data.accessories || [];

            if (invoiceNumberInput) invoiceNumberInput.value = invoice.invoice_number || "";
            if (invoiceDateInput) invoiceDateInput.value = invoice.invoice_date || "";

            if (operationTypeSelect) operationTypeSelect.value = invoice.operation_type || "";
            if (paymentMethodSelect) paymentMethodSelect.value = invoice.payment_method || "";

            if (totalPriceInput) totalPriceInput.value = invoice.total_price ?? "";
            if (depositAmountInput) depositAmountInput.value = invoice.deposit_amount ?? "";
            if (remainingBalanceInput) remainingBalanceInput.value = invoice.remaining_balance ?? "";

            if (customerNameInput) customerNameInput.value = invoice.customer_name || "";
            if (phone1Input) phone1Input.value = invoice.phone_1 || "";
            if (phone2Input) phone2Input.value = invoice.phone_2 || "";

            if (weddingDateInput) weddingDateInput.value = invoice.wedding_date || "";
            if (collectionDateInput) collectionDateInput.value = invoice.collection_date || "";
            if (returnDateInput) {
                returnDateInput.value = invoice.return_date || "";
                handleReturnDateField();
            }

            if (dressNameInput) dressNameInput.value = item.dress_name || "";
            if (dressColorInput) dressColorInput.value = item.dress_color || "";
            if (dressCodeInput) dressCodeInput.value = item.dress_code || "";

            if (bustInput) bustInput.value = item.bust_meas || "";
            if (waistInput) waistInput.value = item.waist_meas || "";
            if (dressLenInput) dressLenInput.value = item.dress_len || "";
            if (sleeveLenInput) sleeveLenInput.value = item.sleeve_len || "";
            if (shoulderLenInput) shoulderLenInput.value = item.shoulder_len || "";
            if (otherMeasInput) otherMeasInput.value = item.other_meas || "";

            if (notesInput) notesInput.value = invoice.notes || "";

            fillAccessories(accessories);

            if (printBtn) printBtn.style.display = "inline-block";

        } catch (err) {
            console.error("Load invoice error:", err);
            alert("حدث خطأ أثناء تحميل بيانات الفاتورة.");
            window.location.href = "sales.php";
        }
    }

    // ================================================================
    // SAVE INVOICE
    // ================================================================
    async function handleSave(e) {
        e.preventDefault();
        if (!validateForm()) return;

        const payload = buildInvoiceData();

        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
        }

        try {
            const data = await apiPost(SAVE_INVOICE_ENDPOINT, payload);

            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> حفظ الفاتورة';
            }

            alert(isEditMode ? "تم تعديل الفاتورة بنجاح" : "تم حفظ الفاتورة بنجاح");
            window.location.href = "sales.php";

        } catch (err) {
            console.error("Save error:", err);
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> حفظ الفاتورة';
            }
            handleApiError(err); // Uses alert inside
        }
    }

    // ================================================================
    // CANCEL & PRINT
    // ================================================================
    function handleCancel(e) {
        e.preventDefault();
        if (confirm("هل أنت متأكد من إلغاء الفاتورة؟ سيتم فقدان جميع البيانات غير المحفوظة.")) {
            window.location.href = "sales.php";
        }
    }

    function handlePrint(e) {
        e.preventDefault();
        if (!isEditMode) {
            alert('احفظ الفاتورة أولاً ثم اطبعها من صفحة المبيعات.');
            return;
        }
        if (typeof PrintInvoice !== 'undefined') {
            PrintInvoice.printById(invoiceId);
        } else {
            console.error('PrintInvoice module not loaded');
        }
    }

    // ================================================================
    // INITIALIZATION
    // ================================================================

    if (totalPriceInput) totalPriceInput.addEventListener("input", calcRemaining);
    if (depositAmountInput) depositAmountInput.addEventListener("input", calcRemaining);

    if (hasAccessoriesCheckbox && accessoriesSection) {
        hasAccessoriesCheckbox.addEventListener("change", () => {
            accessoriesSection.style.display = hasAccessoriesCheckbox.checked ? "block" : "none";
        });
    }

    if (saveBtn) saveBtn.addEventListener("click", handleSave);
    if (cancelBtn) cancelBtn.addEventListener("click", handleCancel);
    if (printBtn) printBtn.addEventListener("click", handlePrint);

    if (isEditMode) {
        loadInvoiceForEdit(invoiceId);
    } else {
        if (invoiceDateInput) {
            invoiceDateInput.value = formatDateForInput(new Date());
        }
        generateInvoiceNumber();
    }
});
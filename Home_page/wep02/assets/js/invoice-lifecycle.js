/**
 * Invoice Lifecycle Management
 * 
 * يتعامل مع جميع إجراءات دورة حياة الفاتورة:
 * - تأكيد التسليم (deliver)
 * - تأكيد الإرجاع (return)
 * - إقفال الفاتورة (close)
 * - إلغاء الفاتورة (cancel)
 * 
 * Enhanced Version with proper error handling and JSON validation
 */

/**
 * Helper function to validate JSON response
 * يتعامل مع جميع أنواع الأخطاء بشكل صحيح
 */
async function handleApiResponse(response, actionName) {
    // Get Content-Type
    const contentType = response.headers.get('content-type') || '';

    // If not JSON, handle error
    if (!contentType.includes('application/json')) {
        const text = await response.text();
        console.error(`Non-JSON response for ${actionName}:`, text);
        throw new Error('الخادم أرجع بيانات غير صالحة. حاول مرة أخرى.');
    }

    // Parse JSON
    const data = await response.json();

    // Handle HTTP errors (including 400, 500, etc.)
    if (!response.ok) {
        // الخادم أرجع خطأ مع رسالة - نعرض الرسالة للمستخدم
        if (data.message) {
            throw new Error(data.message);
        }
        // لا توجد رسالة واضحة
        if (response.status === 400) {
            throw new Error('طلب غير صالح');
        } else if (response.status === 403) {
            throw new Error('ليس لديك صلاحية لهذا الإجراء');
        } else if (response.status === 500) {
            throw new Error('حدث خطأ في الخادم، حاول مرة أخرى');
        } else {
            throw new Error(`خطأ: ${response.status}`);
        }
    }

    // Check success status in response body
    if (data.status !== 'success') {
        throw new Error(data.message || `فشل في ${actionName}`);
    }

    return data;
}


/**
 * Handle deliver action - تأكيد تسليم الفستان للعميل
 */
async function handleDeliver() {
    if (!confirm('هل أنت متأكد من تأكيد تسليم الفستان للعميل؟')) {
        return;
    }

    const notes = prompt('ملاحظات (اختياري):');

    // Show loading state
    const btn = document.getElementById('btn-deliver');
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التسليم...';
    }

    try {
        const response = await fetch('api/invoices.php?action=deliver', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                invoice_id: invoiceId,
                notes: notes || null
            })
        });

        const data = await handleApiResponse(response, 'تأكيد التسليم');

        // Show success message
        if (window.invoiceDetailsUtils) {
            window.invoiceDetailsUtils.showSuccess(data.message || 'تم تأكيد تسليم الفستان بنجاح');
        } else {
            alert('تم تأكيد تسليم الفستان بنجاح');
        }

        // Refresh page data
        setTimeout(() => {
            if (window.invoiceDetailsUtils) {
                window.invoiceDetailsUtils.refreshData();
            } else {
                location.reload();
            }
        }, 1500);

    } catch (error) {
        console.error('Deliver error:', error);
        if (window.invoiceDetailsUtils) {
            window.invoiceDetailsUtils.showError('خطأ: ' + error.message);
        } else {
            alert('خطأ: ' + error.message);
        }
    } finally {
        // Restore button
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
}

/**
 * Handle return action - فتح نافذة الإرجاع
 */
function handleReturn() {
    const modal = document.getElementById('return-modal');
    if (modal) {
        modal.style.display = 'block';
        // Reset form
        const form = document.getElementById('return-form');
        if (form) form.reset();
    } else {
        console.error('Return modal not found');
        alert('خطأ: نافذة الإرجاع غير موجودة');
    }
}

/**
 * Close return modal
 */
function closeReturnModal() {
    const modal = document.getElementById('return-modal');
    if (modal) {
        modal.style.display = 'none';
    }
    const form = document.getElementById('return-form');
    if (form) form.reset();
}

/**
 * Submit return form - تأكيد إرجاع الفستان
 */
async function submitReturn(e) {
    if (e) e.preventDefault();

    // الحصول على النموذج والمودال بدقة
    const form = document.getElementById('return-form');
    if (!form) {
        console.error('Return form not found');
        return;
    }

    // البحث عن القائمة المنسدلة داخل النموذج باستخدام name attribute لضمان الدقة
    const conditionSelect = form.querySelector('[name="return_condition"]');
    const notesInput = form.querySelector('[name="return_notes"]');

    // الحصول على القيمة
    const condition = conditionSelect ? conditionSelect.value : '';
    const notes = notesInput ? notesInput.value : '';

    // التحقق الصارم
    if (!condition || condition.trim() === '') {
        console.warn('Condition validation failed. Value:', condition);
        if (window.invoiceDetailsUtils) {
            window.invoiceDetailsUtils.showError('يجب اختيار حالة الفستان');
        } else {
            alert('يجب اختيار حالة الفستان');
        }
        return;
    }

    // Show loading state on the button with data-action="confirm-return"
    // البحث عن الزر الصحيح باستخدام data-action
    const submitBtn = form.querySelector('[data-action="confirm-return"]') || form.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.innerHTML : '';

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التأكيد...';
    }

    try {
        const response = await fetch('api/invoices.php?action=return', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                invoice_id: invoiceId, // Global variable
                condition: condition,
                notes: notes || null
            })
        });

        // التحقق من الاستجابة باستخدام الدالة المساعدة
        const data = await handleApiResponse(response, 'تأكيد الإرجاع');

        // Close modal
        closeReturnModal();

        // Show success message
        if (window.invoiceDetailsUtils) {
            window.invoiceDetailsUtils.showSuccess(data.message || 'تم تأكيد إرجاع الفستان بنجاح');
        } else {
            alert('تم تأكيد إرجاع الفستان بنجاح');
        }

        // إذا كانت الحالة تحتاج غرامة، اعرض تنبيه
        if (condition === 'damaged' || condition === 'missing_items') {
            setTimeout(() => {
                if (confirm('الفستان بحالة سيئة - هل تريد إضافة غرامة؟')) {
                    openAddPenaltyModal();
                    // تحديث البيانات أيضاً حتى لو فتحنا المودال
                    refreshAfterAction();
                } else {
                    refreshAfterAction();
                }
            }, 1000);
        } else {
            setTimeout(refreshAfterAction, 1500);
        }

    } catch (error) {
        console.error('Return error:', error);
        // Don't close modal on error
        if (window.invoiceDetailsUtils) {
            window.invoiceDetailsUtils.showError('خطأ: ' + error.message);
        } else {
            alert('خطأ: ' + error.message);
        }
    } finally {
        // Restore button
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }
}

/**
 * Handle close invoice action - إقفال الفاتورة نهائياً
 */
async function handleClose() {
    if (!confirm('هل أنت متأكد من إقفال الفاتورة نهائياً؟\nلن يمكنك تعديلها بعد الإقفال.')) {
        return;
    }

    const notes = prompt('ملاحظات (اختياري):');

    // Show loading state
    const btn = document.getElementById('btn-close');
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإقفال...';
    }

    try {
        const response = await fetch('api/invoices.php?action=close', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                invoice_id: invoiceId,
                notes: notes || null
            })
        });

        const data = await handleApiResponse(response, 'إقفال الفاتورة');

        if (window.invoiceDetailsUtils) {
            window.invoiceDetailsUtils.showSuccess(data.message || 'تم إقفال الفاتورة بنجاح');
        } else {
            alert('تم إقفال الفاتورة بنجاح');
        }

        setTimeout(refreshAfterAction, 1500);

    } catch (error) {
        console.error('Close error:', error);
        if (window.invoiceDetailsUtils) {
            window.invoiceDetailsUtils.showError('خطأ: ' + error.message);
        } else {
            alert('خطأ: ' + error.message);
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
}

/**
 * Handle cancel invoice action - إلغاء الفاتورة
 */
async function handleCancel() {
    const reason = prompt('يرجى إدخال سبب الإلغاء:');

    if (!reason || reason.trim() === '') {
        if (window.invoiceDetailsUtils) {
            window.invoiceDetailsUtils.showWarning('يجب إدخال سبب الإلغاء');
        } else {
            alert('يجب إدخال سبب الإلغاء');
        }
        return;
    }

    if (!confirm('هل أنت متأكد من إلغاء هذه الفاتورة؟\nسيتم نقلها إلى الأرشيف.')) {
        return;
    }

    // Show loading state
    const btn = document.getElementById('btn-cancel');
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإلغاء...';
    }

    try {
        const response = await fetch('api/invoices.php?action=cancel', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                invoice_id: invoiceId,
                reason: reason
            })
        });

        const data = await handleApiResponse(response, 'إلغاء الفاتورة');

        if (window.invoiceDetailsUtils) {
            window.invoiceDetailsUtils.showSuccess(data.message || 'تم إلغاء الفاتورة بنجاح');
        } else {
            alert('تم إلغاء الفاتورة بنجاح');
        }

        // Redirect to sales page
        setTimeout(() => {
            window.location.href = 'sales.php';
        }, 2000);

    } catch (error) {
        console.error('Cancel error:', error);
        if (window.invoiceDetailsUtils) {
            window.invoiceDetailsUtils.showError('خطأ: ' + error.message);
        } else {
            alert('خطأ: ' + error.message);
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
}

/**
 * Open modal for adding penalty after damaged return
 */
function openAddPenaltyModal() {
    const paymentType = document.getElementById('payment-type');
    const paymentNotes = document.getElementById('payment-notes');
    const modal = document.getElementById('add-payment-modal');

    if (paymentType) paymentType.value = 'penalty';
    if (paymentNotes) paymentNotes.value = 'غرامة بسبب الضرر أو نقص الملحقات';
    if (modal) modal.style.display = 'block';
}

/**
 * Refresh page data after action
 */
function refreshAfterAction() {
    if (window.invoiceDetailsUtils && typeof window.invoiceDetailsUtils.refreshData === 'function') {
        window.invoiceDetailsUtils.refreshData();
    } else if (typeof loadInvoiceDetails === 'function') {
        loadInvoiceDetails();
    } else {
        location.reload();
    }
}

/**
 * Print invoice - طباعة الفاتورة
 */
function printInvoice() {
    window.open('print_invoice.php?id=' + invoiceId, '_blank');
}

/**
 * Initialize event listeners on DOM ready
 */
document.addEventListener('DOMContentLoaded', function () {
    // Return form submission (using submit event on form is safer than click on button for validation)
    const returnForm = document.getElementById('return-form');
    if (returnForm) {
        returnForm.addEventListener('submit', submitReturn);
    }

    // Close modal on outside click
    const returnModal = document.getElementById('return-modal');
    if (returnModal) {
        returnModal.addEventListener('click', function (event) {
            if (event.target === returnModal) {
                closeReturnModal();
            }
        });
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeReturnModal();
        }
    });
});

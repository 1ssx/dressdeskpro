/**
 * Master Dashboard - Enhanced Functions
 * Adds new functionality for store management, audit logs, impersonation
 * FIXED: Improved error handling and console logging for debugging
 */

// ==================== STORE DETAILS ====================
function viewStoreDetails(storeId) {
    document.getElementById('storeDetailsModal').style.display = 'block';
    document.getElementById('store-details-content').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> جاري التحميل...</div>';

    fetch(`api/master/stores.php?action=get_details&store_id=${storeId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                renderStoreDetails(data.data);
            } else {
                document.getElementById('store-details-content').innerHTML = '<p style="color: var(--danger); text-align: center;">خطأ في تحميل التفاصيل</p>';
            }
        })
        .catch(err => {
            console.error('Error:', err);
            document.getElementById('store-details-content').innerHTML = '<p style="color: var(--danger); text-align: center;">حدث خطأ في الاتصال</p>';
        });
}

function renderStoreDetails(store) {
    const html = `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div class="form-group">
                <label>ID:</label>
                <p style="margin: 0; font-weight: 600;">${store.id}</p>
            </div>
            <div class="form-group">
                <label>اسم المحل:</label>
                <p style="margin: 0; font-weight: 600;">${escapeHtml(store.store_name)}</p>
            </div>
            <div class="form-group">
                <label>قاعدة البيانات:</label>
                <p style="margin: 0;"><code>${escapeHtml(store.database_name)}</code></p>
            </div>
            <div class="form-group">
                <label>الحالة:</label>
                <p style="margin: 0;">${store.status}</p>
            </div>
            <div class="form-group">
                <label>اسم المالك:</label>
                <p style="margin: 0;">${escapeHtml(store.owner_name)}</p>
            </div>
            <div class="form-group">
                <label>بريد المالك:</label>
                <p style="margin: 0; direction: ltr;">${escapeHtml(store.owner_email)}</p>
            </div>
            <div class="form-group">
                <label>كود التفعيل:</label>
                <p style="margin: 0;"><code>${escapeHtml(store.license_code || '-')}</code></p>
            </div>
            <div class="form-group">
                <label>تاريخ الإنشاء:</label>
                <p style="margin: 0;">${new Date(store.created_at).toLocaleString('en-GB')}</p>
            </div>
            <div class="form-group">
                <label>آخر دخول:</label>
                <p style="margin: 0;">${store.last_login ? new Date(store.last_login).toLocaleString('en-GB') : 'لم يدخل بعد'}</p>
            </div>
            <div class="form-group">
                <label>آخر تحديث:</label>
                <p style="margin: 0;">${new Date(store.updated_at).toLocaleString('en-GB')}</p>
            </div>
        </div>
    `;
    document.getElementById('store-details-content').innerHTML = html;
}

function closeStoreDetailsModal() {
    document.getElementById('storeDetailsModal').style.display = 'none';
}

// ==================== SOFT DELETE ====================
function showSoftDeleteModal(storeId, storeName) {
    document.getElementById('softDeleteModal').style.display = 'block';
    document.getElementById('soft-delete-store-id').value = storeId;
    document.getElementById('soft-delete-expected-name').textContent = storeName;
    document.getElementById('soft-delete-confirm').value = '';
    document.getElementById('soft-delete-confirm').focus();
}

function closeSoftDeleteModal() {
    document.getElementById('softDeleteModal').style.display = 'none';
    document.getElementById('soft-delete-form').reset();
}

document.getElementById('soft-delete-form').addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    formData.append('action', 'soft_delete');

    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحذف...';
    btn.disabled = true;

    fetch('api/master/stores.php', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            btn.innerHTML = originalText;
            btn.disabled = false;

            if (data.status === 'success') {
                closeSoftDeleteModal();
                loadStores();
                loadDeletedStores(); // Refresh trash section
                loadAuditLogs();
            } else {
                alert('خطأ: ' + (data.message || 'فشل الحذف'));
            }
        })
        .catch(err => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            console.error('Error:', err);
            alert('حدث خطأ في الاتصال');
        });
});

// ==================== HARD DELETE ====================
function showHardDeleteModal(storeId, storeName) {
    document.getElementById('hardDeleteModal').style.display = 'block';
    document.getElementById('hard-delete-store-id').value = storeId;
    document.getElementById('hard-delete-expected-name').textContent = storeName;
    document.getElementById('hard-delete-confirm-name').value = '';
    document.getElementById('hard-delete-confirm-delete').value = '';
    document.getElementById('hard-delete-db').checked = false;

    // CRITICAL: Disable submit button by default
    const submitBtn = document.getElementById('btn-confirm-hard-delete');
    if (submitBtn) {
        submitBtn.disabled = true;
    }

    document.getElementById('hard-delete-confirm-name').focus();
}

function closeHardDeleteModal() {
    document.getElementById('hardDeleteModal').style.display = 'none';
    document.getElementById('hard-delete-form').reset();

    // Reset button state
    const submitBtn = document.getElementById('btn-confirm-hard-delete');
    if (submitBtn) {
        submitBtn.disabled = true;
    }
}

// CRITICAL: Add event listener to checkbox to control button state
document.addEventListener('DOMContentLoaded', function () {
    const checkbox = document.getElementById('hard-delete-db');
    const submitBtn = document.getElementById('btn-confirm-hard-delete');

    if (checkbox && submitBtn) {
        checkbox.addEventListener('change', function () {
            submitBtn.disabled = !this.checked;

            // Visual feedback
            if (this.checked) {
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
                console.log('✅ Safety checkbox confirmed - Delete button enabled');
            } else {
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
                console.log('❌ Safety checkbox unchecked - Delete button disabled');
            }
        });

        // Set initial state
        submitBtn.disabled = !checkbox.checked;
        submitBtn.style.opacity = checkbox.checked ? '1' : '0.5';
        submitBtn.style.cursor = checkbox.checked ? 'pointer' : 'not-allowed';
    }
});



document.getElementById('hard-delete-form').addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    formData.append('action', 'hard_delete');

    // Debug logging
    console.log('=== Hard Delete Form Submission ===');
    console.log('Store ID:', formData.get('store_id'));
    console.log('Confirm Store Name:', formData.get('confirm_store_name'));
    console.log('Confirm Delete:', formData.get('confirm_delete'));
    console.log('Delete Database:', formData.get('delete_database'));
    console.log('Action:', formData.get('action'));

    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحذف النهائي...';
    btn.disabled = true;

    fetch('api/master/stores.php', {
        method: 'POST',
        body: formData
    })
        .then(res => {
            console.log('Response status:', res.status, res.statusText);
            console.log('Response headers:', res.headers);

            // Check if response is OK
            if (!res.ok) {
                return res.text().then(text => {
                    console.error('Error response body:', text);
                    try {
                        const json = JSON.parse(text);
                        throw new Error(json.message || 'Server error: ' + res.status);
                    } catch (e) {
                        throw new Error('Server error: ' + res.status + ' - ' + text.substring(0, 200));
                    }
                });
            }

            return res.json();
        })
        .then(data => {
            console.log('Response data:', data);
            btn.innerHTML = originalText;
            btn.disabled = false;

            if (data.status === 'success') {
                alert('✅ تم الحذف النهائي بنجاح\n\n' + (data.message || ''));
                closeHardDeleteModal();
                loadStores();
                loadDeletedStores(); // Refresh trash section
                loadAuditLogs();
            } else {
                alert('❌ خطأ: ' + (data.message || 'فشل الحذف'));
            }
        })
        .catch(err => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            console.error('Hard delete error:', err);
            alert('حدث خطأ في الاتصال:\n' + err.message);
        });
});


// ==================== RESTORE STORE ====================
function restoreStore(storeId) {
    if (!confirm('هل أنت متأكد من استعادة هذا المحل؟')) return;

    const formData = new FormData();
    formData.append('action', 'restore');
    formData.append('store_id', storeId);

    fetch('api/master/stores.php', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                alert('✅ تم استعادة المحل بنجاح');
                loadStores();
                loadDeletedStores(); // Refresh trash section
                loadAuditLogs();
            } else {
                alert('خطأ: ' + (data.message || 'فشل الاستعادة'));
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('حدث خطأ في الاتصال');
        });
}

// ==================== IMPERSONATION (FIXED) ====================
function loginAsStore(storeId, storeName) {
    if (!confirm(`هل أنت متأكد من الدخول كـ System Owner إلى محل: ${storeName}?\n\nجميع الإجراءات ستسجل في Audit Log.`)) return;

    console.log('🔄 Impersonation requested for store:', storeId, storeName);

    const formData = new FormData();
    formData.append('action', 'login_as_store');
    formData.append('store_id', storeId);

    fetch('api/master/impersonation.php', {
        method: 'POST',
        body: formData
    })
        .then(res => {
            console.log('📡 Impersonation response status:', res.status, res.statusText);

            // Check if response is OK
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            }

            // Check content type before parsing JSON
            const contentType = res.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                console.error('❌ Server returned non-JSON response');
                throw new Error('Server error: Non-JSON response. Check PHP error log.');
            }

            return res.json();
        })
        .then(data => {
            console.log('📦 Impersonation data:', data);

            if (data.status === 'success') {
                console.log('✅ Impersonation successful, redirecting to:', data.redirect);
                // Redirect to store dashboard
                window.location.href = data.redirect || '../index.php';
            } else {
                console.error('❌ Impersonation failed:', data.message);
                alert('خطأ في الدخول:\n' + (data.message || 'فشل الدخول'));
            }
        })
        .catch(err => {
            console.error('❌ Impersonation error:', err);
            alert('حدث خطأ في الاتصم:\n' + err.message + '\n\nتحقق من console للمزيد من التفاصيل (F12)');
        });
}

// ==================== AUDIT LOGS ====================
function loadAuditLogs() {
    fetch('api/master/audit_log.php?action=list&limit=20')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                renderAuditLogs(data.data);
            } else {
                document.getElementById('audit-container').innerHTML = '<div class="loading" style="color: var(--danger);">خطأ في تحميل السجل</div>';
            }
        })
        .catch(err => {
            console.error('Error loading audit logs:', err);
            document.getElementById('audit-container').innerHTML = '<div class="loading" style="color: var(--danger);">حدث خطأ في الاتصال</div>';
        });
}

function renderAuditLogs(logs) {
    if (logs.length === 0) {
        document.getElementById('audit-container').innerHTML = '<div class="loading">لا توجد سجلات حتى الآن</div>';
        return;
    }

    let html = '<table><thead><tr>';
    html += '<th>الوقت</th><th>الإجراء</th><th>المنفذ</th><th>المحل</th><th>الوصف</th><th>IP</th>';
    html += '</tr></thead><tbody>';

    logs.forEach(log => {
        const actionTypeMap = {
            'store_created': 'إنشاء محل',
            'store_suspended': 'إيقاف محل',
            'store_activated': 'تفعيل محل',
            'store_deleted_soft': 'حذف مؤقت',
            'store_deleted_hard': 'حذف نهائي',
            'store_restored': 'استعادة محل',
            'license_created': 'إنشاء رمز',
            'license_used': 'استخدام رمز',
            'impersonation_login': 'دخول كمدير',
            'impersonation_logout': 'خروج من الإدارة'
        };

        const actionText = actionTypeMap[log.action_type] || log.action_type;

        html += `<tr>
            <td style="white-space: nowrap;">${new Date(log.created_at).toLocaleString('en-GB')}</td>
            <td><code>${actionText}</code></td>
            <td>${escapeHtml(log.admin_name || '-')}</td>
            <td>${escapeHtml(log.store_name || '-')}</td>
            <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(log.action_description || '-')}</td>
            <td style="direction: ltr;"><code>${log.ip_address || '-'}</code></td>
        </tr>`;
    });

    html += '</tbody></table>';
    document.getElementById('audit-container').innerHTML = html;
}

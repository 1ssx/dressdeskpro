<?php
/**
 * Master Admin Dashboard
 * Platform owner control panel for managing stores and license keys
 */

require_once __DIR__ . '/../includes/master_session_check.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة القيادة | إدارة المنصة</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #4f46e5; /* Indigo 600 */
            --primary-hover: #4338ca;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg-body: #f3f4f6;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --radius: 0.75rem; /* 12px */
        }

        * {
            box-sizing: border-box;
            outline: none;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }

        /* Layout */
        .master-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Header Card */
        .master-header {
            background: var(--bg-card);
            padding: 1.5rem 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid var(--primary);
        }

        .header-content h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-content p {
            margin: 0.25rem 0 0 0;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .logout-btn {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 0.6rem 1.2rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background-color: #fecaca;
        }

        /* Sections (Cards) */
        .section {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 0; /* Remove padding to handle table cleanly */
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            overflow: hidden; /* For rounded corners */
            border: 1px solid var(--border-color);
        }

        .section-header {
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            background: #fafafa;
        }

        .section-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-body {
            padding: 1.5rem 2rem;
            overflow-x: auto; /* Responsive table */
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.5rem 1rem;
            border: 1px solid transparent;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            font-family: 'Cairo', sans-serif;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);
        }
        .btn-primary:hover { background-color: var(--primary-hover); transform: translateY(-1px); }

        .btn-danger { background-color: #fff1f2; color: #be123c; border: 1px solid #fda4af; }
        .btn-danger:hover { background-color: #ffe4e6; }

        .btn-warning { background-color: #fffbeb; color: #b45309; border: 1px solid #fcd34d; }
        .btn-warning:hover { background-color: #fef3c7; }

        /* Tables */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.95rem;
        }

        th {
            background-color: #f9fafb;
            color: var(--text-muted);
            font-weight: 600;
            text-align: right;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
            vertical-align: middle;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background-color: #f9fafb; }

        code {
            font-family: 'Monaco', 'Consolas', monospace;
            background: #f1f5f9;
            color: #0f172a;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        /* Status Badges */
        .status-badge {
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
        }

        .status-active, .status-used { background-color: #dcfce7; color: #15803d; }
        .status-suspended, .status-expired { background-color: #fee2e2; color: #b91c1c; }
        .status-unused { background-color: #e0f2fe; color: #0369a1; }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .modal-content {
            background: white;
            margin: 5% auto;
            border-radius: 1rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: slideUp 0.3s ease-out;
            overflow: hidden;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        .modal-header h2 { margin: 0; font-size: 1.25rem; }

        .modal-body { padding: 1.5rem; }

        .form-group { margin-bottom: 1.5rem; }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-main);
            font-size: 0.9rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.95rem;
            font-family: 'Cairo', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-actions {
            background: #f9fafb;
            padding: 1rem 1.5rem;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            border-top: 1px solid var(--border-color);
        }

        .btn-secondary { background: white; border: 1px solid #d1d5db; color: var(--text-main); }
        .btn-secondary:hover { background: #f3f4f6; }

        .loading {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        
        /* Mobile Tweaks */
        @media (max-width: 768px) {
            .master-container { padding: 1rem; }
            .master-header { flex-direction: column; gap: 15px; text-align: center; }
            .section-header { flex-direction: column; gap: 15px; align-items: flex-start; }
            .btn { width: 100%; justify-content: center; }
            td, th { padding: 0.75rem; }
        }
    </style>
</head>
<body>
    <div class="master-container">
        <div class="master-header">
            <div class="header-content">
                <h1><i class="fas fa-shield-alt" style="color: var(--primary);"></i> لوحة التحكم الرئيسية</h1>
                <p>مرحباً، <?php echo htmlspecialchars($currentMasterUser['name']); ?> | إدارة النظام والاشتراكات</p>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
            </a>
        </div>

        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-store" style="color: var(--secondary);"></i> المحلات المسجلة</h2>
            </div>
            <div class="section-body" id="stores-container">
                <div class="loading"><i class="fas fa-circle-notch fa-spin"></i> جاري تحميل البيانات...</div>
            </div>
        </div>

        <!-- Deleted Stores Section (Trash/Recycle Bin) -->
        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-trash-restore" style="color: var(--warning);"></i> المحلات المحذوفة مؤقتاً (سلة المحذوفات)</h2>
                <button class="btn btn-primary" onclick="loadDeletedStores()" title="تحديث">
                    <i class="fas fa-sync"></i> تحديث
                </button>
            </div>
            <div class="section-body" id="deleted-stores-container">
                <div class="loading"><i class="fas fa-circle-notch fa-spin"></i> جاري تحميل البيانات...</div>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-key" style="color: var(--secondary);"></i> رموز التفعيل</h2>
                <button class="btn btn-primary" onclick="showGenerateModal()">
                    <i class="fas fa-plus"></i> إنشاء رمز جديد
                </button>
            </div>
            <div class="section-body" id="keys-container">
                <div class="loading"><i class="fas fa-circle-notch fa-spin"></i> جاري تحميل البيانات...</div>
            </div>
        </div>

        <!-- New Audit Log Section -->
        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-clipboard-list" style="color: var(--secondary);"></i> سجل الأحداث (Audit Log)</h2>
                <button class="btn btn-primary" onclick="loadAuditLogs()">
                    <i class="fas fa-sync"></i> تحديث
                </button>
            </div>
            <div class="section-body" id="audit-container">
                <div class="loading"><i class="fas fa-circle-notch fa-spin"></i> جاري تحميل البيانات...</div>
            </div>
        </div>
    </div>

    <div id="generateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>إنشاء رمز تفعيل جديد</h2>
            </div>
            <form id="generate-form">
                <div class="modal-body">
                    <div class="form-group">
                        <label>تاريخ الانتهاء (اختياري)</label>
                        <input type="datetime-local" name="expires_at" id="expires_at">
                        <small style="color: #6b7280; display: block; margin-top: 5px;">اتركه فارغاً لصلاحية مدى الحياة</small>
                    </div>
                    <div class="form-group">
                        <label>الحد الأقصى للاستخدام (عدد المحلات)</label>
                        <input type="number" name="max_uses" value="1" min="1">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeGenerateModal()">إلغاء</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> إنشاء الرمز</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Store Details Modal -->
    <div id="storeDetailsModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2>تفاصيل المحل</h2>
            </div>
            <div class="modal-body" id="store-details-content">
                <div class="loading"><i class="fas fa-spinner fa-spin"></i> جاري التحميل...</div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeStoreDetailsModal()">إغلاق</button>
            </div>
        </div>
    </div>

    <!-- Soft Delete Confirmation Modal -->
    <div id="softDeleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="color: var(--warning);">⚠️ حذف مؤقت (Soft Delete)</h2>
            </div>
            <form id="soft-delete-form">
                <div class="modal-body">
                    <input type="hidden" name="store_id" id="soft-delete-store-id">
                    <p style="margin-bottom: 1rem; color: #6b7280;">
                        سيتم إيقاف المحل مع الحفاظ على جميع البيانات. يمكن استعادته لاحقاً.
                    </p>
                    <div class="form-group">
                        <label>للتأكيد، اكتب اسم المحل:</label>
                        <input type="text" name="confirmation" id="soft-delete-confirm" required 
                               placeholder="اسم المحل" autocomplete="off">
                        <small style="display: block; margin-top: 5px; color: #9ca3af;">Expected: <strong id="soft-delete-expected-name"></strong></small>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeSoftDeleteModal()">إلغاء</button>
                    <button type="submit" class="btn btn-warning">حذف مؤقت</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hard Delete Confirmation Modal -->
    <div id="hardDeleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background: #fee2e2; border-bottom-color: #fca5a5;">
                <h2 style="color: var(--danger);">⚠️ حذف نهائي - خطير!</h2>
            </div>
            <form id="hard-delete-form">
                <div class="modal-body">
                    <input type="hidden" name="store_id" id="hard-delete-store-id">
                    <div style="background: #fef2f2; border: 2px solid #fca5a5; padding: 15px; border-radius: 8px; margin-bottom: 1rem;">
                        <p style="margin: 0; color: #b91c1c; font-weight: 600;">
                            ⚠️ هذا الإجراء غير قابل للتراجع! سيتم حذف المحل نهائياً من السجلات.
                        </p>
                    </div>
                    <div class="form-group">
                        <label>للتأكيد الأول، اكتب اسم المحل:</label>
                        <input type="text" name="confirm_store_name" id="hard-delete-confirm-name" required 
                               placeholder="اسم المحل" autocomplete="off">
                        <small style="display: block; margin-top: 5px; color: #9ca3af;">Expected: <strong id="hard-delete-expected-name"></strong></small>
                    </div>
                    <div class="form-group">
                        <label>للتأكيد الثاني، اكتب كلمة <code style="color: #dc2626;">DELETE</code>:</label>
                        <input type="text" name="confirm_delete" id="hard-delete-confirm-delete" required 
                               placeholder="DELETE" autocomplete="off" style="text-transform: uppercase;">
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; background: #fee; padding: 10px; border-radius: 6px; border: 2px solid #fca5a5;">
                            <input type="checkbox" name="delete_database" value="true" id="hard-delete-db" required>
                            <span style="font-weight: 600; color: #b91c1c;">
                                ✅ تأكيد: أوافق على حذف قاعدة البيانات نهائياً (إلزامي)
                            </span>
                        </label>
                        <small style="display: block; margin-top: 5px; color: #dc2626;">
                            ⚠️ يجب تفعيل هذا الخيار لإتمام الحذف النهائي - جميع البيانات ستحذف نهائياً!
                        </small>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeHardDeleteModal()">إلغاء</button>
                    <button type="submit" id="btn-confirm-hard-delete" class="btn btn-danger" disabled>
                        حذف نهائي
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadStores();
            loadDeletedStores();
            loadLicenseKeys();
            loadAuditLogs();
        });

        // Load Stores
        function loadStores() {
            fetch('api/master/stores.php?action=list')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderStores(data.data);
                    } else {
                        document.getElementById('stores-container').innerHTML = '<div class="loading" style="color: var(--danger);">خطأ في تحميل البيانات</div>';
                    }
                })
                .catch(err => {
                    console.error('Error loading stores:', err);
                    document.getElementById('stores-container').innerHTML = '<div class="loading" style="color: var(--danger);">حدث خطأ في الاتصال</div>';
                });
        }

        // Load Deleted Stores (Trash)
        function loadDeletedStores() {
            fetch('api/master/stores.php?action=list&include_deleted=true')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Filter only deleted stores
                        const deletedStores = data.data.filter(store => store.status === 'deleted');
                        renderDeletedStores(deletedStores);
                    } else {
                        document.getElementById('deleted-stores-container').innerHTML = '<div class="loading" style="color: var(--danger);">خطأ في تحميل البيانات</div>';
                    }
                })
                .catch(err => {
                    console.error('Error loading deleted stores:', err);
                    document.getElementById('deleted-stores-container').innerHTML = '<div class="loading" style="color: var(--danger);">حدث خطأ في الاتصال</div>';
                });
        }

        function renderDeletedStores(stores) {
            if (stores.length === 0) {
                document.getElementById('deleted-stores-container').innerHTML = '<div class="loading" style="color: var(--success);"><i class="fas fa-check-circle"></i> لا توجد محلات محذوفة - سلة المحذوفات فارغة</div>';
                return;
            }

            let html = '<table><thead><tr>';
            html += '<th>اسم المحل</th><th>المالك</th><th>قاعدة البيانات</th><th>تاريخ الحذف</th><th>حذف بواسطة</th><th>إجراءات</th>';
            html += '</tr></thead><tbody>';

            stores.forEach(store => {
                const deletedAt = store.deleted_at ? new Date(store.deleted_at).toLocaleString('en-GB') : 'غير محدد';
                const deletedBy = store.deleted_by || 'غير محدد';
                const activationCode = store.activation_code_used || store.license_code || '-';

                html += `<tr style="background-color: #fef2f2;">
                    <td><strong>${escapeHtml(store.store_name)}</strong></td>
                    <td>
                        ${escapeHtml(store.owner_name)}<br>
                        <small style="color: #9ca3af; direction: ltr; display: inline-block;">${escapeHtml(store.owner_email)}</small>
                    </td>
                    <td><code>${escapeHtml(store.database_name)}</code></td>
                    <td style="white-space: nowrap;">${deletedAt}</td>
                    <td>ID: ${deletedBy}</td>
                    <td style="white-space: nowrap;">
                        <button class="btn btn-primary" style="padding: 6px 12px; font-size: 0.85rem; margin: 2px;" onclick="restoreStore(${store.id})" title="استعادة المحل">
                            <i class="fas fa-undo"></i> استعادة
                        </button>
                        <button class="btn btn-danger" style="padding: 6px 12px; font-size: 0.85rem; margin: 2px;" onclick="showHardDeleteModal(${store.id}, '${escapeHtml(store.store_name)}')" title="حذف نهائي">
                            <i class="fas fa-skull-crossbones"></i> حذف نهائي
                        </button>
                    </td>
                </tr>`;
            });

            html += '</tbody></table>';
            document.getElementById('deleted-stores-container').innerHTML = html;
        }

        function renderStores(stores) {
            if (stores.length === 0) {
                document.getElementById('stores-container').innerHTML = '<div class="loading">لا توجد محلات مسجلة حالياً</div>';
                return;
            }

            let html = '<table><thead><tr>';
            html += '<th>اسم المحل</th><th>المالك</th><th>قاعدة البيانات</th><th>كود التفعيل</th><th>الحالة</th><th>آخر دخول</th><th>إجراءات</th>';
            html += '</tr></thead><tbody>';

            stores.forEach(store => {
                const statusClass = store.status === 'active' ? 'status-active' : 
                                     (store.status === 'deleted' ? 'status-suspended' : 'status-suspended');
                const statusText = store.status === 'active' ? 'نشط' : 
                                  (store.status === 'deleted' ? 'محذوف' : 'موقوف');
                const activationCode = store.activation_code_used || store.license_code || '-';
                const lastLogin = store.last_login ? new Date(store.last_login).toLocaleDateString('en-GB') : 'لم يدخل بعد';

                html += `<tr>
                    <td><strong>${escapeHtml(store.store_name)}</strong></td>
                    <td>
                        ${escapeHtml(store.owner_name)}<br>
                        <small style="color: #9ca3af; direction: ltr; display: inline-block;">${escapeHtml(store.owner_email)}</small>
                    </td>
                    <td><code>${escapeHtml(store.database_name)}</code></td>
                    <td><code>${escapeHtml(activationCode)}</code></td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td>${lastLogin}</td>
                    <td style="white-space: nowrap;">`;
                
                // Action buttons based on status
                if (store.status === 'deleted') {
                    html += `
                        <button class="btn btn-primary" style="padding: 4px 10px; font-size: 0.8rem; margin: 2px;" onclick="restoreStore(${store.id})" title="استعادة">
                            <i class="fas fa-undo"></i>
                        </button>
                    `;
                } else {
                    // View Details
                    html += `
                        <button class="btn btn-primary" style="padding: 4px 10px; font-size: 0.8rem; margin: 2px;" onclick="viewStoreDetails(${store.id})" title="عرض التفاصيل">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    `;
                    
                    // Login as Store (Impersonation)
                   html += `
                        <button class="btn btn-warning" style="padding: 4px 10px; font-size: 0.8rem; margin: 2px; background: #dbeafe; color: #1e40af; border-color: #93c5fd;" onclick="loginAsStore(${store.id}, '${escapeHtml(store.store_name)}')" title="دخول للمحل">
                            <i class="fas fa-sign-in-alt"></i>
                        </button>
                    `;
                    
                    // Toggle Status
                    const toggleText = store.status === 'active' ? 'إيقاف' : 'تفعيل';
                    const toggleIcon = store.status === 'active' ? 'fa-ban' : 'fa-check';
                    const toggleAction = store.status === 'active' ? 'suspended' : 'active';
                    html += `
                        <button class="btn btn-warning" style="padding: 4px 10px; font-size: 0.8rem; margin: 2px;" onclick="toggleStoreStatus(${store.id}, '${toggleAction}')" title="${toggleText}">
                            <i class="fas ${toggleIcon}"></i>
                        </button>
                    `;
                    
                    // Soft Delete
                    html += `
                        <button class="btn btn-warning" style="padding: 4px 10px; font-size: 0.8rem; margin: 2px;" onclick="showSoftDeleteModal(${store.id}, '${escapeHtml(store.store_name)}')" title="حذف مؤقت">
                            <i class="fas fa-trash"></i>
                        </button>
                    `;
                }
                
                // Hard Delete (always available, even for deleted stores)
                html += `
                    <button class="btn btn-danger" style="padding: 4px 10px; font-size: 0.8rem; margin: 2px;" onclick="showHardDeleteModal(${store.id}, '${escapeHtml(store.store_name)}')" title="حذف نهائي">
                        <i class="fas fa-skull-crossbones"></i>
                    </button>
                `;
                
                html += `</td></tr>`;
            });

            html += '</tbody></table>';
            document.getElementById('stores-container').innerHTML = html;
        }

        // Load License Keys
        function loadLicenseKeys() {
            fetch('api/master/license_keys.php?action=list')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderLicenseKeys(data.data);
                    } else {
                        document.getElementById('keys-container').innerHTML = '<div class="loading" style="color: var(--danger);">خطأ في تحميل البيانات</div>';
                    }
                })
                .catch(err => {
                    console.error('Error loading keys:', err);
                    document.getElementById('keys-container').innerHTML = '<div class="loading" style="color: var(--danger);">حدث خطأ في الاتصال</div>';
                });
        }

        function renderLicenseKeys(keys) {
            if (keys.length === 0) {
                document.getElementById('keys-container').innerHTML = '<div class="loading">لا توجد رموز تفعيل</div>';
                return;
            }

            let html = '<table><thead><tr>';
            html += '<th>الرمز</th><th>الحالة</th><th>المحل المستخدم</th><th>تاريخ الإنشاء</th><th>تاريخ الاستخدام</th><th>إجراءات</th>';
            html += '</tr></thead><tbody>';

            keys.forEach(key => {
                let statusClass = 'status-unused';
                let statusText = 'غير مستخدم';
                if (key.status === 'used') {
                    statusClass = 'status-used';
                    statusText = 'مستخدم';
                } else if (key.status === 'expired') {
                    statusClass = 'status-expired';
                    statusText = 'منتهي';
                }

                html += `<tr>
                    <td><code>${escapeHtml(key.code)}</code></td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td>${key.store_name ? '<strong>'+escapeHtml(key.store_name)+'</strong>' : '<span style="color:#9ca3af">-</span>'}</td>
                    <td>${new Date(key.created_at).toLocaleDateString('en-GB')}</td>
                    <td>${key.used_at ? new Date(key.used_at).toLocaleDateString('en-GB') : '-'}</td>
                    <td>`;
                
                if (key.status === 'unused') {
                    html += `<div style="display:flex; gap:5px;">`;
                    html += `<button class="btn btn-warning" style="padding: 4px 8px; font-size: 0.8rem;" onclick="expireKey(${key.id})" title="إنهاء الصلاحية"><i class="fas fa-times-circle"></i></button> `;
                    html += `<button class="btn btn-danger" style="padding: 4px 8px; font-size: 0.8rem;" onclick="deleteKey(${key.id})" title="حذف نهائي"><i class="fas fa-trash"></i></button>`;
                    html += `</div>`;
                } else {
                     html += `<span style="color:#9ca3af; font-size:0.8rem;">لا يوجد إجراء</span>`;
                }
                
                html += `</td></tr>`;
            });

            html += '</tbody></table>';
            document.getElementById('keys-container').innerHTML = html;
        }

        // Toggle Store Status
        function toggleStoreStatus(storeId, newStatus) {
            if (!confirm('هل أنت متأكد من تغيير حالة المحل؟')) return;

            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('store_id', storeId);
            formData.append('status', newStatus);

            fetch('api/master/stores.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // alert('تم تحديث الحالة بنجاح');
                    loadStores(); // Reload seamlessly
                } else {
                    alert('حدث خطأ: ' + (data.message || 'خطأ غير معروف'));
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('حدث خطأ في الاتصال');
            });
        }

        // Generate License Key
        function showGenerateModal() {
            document.getElementById('generateModal').style.display = 'block';
            document.getElementById('expires_at').focus();
        }

        function closeGenerateModal() {
            document.getElementById('generateModal').style.display = 'none';
            document.getElementById('generate-form').reset();
        }

        document.getElementById('generate-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإنشاء...';
            btn.disabled = true;

            const formData = new FormData(this);
            formData.append('action', 'generate');

            fetch('api/master/license_keys.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                if (data.status === 'success') {
                    // alert('تم إنشاء الرمز بنجاح: ' + data.data.code);
                    closeGenerateModal();
                    loadLicenseKeys();
                } else {
                    alert('حدث خطأ: ' + (data.message || 'خطأ غير معروف'));
                }
            })
            .catch(err => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                console.error('Error:', err);
                alert('حدث خطأ في الاتصال');
            });
        });

        // Expire Key
        function expireKey(keyId) {
            if (!confirm('هل أنت متأكد من إنهاء هذا الرمز؟ لن يمكن استخدامه بعد الآن.')) return;

            const formData = new FormData();
            formData.append('action', 'expire');
            formData.append('key_id', keyId);

            fetch('api/master/license_keys.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    loadLicenseKeys();
                } else {
                    alert('حدث خطأ: ' + (data.message || 'خطأ غير معروف'));
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('حدث خطأ في الاتصال');
            });
        }

        // Delete Key
        function deleteKey(keyId) {
            if (!confirm('هل أنت متأكد من حذف هذا الرمز نهائياً من السجلات؟')) return;

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('key_id', keyId);

            fetch('api/master/license_keys.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    loadLicenseKeys();
                } else {
                    alert('حدث خطأ: ' + (data.message || 'خطأ غير معروف'));
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('حدث خطأ في الاتصال');
            });
        }

        // Utility function
        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('generateModal');
            if (event.target === modal) {
                closeGenerateModal();
            }
        }
    </script>
    <script src="../assets/js/master_dashboard_enhanced.js"></script>
</body>
</html>
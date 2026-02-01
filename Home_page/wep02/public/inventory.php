<?php
require_once __DIR__ . '/../includes/session_check.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة محل فساتين الزفاف | إدارة المخزون</title>
    <link rel="icon" type="image/png" href="../assets/img/logo-transparent.png?v=<?php echo time(); ?>">
    <link rel="shortcut icon" type="image/png" href="../assets/img/logo-transparent.png?v=<?php echo time(); ?>">
    <!-- Load Font Awesome non-blocking to avoid render-block delay when CDN is slow -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="../assets/css/index-style.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/inventory.css">
    <link rel="stylesheet" href="../assets/css/mobile-optimizations.css">
</head>
<body>
    <?php
    $activePage = 'inventory';
    include __DIR__ . '/../includes/navbar.php';
    ?>

    <main class="main-content">
    <main class="main-content">
        <section class="inventory-header">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1 class="section-title" style="margin: 0;"><i class="fas fa-tshirt"></i> إدارة المخزون</h1>
                <button class="btn primary" id="add-dress-btn">
                    <i class="fas fa-plus"></i> إضافة فستان جديد
                </button>
            </div>
        </section>

        <section class="inventory-tabs">
            <div class="tabs">
                <div class="tab active" data-tab="all-dresses">جميع الفساتين</div>
                <div class="tab" data-tab="categories">الفئات</div>
                <div class="tab" data-tab="movements">حركة المخزون</div>
            </div>
            
            <!-- All Dresses Tab -->
            <div class="tab-content active" id="all-dresses-content">
                <div class="section-card">
                    <div class="search-filter" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 20px;">
                        <div class="search-box" style="flex: 2; min-width: 250px;">
                            <i class="fas fa-search"></i>
                            <input type="text" id="dress-search" placeholder="بحث عن فستان..." style="width: 100%;">
                        </div>
                        
                        <div class="filter-select" style="flex: 1; min-width: 150px;">
                            <select id="category-filter" style="width: 100%;">
                                <option value="">جميع الفئات</option>
                                <option value="1">فساتين زفاف</option>
                                <option value="2">فساتين سهرة</option>
                                <option value="3">طرح</option>
                                <option value="4">اكسسوارات</option>
                                <option value="5">عبايات</option>
                            </select>
                        </div>
                        
                        <div class="filter-select" style="flex: 1; min-width: 150px;">
                            <select id="size-filter" style="width: 100%;">
                                <option value="">جميع المقاسات</option>
                                <option value="XS">XS</option>
                                <option value="S">S</option>
                                <option value="M">M</option>
                                <option value="L">L</option>
                                <option value="XL">XL</option>
                                <option value="XXL">XXL</option>
                            </select>
                        </div>
                        
                        <div class="filter-select" style="flex: 1; min-width: 150px;">
                            <select id="status-filter" style="width: 100%;">
                                <option value="">جميع الحالات</option>
                                <option value="available">متاح</option>
                                <option value="low">مخزون منخفض</option>
                                <option value="unavailable">غير متاح</option>
                            </select>
                        </div>
                    </div>
                
                <div class="inventory-table-container">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>الصورة</th>
                                <th>الكود</th>
                                <th>اسم الفستان</th>
                                <th>الفئة</th>
                                <th>السعر</th>
                                <th>اللون</th>
                                <th>الكمية</th>
                                <th>الحالة</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="inventory-table-body">
                            <!-- سيتم إضافة الصفوف بواسطة JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                    <div class="pagination">
                        <button class="pagination-btn" id="prev-page"><i class="fas fa-chevron-right"></i></button>
                        <div class="pagination-numbers" id="pagination-numbers">
                            <!-- سيتم إضافة أرقام الصفحات بواسطة JavaScript -->
                        </div>
                        <button class="pagination-btn" id="next-page"><i class="fas fa-chevron-left"></i></button>
                    </div>
                </div>
            </div>
            
            <!-- Categories Tab -->
            <div class="tab-content" id="categories-content">
                <div class="categories-container">
                    <div class="categories-list">
                        <h3 class="sub-title">الفئات الحالية</h3>
                        <div class="category-cards" id="category-cards">
                            <!-- سيتم إضافة البطاقات بواسطة JavaScript -->
                        </div>
                    </div>
                    
                    <div class="category-form-container">
                        <h3 class="sub-title">إضافة / تعديل فئة</h3>
                        <form id="category-form">
                            <input type="hidden" id="category-id">
                            <div class="form-group">
                                <label for="category-name">اسم الفئة:</label>
                                <input type="text" id="category-name" required>
                            </div>
                            <div class="form-group">
                                <label for="category-description">الوصف:</label>
                                <textarea id="category-description"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="category-color">لون الفئة:</label>
                                <input type="color" id="category-color" value="#9c27b0">
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn secondary" id="reset-category-btn">إلغاء</button>
                                <button type="submit" class="btn primary">حفظ الفئة</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Suppliers Tab -->
            <div class="tab-content" id="suppliers-content">
                <div class="suppliers-container">
                    <div class="suppliers-list">
                        <h3 class="sub-title">الموردين الحاليين</h3>
                        <div class="search-box" style="margin-bottom: 20px;">
                            <i class="fas fa-search"></i>
                            <input type="text" id="supplier-search" placeholder="بحث عن مورد...">
                        </div>
                        <table class="suppliers-table">
                            <thead>
                                <tr>
                                    <th>الاسم</th>
                                    <th>جهة الاتصال</th>
                                    <th>رقم الهاتف</th>
                                    <th>البريد الإلكتروني</th>
                                    <th>العنوان</th>
                                    <th>عدد المنتجات</th>
                                    <th>إجراءات</th>
                                </tr>
                            </thead>
                            <tbody id="suppliers-table-body">
                                <!-- سيتم إضافة الصفوف بواسطة JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="supplier-form-container">
                        <h3 class="sub-title">إضافة / تعديل مورد</h3>
                        <form id="supplier-form">
                            <input type="hidden" id="supplier-id">
                            <div class="form-group">
                                <label for="supplier-name">اسم المورد / الشركة:</label>
                                <input type="text" id="supplier-name" required>
                            </div>
                            <div class="form-group">
                                <label for="supplier-contact">اسم جهة الاتصال:</label>
                                <input type="text" id="supplier-contact">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="supplier-phone">رقم الهاتف:</label>
                                    <input type="tel" id="supplier-phone" required>
                                </div>
                                <div class="form-group">
                                    <label for="supplier-email">البريد الإلكتروني:</label>
                                    <input type="email" id="supplier-email">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="supplier-address">العنوان:</label>
                                <textarea id="supplier-address"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="supplier-notes">ملاحظات:</label>
                                <textarea id="supplier-notes"></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn secondary" id="reset-supplier-btn">إلغاء</button>
                                <button type="submit" class="btn primary">حفظ المورد</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Inventory Movements Tab -->
            <div class="tab-content" id="movements-content">
                <div class="movements-header">
                    <h3 class="sub-title">حركة المخزون</h3>
                    <div class="movements-filters">
                        <div class="date-filter">
                            <label for="date-from">من تاريخ:</label>
                            <input type="date" id="date-from">
                        </div>
                        <div class="date-filter">
                            <label for="date-to">إلى تاريخ:</label>
                            <input type="date" id="date-to">
                        </div>
                        <div class="filter-select">
                            <label for="movement-type">نوع الحركة:</label>
                            <select id="movement-type">
                                <option value="">الكل</option>
                                <option value="in">إضافة مخزون</option>
                                <option value="out">سحب مخزون</option>
                                <option value="adjustment">تعديل مخزون</option>
                            </select>
                        </div>
                        <button class="btn primary" id="filter-movements-btn">
                            <i class="fas fa-filter"></i> تصفية
                        </button>
                    </div>
                </div>
                
                <div class="movements-table-container">
                    <table class="movements-table">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>رقم المرجع</th>
                                <th>كود الفستان</th>
                                <th>اسم الفستان</th>
                                <th>نوع الحركة</th>
                                <th>الكمية</th>
                                <th>الرصيد بعد</th>
                                <th>المستخدم</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody id="movements-table-body">
                            <!-- سيتم إضافة الصفوف بواسطة JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <div class="pagination">
                    <button class="pagination-btn" id="movements-prev-page"><i class="fas fa-chevron-right"></i></button>
                    <div class="pagination-numbers" id="movements-pagination-numbers">
                        <!-- سيتم إضافة أرقام الصفحات بواسطة JavaScript -->
                    </div>
                    <button class="pagination-btn" id="movements-next-page"><i class="fas fa-chevron-left"></i></button>
                </div>
            </div>
        </section>
    </main>
    
    <footer class="footer">
        <div class="footer-content">
            <div class="copyright">
                <p>© 2023 نظام إدارة محل فساتين الزفاف. جميع الحقوق محفوظة.</p>
            </div>
            <div class="version">
                <p>الإصدار 2.1.0</p>
            </div>
        </div>
    </footer>

    <!-- Add Dress Modal -->
    <div id="dress-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="dress-modal-title">إضافة فستان جديد</h2>
            <form id="dress-form" enctype="multipart/form-data"> <input type="hidden" id="dress-id" name="id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="dress-code">كود الفستان:</label>
                        <input type="text" id="dress-code" name="code" required>
                    </div>
                    <div class="form-group">
                        <label for="dress-name">اسم الفستان:</label>
                        <input type="text" id="dress-name" name="name" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="dress-category">الفئة:</label>
                        <select id="dress-category" name="category_id" required>
                            <option value="">اختر الفئة</option>
                            <option value="1">فساتين زفاف</option>
                            <option value="2">فساتين سهرة</option>
                            <option value="3">طرح</option>
                            <option value="4">اكسسوارات</option>
                            <option value="5">عبايات</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="dress-price">سعر البيع:</label>
                        <input type="number" id="dress-price" name="price" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="dress-cost">سعر التكلفة:</label>
                        <input type="number" id="dress-cost" name="cost" min="0" step="0.01" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="dress-color">اللون:</label>
                        <input type="text" id="dress-color" name="color" required>
                    </div>
                    <div class="form-group">
                        <label for="dress-size">المقاس:</label>
                        <select id="dress-size" name="size" required>
                            <option value="">اختر المقاس</option>
                            <option value="XS">XS</option>
                            <option value="S">S</option>
                            <option value="M">M</option>
                            <option value="L">L</option>
                            <option value="XL">XL</option>
                            <option value="XXL">XXL</option>
                            <option value="XXXL">XXXL</option>
                            <option value="مقاس مختلف">مقاس مختلف</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="dress-fabric">نوع القماش:</label>
                        <input type="text" id="dress-fabric" name="fabric_type" placeholder="مثال: ستان، شيفون، دانتيل...">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="dress-quantity">الكمية:</label>
                        <input type="number" id="dress-quantity" name="quantity" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="dress-description">الوصف:</label>
                    <textarea id="dress-description" name="description"></textarea>
                </div>

                <div class="form-group">
                    <label for="dress-image">صورة الفستان:</label>
                    <input type="file" id="dress-image" name="dress_image" accept="image/*">
                </div>

                <div class="form-actions">
                    <button type="button" class="btn secondary" id="cancel-dress-btn">إلغاء</button>
                    <button type="submit" class="btn primary">حفظ الفستان</button>
                </div>
            </form>
        </div>
    </div>
    <!-- View Dress Details Modal -->
    <div id="dress-details-modal" class="modal">
        <div class="modal-content details-modal">
            <span class="close">&times;</span>
            <div class="dress-details-header">
                <div class="dress-image">
                    <!-- Inline placeholder image to avoid extra network round-trip on page load -->
                    <img id="detail-dress-image" src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='150' height='150'><rect width='150' height='150' fill='%23e0e0e0'/><text x='50%25' y='55%25' font-size='14' text-anchor='middle' fill='%23666' font-family='Arial' dy='.3em'>صورة</text></svg>" alt="صورة الفستان">
                </div>
                <div class="dress-basic-info">
                    <h2 id="detail-dress-name"></h2>
                    <p id="detail-dress-code" class="dress-code"></p>
                    <p id="detail-dress-category" class="dress-category"></p>
                </div>
            </div>
            <div class="dress-details-content">
                <div class="details-section">
                    <h3><i class="fas fa-info-circle"></i> معلومات أساسية</h3>
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="detail-label">سعر البيع:</span>
                            <span id="detail-dress-price" class="detail-value"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">سعر التكلفة:</span>
                            <span id="detail-dress-cost" class="detail-value"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">المقاس:</span>
                            <span id="detail-dress-size" class="detail-value"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">اللون:</span>
                            <span id="detail-dress-color" class="detail-value"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">الكمية الحالية:</span>
                            <span id="detail-dress-quantity" class="detail-value"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">الحد الأدنى للمخزون:</span>
                            <span id="detail-dress-min-quantity" class="detail-value"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">عدد مرات التأجير:</span>
                            <span id="detail-dress-view-count" class="detail-value">0</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">تاريخ الإضافة:</span>
                            <span id="detail-dress-date" class="detail-value"></span>
                        </div>
                    </div>
                </div>
                
                <div class="details-section">
                    <h3><i class="fas fa-align-left"></i> الوصف</h3>
                    <p id="detail-dress-description" class="dress-description"></p>
                </div>
                
                <div class="details-section">
                    <h3><i class="fas fa-history"></i> آخر الحركات</h3>
                    <div class="dress-movements" id="dress-movements">
                        <!-- سيتم إضافة الحركات بواسطة JavaScript -->
                    </div>
                </div>
            </div>
            <div class="dress-details-actions">
                <button class="btn warning" id="edit-dress-btn">
                    <i class="fas fa-edit"></i> تعديل
                </button>
                <button class="btn success" id="add-stock-btn">
                    <i class="fas fa-plus-circle"></i> إضافة مخزون
                </button>
                <button class="btn danger" id="delete-dress-btn">
                    <i class="fas fa-trash"></i> حذف
                </button>
            </div>
        </div>
    </div>

    <!-- Add Stock Modal -->
    <div id="add-stock-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>إضافة مخزون</h2>
            <form id="add-stock-form">
                <input type="hidden" id="stock-dress-id">
                <div class="form-group">
                    <label for="stock-dress-name">الفستان:</label>
                    <input type="text" id="stock-dress-name" readonly>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="stock-current-quantity">الكمية الحالية:</label>
                        <input type="number" id="stock-current-quantity" readonly>
                    </div>
                    <div class="form-group">
                        <label for="stock-quantity">الكمية المضافة:</label>
                        <input type="number" id="stock-quantity" min="1" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="stock-supplier">المورد:</label>
                    <select id="stock-supplier">
                        <option value="">اختر المورد</option>
                        <!-- سيتم إضافة الخيارات بواسطة JavaScript -->
                    </select>
                </div>
                <div class="form-group">
                    <label for="stock-notes">ملاحظات:</label>
                    <textarea id="stock-notes"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn secondary" id="cancel-stock-btn">إلغاء</button>
                    <button type="submit" class="btn primary">إضافة المخزون</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-confirm-modal" class="modal">
        <div class="modal-content confirm-modal">
            <h2><i class="fas fa-exclamation-triangle"></i> تأكيد الحذف</h2>
            <p>هل أنت متأكد من حذف هذا الفستان؟ لا يمكن التراجع عن هذا الإجراء.</p>
            <div class="confirm-actions">
                <button class="btn secondary" id="cancel-delete-btn">إلغاء</button>
                <button class="btn danger" id="confirm-delete-btn">تأكيد الحذف</button>
            </div>
        </div>
    </div>

    <!-- Core Utilities (Load First) -->
    <script src="../assets/js/core/api-client.js"></script>
    <script src="../assets/js/core/formatters.js"></script>
    <script src="../assets/js/core/dom-utils.js"></script>
    <script src="../assets/js/core/validators.js"></script>
    
    <!-- Application Scripts -->
    <script src="../assets/js/inventory.js"></script>
</body>
</html>

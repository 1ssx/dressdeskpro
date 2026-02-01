<?php
/**
 * Shared Navigation Bar / Sidebar Component
 * 
 * Usage: include this file in any page that needs the sidebar
 * 
 * @param string $activePage The current active page identifier
 * @param string $userName Optional user name
 */

// Default active page
$activePage = $activePage ?? 'index';

// Get user name logic
$displayName = $userName ?? ($currentUser['name'] ?? 'مدير المبيعات');

// Store logo logic (kept for fallback or other uses, though we use fixed title "لمسات الأسطورة")
$storeLogo = null;
$logoUrl = null;
if (isset($_SESSION['store_id'])) {
    try {
        $pdoMaster = require __DIR__ . '/../app/config/master_database.php';
        $stmt = $pdoMaster->prepare("SELECT logo_path FROM stores WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['store_id']]);
        $storeLogo = $stmt->fetchColumn();
        if ($storeLogo) {
            $logoFullPath = __DIR__ . '/../' . $storeLogo;
            if (file_exists($logoFullPath)) {
                $logoUrl = '../' . $storeLogo;
            }
        }
    } catch (Exception $e) {
        error_log('Error fetching store logo: ' . $e->getMessage());
    }
}
?>

<?php
// Include impersonation banner if system owner is impersonating
include __DIR__ . '/impersonation_banner.php';
?>

<!-- Mobile Header (Visible only on mobile) -->
<header class="mobile-header">
    <button class="mobile-menu-toggle" id="mobile-menu-toggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="mobile-logo">
        <h1>لمسات الأسطورة</h1>
    </div>
    <div class="mobile-user">
        <!-- Notification Icon Placeholder -->
        <?php if (isset($showDeliveryBadge) && $showDeliveryBadge): ?>
             <i class="fas fa-bell notification-icon"></i>
        <?php endif; ?>
    </div>
</header>

<!-- Sidebar (Fixed Right) -->
<aside class="sidebar" id="sidebar">
    <!-- Header Section -->
    <div class="sidebar-header">
        <div class="logo-container" style="margin-bottom: 15px; display: flex; justify-content: center;">
            <?php if ($logoUrl): ?>
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Store Logo" style="max-width: 80px; height: auto; display: block; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));">
            <?php else: ?>
                <div class="crown-icon">
                    <i class="fas fa-crown"></i>
                </div>
            <?php endif; ?>
        </div>
        <h1 class="system-name"><?php echo htmlspecialchars($_SESSION['store_name'] ?? 'لمسات الأسطورة'); ?></h1>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <ul>
            <li class="<?php echo $activePage === 'index' ? 'active' : ''; ?>">
                <a href="index.php">
                    <i class="fas fa-home"></i>
                    <span>نظرة عامة</span>
                </a>
            </li>
            <li class="<?php echo $activePage === 'inventory' ? 'active' : ''; ?>">
                <a href="inventory.php">
                    <i class="fas fa-tshirt"></i>
                    <span>إدارة المخزون</span>
                </a>
            </li>
            <li class="<?php echo $activePage === 'customer' ? 'active' : ''; ?>">
                <a href="customer.php">
                    <i class="fas fa-users"></i>
                    <span>العملاء</span>
                </a>
            </li>
            <li class="<?php echo $activePage === 'sales' ? 'active' : ''; ?>">
                <a href="sales.php">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>الفواتير والمبيعات</span>
                </a>
            </li>
            <li class="<?php echo $activePage === 'bookings' ? 'active' : ''; ?>">
                <a href="bookings.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>المواعيد والقياسات</span>
                </a>
            </li>
            <li class="<?php echo $activePage === 'invoices_archive' ? 'active' : ''; ?>">
                <a href="invoices_archive.php">
                    <i class="fas fa-archive"></i>
                    <span>أرشيف الفواتير</span>
                </a>
            </li>
            <li class="<?php echo $activePage === 'receivables' ? 'active' : ''; ?>">
                <a href="receivables_report.php">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span>تقرير الذمم</span>
                </a>
            </li>
            <li class="<?php echo $activePage === 'reports' ? 'active' : ''; ?>">
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>التقارير والإحصائيات</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- User Section -->
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn" title="تسجيل الخروج">
            <i class="fas fa-sign-out-alt"></i>
        </a>
        <div class="user-info">
            <div class="user-text">
                <span class="name"><?php echo htmlspecialchars($displayName); ?></span>
                <span class="role">مدير النظام</span>
            </div>
        </div>
        <a href="settings.php" class="user-avatar" title="الإعدادات">
             <i class="fas fa-user-circle"></i>
        </a>
    </div>
</aside>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggle = document.getElementById('mobile-menu-toggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        if(toggle) {
            toggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            });
        }

        if(overlay) {
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
        }
    });
</script>

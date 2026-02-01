<?php
/**
 * Impersonation Banner Component
 * Shows a warning banner when System Owner is impersonating a store
 * Include this in navbar.php or at the top of store pages
 */

// Check if impersonation is active
if (isset($_SESSION['impersonation_active']) && $_SESSION['impersonation_active'] === true) {
    $masterName = $_SESSION['impersonation_master_name'] ?? 'System Owner';
    $storeName = $_SESSION['store_name'] ?? 'Unknown Store';
?>
<div id="impersonation-banner" style="
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    padding: 12px 20px;
    z-index: 9999;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-family: 'Cairo', sans-serif;
    border-bottom: 3px solid #991b1b;
">
    <div style="display: flex; align-items: center; gap: 15px;">
        <div style="
            background: rgba(255,255,255,0.2);
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        ">
            <i class="fas fa-user-shield"></i>
            <span>SYSTEM OWNER MODE</span>
        </div>
        
        <div style="font-size: 0.95rem;">
            <strong><?php echo htmlspecialchars($masterName); ?></strong> 
            <span style="opacity: 0.9;">مسجل دخول كمدير لمحل:</span>
            <strong style="background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 6px; margin-right: 8px;">
                <?php echo htmlspecialchars($storeName); ?>
            </strong>
        </div>
    </div>
    
    <div style="display: flex; gap: 10px; align-items: center;">
        <div style="
            background: rgba(255,255,255,0.15);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            opacity: 0.9;
        ">
            <i class="fas fa-exclamation-triangle"></i>
            <span>جميع الإجراءات مسجلة</span>
        </div>
        
        <button onclick="exitImpersonation()" style="
            background: #ffffff;
            color: #dc2626;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Cairo', sans-serif;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        " onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
            <i class="fas fa-sign-out-alt"></i>
            <span>العودة لـ Master Dashboard</span>
        </button>
    </div>
</div>

<script>
function exitImpersonation() {
    if (!confirm('هل أنت متأكد من الخروج من وضع الإدارة والعودة لـ Master Dashboard?')) return;
    
    fetch('api/master/impersonation.php?action=exit_impersonation', {
        method: 'POST'
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            window.location.href = data.redirect || 'master_dashboard.php';
        } else {
            alert('خطأ: ' + (data.message || 'فشل الخروج'));
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('حدث خطأ في الاتصال');
    });
}

// Adjust page content top margin to account for banner
document.addEventListener('DOMContentLoaded', function() {
    const bannerHeight = document.getElementById('impersonation-banner').offsetHeight;
    document.body.style.paddingTop = (bannerHeight + 10) + 'px';
});
</script>

<style>
#impersonation-banner button:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
}

#impersonation-banner button:active {
    transform: scale(0.98) !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    #impersonation-banner {
        flex-direction: column;
        gap: 15px;
        text-align: center;
        padding: 15px;
    }
    
    #impersonation-banner > div {
        flex-direction: column;
        width: 100%;
    }
    
    #impersonation-banner button {
        width: 100%;
        justify-content: center;
    }
}
</style>
<?php
}
?>

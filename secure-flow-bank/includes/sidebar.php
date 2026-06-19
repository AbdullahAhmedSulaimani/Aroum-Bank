<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon"><i class="fas fa-shield-halved"></i></div>
        <div class="logo-text">Aurum<span>Banking Platform</span></div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-title">الرئيسية</div>
        <a href="dashboard.php" class="<?= $currentPage==='dashboard.php'?'active':'' ?>"><i class="fas fa-grid-2"></i><span>لوحة التحكم</span></a>

        <div class="nav-section-title">الحسابات</div>
        <a href="accounts.php" class="<?= $currentPage==='accounts.php'?'active':'' ?>"><i class="fas fa-wallet"></i><span>حساباتي</span></a>
        <a href="cards.php" class="<?= $currentPage==='cards.php'?'active':'' ?>"><i class="fas fa-credit-card"></i><span>البطاقات</span></a>

        <div class="nav-section-title">المعاملات</div>
        <a href="transfers.php" class="<?= $currentPage==='transfers.php'?'active':'' ?>"><i class="fas fa-arrow-right-arrow-left"></i><span>التحويلات</span></a>
        <a href="transactions.php" class="<?= $currentPage==='transactions.php'?'active':'' ?>"><i class="fas fa-clock-rotate-left"></i><span>سجل العمليات</span></a>

        <div class="nav-section-title">الاستثمارات</div>
        <a href="investments.php" class="<?= $currentPage==='investments.php'?'active':'' ?>"><i class="fas fa-chart-line"></i><span>محفظتي الاستثمارية</span></a>

        <div class="nav-section-title">الإعدادات</div>
        <a href="profile.php" class="<?= $currentPage==='profile.php'?'active':'' ?>"><i class="fas fa-user-circle"></i><span>الملف الشخصي</span></a>
        <a href="two_factor_setup.php" class="<?= $currentPage==='two_factor_setup.php'?'active':'' ?>"><i class="fas fa-shield-halved"></i><span>المصادقة الثنائية</span></a>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn"><i class="fas fa-right-from-bracket"></i><span>تسجيل الخروج</span></a>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

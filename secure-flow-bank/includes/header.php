<?php
setSecurityHeaders();
if (!isset($pageTitle))    $pageTitle    = 'Aurum Bank';
if (!isset($pageSubtitle)) $pageSubtitle = '';
if (!isset($unreadNotifications)) $unreadNotifications = 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle) ?> — Aurum Bank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        // Apply theme instantly to avoid flash
        (function(){
            var t = localStorage.getItem('aub_theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <header class="topbar">
        <div class="flex items-center gap-3">
            <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
            <div class="topbar-title">
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
                <?php if ($pageSubtitle): ?><p><?= htmlspecialchars($pageSubtitle) ?></p><?php endif; ?>
            </div>
        </div>
        <div class="topbar-actions">
            <button class="theme-toggle" id="themeToggle" title="تبديل الوضع">
                <i class="fas fa-sun icon-sun"></i>
                <i class="fas fa-moon icon-moon"></i>
            </button>
            <button class="btn-icon" title="الإشعارات">
                <i class="fas fa-bell"></i>
                <?php if ($unreadNotifications > 0): ?>
                    <span class="badge-count"><?= (int)$unreadNotifications ?></span>
                <?php endif; ?>
            </button>
            <div class="user-avatar" title="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>">
                <?= htmlspecialchars(mb_substr($_SESSION['user_name'] ?? 'م', 0, 1)) ?>
            </div>
        </div>
    </header>
    <div class="page-content">

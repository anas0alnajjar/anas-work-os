<?php
$pageTitle = $pageTitle ?? 'Anas Work OS';
$active = $active ?? '';
$flashes = pull_flashes();
$pageDescriptions = [
    'dashboard' => 'نظرة تنفيذية على المتابعات والفرص التي تستحق انتباهك الآن',
    'organizations' => 'نظّم الجهات المستهدفة وحدد أين تستثمر وقتك وعلاقاتك',
    'contacts' => 'تابع المحادثات والخطوات التالية دون أن تضيع أي علاقة',
    'jobs' => 'أدر طلبات التوظيف من الحفظ وحتى العرض الوظيفي',
    'content' => 'حوّل فكرة أسبوعية واحدة إلى ثلاث نسخ واضحة ومناسبة لكل منصة',
    'portfolio_album' => 'أدر صور خبرتك المهنية وسياقها قبل نشرها في موقعك الشخصي',
];
$pageDescription = $pageDescription ?? ($pageDescriptions[$active] ?? 'مساحة عملك المهنية في مكان واحد');
$todayLabel = date('Y/m/d');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#002347">
    <title><?= e($pageTitle) ?> | Anas Work OS</title>
    <link rel="stylesheet" href="assets/style.css?v=20261016-video-album">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="app-sidebar" aria-label="التنقل الرئيسي">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true">AN</span>
            <div class="brand-copy">
                <strong>Anas Work OS</strong>
                <small>نظام العلاقات والفرص</small>
            </div>
            <button class="icon-button sidebar-close" type="button" data-sidebar-close aria-label="إغلاق القائمة">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <div class="nav-label">مساحة العمل</div>
        <nav>
            <a class="<?= $active==='dashboard'?'active':'' ?>" href="dashboard.php">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-16v4h6V4h-6Z"/></svg>
                <span>لوحة التحكم</span>
            </a>
            <a class="<?= $active==='organizations'?'active':'' ?>" href="organizations.php">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21V8l8-5 8 5v13M8 21v-4h8v4M8 11h.01M12 11h.01M16 11h.01"/></svg>
                <span>الجهات</span>
            </a>
            <a class="<?= $active==='contacts'?'active':'' ?>" href="contacts.php">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>الأشخاص والمتابعة</span>
            </a>
            <a class="<?= $active==='jobs'?'active':'' ?>" href="jobs.php">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6V4h6v2M4 8h16v12H4V8Zm0 5h16M10 13v2h4v-2"/></svg>
                <span>فرص العمل</span>
            </a>
            <a class="<?= $active==='content'?'active':'' ?>" href="content_plan.php">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4V5Zm4 4h8M8 13h5M7 2v3M17 2v3"/></svg>
                <span>خطة المحتوى</span>
            </a>
            <a class="<?= $active==='portfolio_album'?'active':'' ?>" href="portfolio_album.php">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4V5Zm3 10 3-3 3 3 2-2 2 2M9 9h.01"/></svg>
                <span>وسائط الموقع</span>
            </a>
        </nav>

        <div class="sidebar-focus">
            <span class="focus-dot" aria-hidden="true"></span>
            <div><strong>قاعدة العمل</strong><small>كل تواصل يجب أن ينتهي بخطوة تالية وموعد واضح.</small></div>
        </div>

        <div class="sidebar-footer">
            <span class="user-avatar" aria-hidden="true"><?= e(strtoupper(substr(current_user()['username'] ?? 'A', 0, 1))) ?></span>
            <a class="user-copy" href="account_settings.php" title="إعدادات الحساب"><small>الحساب الحالي</small><strong><?= e(current_user()['display_name'] ?? current_user()['username'] ?? '') ?></strong></a>
            <a class="logout-link" href="logout.php" aria-label="تسجيل الخروج" title="تسجيل الخروج">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/></svg>
            </a>
        </div>
    </aside>
    <button class="sidebar-overlay" type="button" data-sidebar-close aria-label="إغلاق القائمة"></button>

    <main class="main-content">
        <?php if (!empty($workspaceMode)): ?>
        <header class="workspace-appbar">
            <div class="workspace-appbar-start">
                <button class="icon-button menu-toggle" type="button" data-sidebar-open aria-label="فتح القائمة" aria-controls="app-sidebar" aria-expanded="false">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <a class="workspace-back" href="jobs.php">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                    <span>فرص العمل</span>
                </a>
                <span class="workspace-context">تفاصيل الطلب</span>
            </div>
            <a class="btn small secondary workspace-new" href="job_form.php">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                <span>فرصة جديدة</span>
            </a>
        </header>
        <?php else: ?>
        <header class="topbar">
            <div class="topbar-title">
                <button class="icon-button menu-toggle" type="button" data-sidebar-open aria-label="فتح القائمة" aria-controls="app-sidebar" aria-expanded="false">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <div class="eyebrow"><span>مساحة العمل الشخصية</span><span class="eyebrow-separator">•</span><time><?= e($todayLabel) ?></time></div>
                    <h1><?= e($pageTitle) ?></h1>
                    <p><?= e($pageDescription) ?></p>
                </div>
            </div>
            <div class="top-actions">
                <?php if ($active==='portfolio_album'): ?>
                <a class="btn" href="#album-editor">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    <span>إضافة صورة أو فيديو</span>
                </a>
                <?php elseif ($active==='content'): ?>
                <a class="btn" href="content_topic_form.php">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    <span>موضوع إضافي</span>
                </a>
                <?php else: ?>
                <a class="btn secondary" href="organization_form.php">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    <span>جهة جديدة</span>
                </a>
                <a class="btn" href="job_form.php">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    <span>فرصة عمل</span>
                </a>
                <?php endif; ?>
            </div>
        </header>
        <?php endif; ?>

        <?php foreach($flashes as $f): ?>
            <div class="alert <?= e($f['type']) ?>" role="status">
                <span class="alert-icon" aria-hidden="true">✓</span>
                <span><?= e($f['message']) ?></span>
            </div>
        <?php endforeach; ?>

        <div class="page-content">

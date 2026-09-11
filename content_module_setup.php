<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/options.php';
require __DIR__ . '/includes/content_migrations.php';
require_auth();

$setupConfigured = strlen((string)($config['setup_secret'] ?? '')) >= 32;
$results = [];

if (is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'authorize_setup') {
        $submittedSecret = (string)($_POST['setup_secret'] ?? '');
        $configuredSecret = (string)($config['setup_secret'] ?? '');
        if ($setupConfigured && hash_equals($configuredSecret, $submittedSecret)) {
            session_regenerate_id(true);
            $_SESSION['content_setup_secret_hash'] = hash('sha256', $configuredSecret);
            flash('success', 'تم التحقق من صلاحية تحديث قاعدة البيانات.');
            redirect('content_module_setup.php');
        }
        usleep(400000);
        flash('error', 'رمز إعداد الوحدة غير صحيح.');
        redirect('content_module_setup.php');
    }

    if ($action === 'lock_setup') {
        unset($_SESSION['content_setup_secret_hash']);
        redirect('content_module_setup.php');
    }

    if ($action === 'apply_migrations') {
        if (!content_setup_authorized($config)) {
            http_response_code(403);
            exit('Setup authorization required.');
        }
        $results = content_apply_pending_migrations(db());
    }
}

$authorized = content_setup_authorized($config);
$databaseConnected = false;
$databaseName = '';
$existingTables = [];
$migrationState = ['completed'=>[],'pending'=>array_keys(content_migration_definitions())];
$preflightError = false;

if ($authorized) {
    try {
        $databaseName = (string)scalar('SELECT DATABASE()');
        $databaseConnected = $databaseName !== '';
        $existingTables = content_existing_tables(db());
        $migrationState = content_migration_state(db());
    } catch (Throwable $exception) {
        error_log('[content-setup-preflight] ' . $exception->getMessage());
        $preflightError = true;
    }
}

$requiredTables = content_required_tables();
$pageTitle = 'إعداد وحدة المحتوى';
$pageDescription = 'تحديث آمن يضيف خطة المحتوى ويربطها بمالكها دون المساس بالبيانات الحالية';
$active = '';
require __DIR__ . '/includes/layout_top.php';
?>

<section class="setup-hero">
    <div>
        <span class="setup-kicker">SYSTEM UPDATE · CONTENT MODULE</span>
        <h2>Content &amp; Brand Plan</h2>
        <p>تثبيت آمن لوحدة تخطيط المحتوى وربط مواضيعها وسجلات تذكيرها بالحساب الصحيح.</p>
    </div>
    <span class="setup-shield" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 20 6v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3Z"/><path d="m9 12 2 2 4-4"/></svg></span>
</section>

<?php if (!$setupConfigured): ?>
    <div class="alert error"><span class="alert-icon">!</span><span>أضف قيمة قوية لـ <code>SETUP_SECRET</code> في إعدادات البيئة المحلية قبل متابعة التثبيت.</span></div>
<?php elseif (!$authorized): ?>
    <section class="section setup-auth-card">
        <div class="section-head"><div><h2>تحقق إضافي مطلوب</h2><p>هذه الصفحة تغيّر بنية قاعدة البيانات، لذلك تحتاج جلسة دخول ورمز إعداد مستقل.</p></div></div>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="authorize_setup">
            <div class="field"><label for="setup_secret">رمز إعداد الوحدة</label><input id="setup_secret" type="password" name="setup_secret" required autocomplete="off" placeholder="SETUP_SECRET"></div>
            <button class="btn" type="submit">فتح فحص ما قبل التحديث</button>
        </form>
    </section>
<?php else: ?>
    <?php if ($results): ?>
        <section class="section setup-results">
            <div class="section-head"><div><h2>نتيجة التنفيذ</h2><p>تمت معالجة المهاجرات بالترتيب المسجل.</p></div></div>
            <div class="migration-result-list">
                <?php foreach ($results as $result): ?>
                    <div class="migration-result <?= e($result['status']) ?>"><span><?= $result['status']==='completed'?'✓':($result['status']==='skipped'?'—':'!') ?></span><strong><?= e($result['name']) ?></strong><small><?= $result['status']==='completed'?'تم التنفيذ':($result['status']==='skipped'?'منفذة مسبقاً':'تعذر التنفيذ؛ راجع سجل الخادم') ?></small></div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="setup-grid">
        <section class="section setup-preflight">
            <div class="section-head"><div><h2>فحص ما قبل التحديث</h2><p>حالة الاتصال والجداول المطلوبة قبل أي تنفيذ.</p></div><span class="<?= $databaseConnected?'badge success':'badge danger' ?>"><?= $databaseConnected?'جاهز':'غير متصل' ?></span></div>
            <?php if ($preflightError): ?><div class="alert error"><span class="alert-icon">!</span><span>تعذر إكمال فحص قاعدة البيانات. راجع إعداد الاتصال وسجل الخادم.</span></div><?php else: ?>
                <dl class="setup-facts">
                    <div><dt>اتصال قاعدة البيانات</dt><dd><?= $databaseConnected?'ناجح':'فشل' ?></dd></div>
                    <div><dt>قاعدة البيانات الحالية</dt><dd><?= e($databaseName) ?></dd></div>
                    <div><dt>الجداول الموجودة</dt><dd><?= count($existingTables) ?></dd></div>
                    <div><dt>المهاجرات المعلقة</dt><dd><?= count($migrationState['pending']) ?></dd></div>
                    <div><dt>المهاجرات المكتملة</dt><dd><?= count($migrationState['completed']) ?></dd></div>
                </dl>
            <?php endif; ?>
        </section>

        <section class="section setup-table-card">
            <div class="section-head"><div><h2>جداول وحدة المحتوى</h2><p>لن يتم تعديل أي جدول قائم.</p></div></div>
            <div class="required-table-list">
                <?php foreach ($requiredTables as $table): $exists=in_array($table,$existingTables,true); ?>
                    <div><code><?= e($table) ?></code><span class="<?= $exists?'ready':'pending' ?>"><?= $exists?'موجود':'سيُنشأ' ?></span></div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <section class="section setup-migrations-card">
        <div class="section-head"><div><h2>خطة التحديث</h2><p>يتم تشغيل العناصر المعلقة فقط، وبنفس الترتيب دائماً.</p></div></div>
        <div class="migration-plan">
            <?php foreach (content_migration_definitions() as $name=>$callback): $done=in_array($name,$migrationState['completed'],true); ?>
                <div><span><?= $done?'✓':(array_search($name,array_keys(content_migration_definitions()),true)+1) ?></span><div><strong><?= e($name) ?></strong><small><?= $done?'مكتملة ومسجلة':'معلقة وجاهزة للتنفيذ' ?></small></div></div>
            <?php endforeach; ?>
        </div>

        <div class="setup-warning"><strong>تنبيه أمان</strong><p>هذا التحديث ينشئ أو يوسّع جداول إدارة المحتوى فقط. لا يحذف أو يستبدل الجهات أو الأشخاص أو التفاعلات أو طلبات العمل الحالية.</p></div>

        <div class="setup-actions">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="apply_migrations"><button class="btn" type="submit" <?= (!$databaseConnected||!$migrationState['pending'])?'disabled':'' ?>>إنشاء أو تحديث جداول وحدة المحتوى</button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="lock_setup"><button class="btn secondary" type="submit">قفل صفحة التحديث</button></form>
        </div>
    </section>

    <details class="section existing-tables-disclosure">
        <summary>عرض أسماء الجداول الحالية (<?= count($existingTables) ?>)</summary>
        <div><?php foreach ($existingTables as $table): ?><code><?= e($table) ?></code><?php endforeach; ?></div>
    </details>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php';

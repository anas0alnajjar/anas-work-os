<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/system_migrations.php';
require_auth();

$ownerUserId = (int)(current_user()['id'] ?? 0);
$setupSecret = (string)($config['setup_secret'] ?? '');
$setupConfigured = strlen($setupSecret) >= 32;
$results = [];
$error = '';

if (is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'unlock_setup') {
        $provided = (string)($_POST['setup_secret'] ?? '');
        if (!$setupConfigured || !hash_equals($setupSecret,$provided)) {
            $error = 'رمز تحديث النظام غير صحيح.';
        } else {
            $_SESSION['system_setup_authorized'] = hash('sha256',$setupSecret);
            redirect('system_update.php');
        }
    }
    if ($action === 'lock_setup') {
        unset($_SESSION['system_setup_authorized']);
        redirect('system_update.php');
    }
    if ($action === 'apply_migrations') {
        $authorizedHash = (string)($_SESSION['system_setup_authorized'] ?? '');
        if (!$setupConfigured || !hash_equals(hash('sha256',$setupSecret),$authorizedHash)) {
            http_response_code(403); exit('Setup authorization required.');
        }
        try {
            $legacyReminderEmail = (string)($config['mail']['recipient'] ?? '');
            $results = system_apply_pending_migrations(db(),$ownerUserId,$legacyReminderEmail);
        } catch (Throwable $exception) {
            error_log('[system-update] '.$exception->getMessage());
            $error = 'تعذر تنفيذ تحديث النظام. لم تُعرض التفاصيل الحساسة؛ راجع سجل الخادم.';
        }
    }
}

$authorizedHash = (string)($_SESSION['system_setup_authorized'] ?? '');
$authorized = $setupConfigured && $authorizedHash !== '' && hash_equals(hash('sha256',$setupSecret),$authorizedHash);
$databaseName = '';
$migrationState = ['completed'=>[],'pending'=>array_keys(system_migration_definitions())];
$ownership = [];
if ($authorized) {
    try {
        $databaseName = (string)scalar('SELECT DATABASE()');
        $migrationState = system_migration_state(db());
        $ownership = system_ownership_summary(db(),$ownerUserId);
    } catch (Throwable $exception) {
        error_log('[system-update-preflight] '.$exception->getMessage());
        $error = 'تعذر قراءة حالة قاعدة البيانات.';
    }
}

$pageTitle = 'تحديث النظام متعدد المستخدمين';
$pageDescription = 'ترقية آمنة تربط البيانات القديمة بحسابك ثم تفرض عزل الملكية قبل فتح التسجيل';
$active = '';
require __DIR__.'/includes/layout_top.php';
?>

<section class="setup-hero system-update-hero">
    <div><span class="setup-kicker">SAFE MULTI-USER MIGRATION</span><h2>ملكية البيانات والحسابات</h2><p>تحديث غير هدّام: ينسب البيانات القديمة للحساب الحالي، ثم يجعل ملكية السجلات إلزامية قبل قبول حسابات جديدة.</p></div>
    <span class="setup-shield" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 4 6v5c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V6l-8-3Zm-3 9 2 2 4-5"/></svg></span>
</section>

<?php if ($error): ?><div class="alert error" role="alert"><span class="alert-icon">!</span><span><?= e($error) ?></span></div><?php endif; ?>

<?php if (!$authorized): ?>
<section class="section setup-auth-card">
    <div class="section-head"><div><h2>تفويض تحديث الإنتاج</h2><p>الجلسة الحالية وحدها لا تكفي؛ أدخل SETUP_SECRET الموجود في إعدادات الخادم.</p></div></div>
    <?php if (!$setupConfigured): ?><div class="alert error"><span class="alert-icon">!</span><span>SETUP_SECRET غير مضبوط أو أقصر من 32 محرفاً.</span></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="unlock_setup"><div class="field"><label for="setup_secret">رمز التحديث</label><input id="setup_secret" type="password" name="setup_secret" required autocomplete="off"></div><button class="btn" type="submit"<?= !$setupConfigured?' disabled':'' ?>>فتح فحص ما قبل التنفيذ</button></form>
</section>
<?php else: ?>

<?php if ($results): ?>
<section class="section setup-results">
    <div class="section-head"><div><h2>نتيجة التنفيذ</h2><p>كل migration مستقلة وقابلة لإعادة التشغيل بأمان.</p></div></div>
    <div class="migration-result-list"><?php foreach($results as $result): ?><div class="migration-result <?= e($result['status']) ?>"><span><?= $result['status']==='completed'?'✓':($result['status']==='skipped'?'—':'!') ?></span><strong><?= e($result['name']) ?></strong><small><?= $result['status']==='completed'?'تم التنفيذ':($result['status']==='skipped'?'منفذة مسبقاً':'توقف التنفيذ؛ راجع سجل الخادم') ?></small></div><?php endforeach; ?></div>
</section>
<?php endif; ?>

<div class="setup-grid system-preflight-grid">
    <section class="section">
        <div class="section-head"><div><h2>Preflight</h2><p>الحساب الذي ستُنسب إليه جميع البيانات القديمة.</p></div></div>
        <dl class="setup-facts"><div><dt>قاعدة البيانات</dt><dd><?= e($databaseName) ?></dd></div><div><dt>مالك البيانات القديمة</dt><dd><?= e(current_user()['username'] ?? '') ?> · ID <?= $ownerUserId ?></dd></div><div><dt>التحديثات المعلقة</dt><dd><?= count($migrationState['pending']) ?></dd></div><div><dt>التحديثات المكتملة</dt><dd><?= count($migrationState['completed']) ?></dd></div></dl>
    </section>
    <section class="section">
        <div class="section-head"><div><h2>بوابة الأمان</h2><p>منع التفعيل الجزئي غير الآمن.</p></div></div>
        <div class="system-stage-guard"><strong><?= $migrationState['pending']?'لا تفتح التسجيل بعد':'عزل الملكية مكتمل' ?></strong><p><?= $migrationState['pending']?'نفّذ جميع التحديثات المعلقة وتأكد أن «غير منسوب» يساوي صفراً قبل تفعيل التسجيل.':'يمكن تفعيل التسجيل من إعدادات البيئة بعد إكمال اختبار إنشاء حساب تجريبي والعزل.' ?></p></div>
    </section>
</div>

<section class="section setup-migrations-card">
    <div class="section-head"><div><h2>خطة التوسعة</h2><p>حسابات ← ملكية السجلات ← مفاتيح فريدة لكل مستخدم ← ملكية إلزامية.</p></div></div>
    <div class="migration-plan"><?php foreach(system_migration_definitions() as $name=>$callback): $done=in_array($name,$migrationState['completed'],true); ?><div><span><?= $done?'✓':(array_search($name,array_keys(system_migration_definitions()),true)+1) ?></span><div><strong><?= e($name) ?></strong><small><?= $done?'مكتملة ومسجلة':'معلقة وجاهزة للتنفيذ' ?></small></div></div><?php endforeach; ?></div>
    <div class="setup-warning"><strong>تنبيه أمان</strong><p>سيتم ربط الجهات وفرص العمل وخطة المحتوى وسجلات التذكير الحالية بالحساب المعروض أعلاه. لا يوجد DROP TABLE أو TRUNCATE أو حذف سجلات.</p></div>
    <div class="setup-actions"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="apply_migrations"><button class="btn" type="submit"<?= !$migrationState['pending']?' disabled':'' ?>>تنفيذ التحديثات المعلقة بأمان</button></form><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="lock_setup"><button class="btn secondary" type="submit">قفل صفحة التحديث</button></form></div>
</section>

<?php if ($ownership): ?>
<section class="section ownership-audit">
    <div class="section-head"><div><h2>تدقيق ملكية السجلات</h2><p>يجب أن تبقى السجلات غير المنسوبة صفراً قبل فتح التسجيل وبعد كل تحديث.</p></div></div>
    <div class="table-wrap responsive-table-wrap"><table class="responsive-table"><thead><tr><th>المجال</th><th>الإجمالي</th><th>منسوب لحسابك</th><th>غير منسوب</th></tr></thead><tbody><?php foreach($ownership as $table=>$row): ?><tr><td data-label="المجال"><strong><?= e($table) ?></strong></td><td data-label="الإجمالي"><?= (int)$row['total'] ?></td><td data-label="منسوب"><?= (int)$row['owned'] ?></td><td data-label="غير منسوب"><span class="<?= $row['unowned']?'badge danger':'badge success' ?>"><?= (int)$row['unowned'] ?></span></td></tr><?php endforeach; ?></tbody></table></div>
</section>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__.'/includes/layout_bottom.php';

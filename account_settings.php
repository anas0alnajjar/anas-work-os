<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/auth.php';
require_auth();

$userId = current_user_id();
$errors = [];
$timezones = [
    'Asia/Damascus'=>'دمشق',
    'Asia/Dubai'=>'دبي',
    'Europe/Istanbul'=>'إسطنبول',
    'Europe/Berlin'=>'برلين',
    'UTC'=>'UTC',
];
$account = q('SELECT * FROM users WHERE id=?',[$userId])->fetch();
if (!$account) redirect('logout.php');

if (is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'profile');

    if ($action === 'profile') {
        $displayName = trim((string)($_POST['display_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $reminderEmail = strtolower(trim((string)($_POST['reminder_email'] ?? '')));
        $timezone = trim((string)($_POST['timezone'] ?? ''));
        $reminderHour = filter_var($_POST['reminder_hour'] ?? null,FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>23]]);
        $enabled = isset($_POST['reminders_enabled']) ? 1 : 0;

        add_required_error($errors,'display_name',$displayName,'الاسم');
        add_max_error($errors,'display_name',$displayName,'الاسم',120);
        add_required_error($errors,'email',$email,'بريد الحساب');
        add_email_error($errors,'email',$email);
        add_email_error($errors,'reminder_email',$reminderEmail);
        if ($enabled && $reminderEmail === '') $errors['reminder_email'] = 'حدد البريد الذي سيستقبل التذكيرات.';
        if (!array_key_exists($timezone,$timezones)) $errors['timezone'] = 'اختر منطقة زمنية صحيحة.';
        if ($reminderHour === false) $errors['reminder_hour'] = 'اختر ساعة إرسال صحيحة.';
        if (!$errors && (int)scalar('SELECT COUNT(*) FROM users WHERE LOWER(email)=LOWER(?) AND id<>?',[$email,$userId])) {
            $errors['email'] = 'البريد مرتبط بحساب آخر.';
        }

        if (!$errors) {
            $emailChanged = strtolower((string)$account['email']) !== $email;
            $verifiedAt = $emailChanged ? null : $account['email_verified_at'];
            $pdo = db();
            $pdo->beginTransaction();
            try {
                q(
                    'UPDATE users SET display_name=?,email=?,reminder_email=?,timezone=?,reminder_hour=?,reminders_enabled=?,email_verified_at=? WHERE id=?',
                    [$displayName,$email,$reminderEmail ?: null,$timezone,$reminderHour,$enabled,$verifiedAt,$userId]
                );
                if ($emailChanged) q('UPDATE user_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL',[$userId]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
            $_SESSION['user']['display_name'] = $displayName;
            $_SESSION['user']['email'] = $email;
            flash('success',$emailChanged?'تم الحفظ. أعد تأكيد بريد الحساب الجديد.':'تم حفظ إعدادات الحساب وجدول التذكيرات.');
            redirect('account_settings.php');
        }
        $account = array_merge($account,[
            'display_name'=>$displayName,'email'=>$email,'reminder_email'=>$reminderEmail,
            'timezone'=>$timezone,'reminder_hour'=>$reminderHour === false ? 9 : $reminderHour,
            'reminders_enabled'=>$enabled,
        ]);
    }

    if ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['new_password_confirmation'] ?? '');
        if (!password_verify($current,$account['password_hash'])) $errors['current_password'] = 'كلمة المرور الحالية غير صحيحة.';
        $passwordErrors = auth_password_errors($new);
        if ($passwordErrors) $errors['new_password'] = implode(' ',$passwordErrors);
        if ($new !== $confirm) $errors['new_password_confirmation'] = 'التأكيد غير مطابق.';
        if (!$errors) {
            q('UPDATE users SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$userId]);
            q('UPDATE user_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL',[$userId]);
            flash('success','تم تغيير كلمة المرور وإبطال روابط الاستعادة السابقة.');
            redirect('account_settings.php#security');
        }
    }

    if ($action === 'resend_verification') {
        try {
            auth_send_action_email($account,'email_verification');
            flash('success','تم إرسال رابط تأكيد جديد.');
        } catch (Throwable $exception) {
            error_log('[resend-verification] '.$exception->getMessage());
            flash('error','تعذر إرسال التأكيد الآن. تحقق من إعداد SMTP.');
        }
        redirect('account_settings.php');
    }
}

$pageTitle = 'إعدادات الحساب';
$pageDescription = 'بيانات الدخول، بريد التذكيرات، والمنطقة الزمنية';
$active = '';
require __DIR__.'/includes/layout_top.php';
?>
<?= error_summary($errors) ?>
<div class="settings-layout">
    <aside class="settings-summary">
        <span class="settings-avatar"><?= e(strtoupper(substr($account['display_name'] ?: $account['username'],0,1))) ?></span>
        <h2><?= e($account['display_name'] ?: $account['username']) ?></h2>
        <p>@<?= e($account['username']) ?></p>
        <span class="<?= $account['email_verified_at']?'badge success':'badge warning' ?>"><?= $account['email_verified_at']?'البريد مؤكد':'البريد غير مؤكد' ?></span>
        <nav><a href="#profile">الملف والتنبيهات</a><a href="#security">الأمان</a></nav>
    </aside>
    <main class="settings-main">
        <section class="section" id="profile">
            <div class="section-head"><div><h2>الملف والتنبيهات</h2><p>بريد الدخول منفصل عن البريد الذي يستقبل التذكيرات.</p></div></div>
            <form method="post">
                <input type="hidden" name="action" value="profile"><?= csrf_field() ?>
                <div class="form-grid">
                    <div class="field full<?= field_class($errors,'display_name') ?>"><label for="display_name">الاسم</label><input id="display_name" name="display_name" maxlength="120" required value="<?= e($account['display_name']) ?>"><?= field_error($errors,'display_name') ?></div>
                    <div class="field<?= field_class($errors,'email') ?>"><label for="email">بريد الحساب</label><input id="email" type="email" name="email" required dir="ltr" value="<?= e($account['email']) ?>"><?= field_error($errors,'email') ?></div>
                    <div class="field<?= field_class($errors,'reminder_email') ?>"><label for="reminder_email">بريد التذكيرات</label><input id="reminder_email" type="email" name="reminder_email" dir="ltr" value="<?= e($account['reminder_email']) ?>"><?= field_error($errors,'reminder_email') ?></div>
                    <div class="field<?= field_class($errors,'timezone') ?>"><label for="timezone">المنطقة الزمنية</label><select id="timezone" name="timezone"><?php foreach($timezones as $value=>$label):?><option value="<?= e($value) ?>"<?= $account['timezone']===$value?' selected':'' ?>><?= e($label) ?></option><?php endforeach;?></select><?= field_error($errors,'timezone') ?></div>
                    <div class="field<?= field_class($errors,'reminder_hour') ?>"><label for="reminder_hour">ساعة إرسال الملخص</label><select id="reminder_hour" name="reminder_hour"><?php for($hour=0;$hour<24;$hour++):?><option value="<?= $hour ?>"<?= (int)$account['reminder_hour']===$hour?' selected':'' ?>><?= sprintf('%02d:00',$hour) ?></option><?php endfor;?></select><?= field_error($errors,'reminder_hour') ?><small class="field-hint">بحسب المنطقة الزمنية المختارة.</small></div>
                    <label class="settings-toggle"><input type="checkbox" name="reminders_enabled" value="1"<?= (int)$account['reminders_enabled']?' checked':'' ?>><span><strong>تفعيل التذكيرات</strong><small>ملخص العلاقات والوظائف والمقابلات وخطة النشر.</small></span></label>
                </div>
                <div class="form-actions"><button class="btn" type="submit">حفظ الإعدادات</button><?php if(!$account['email_verified_at']&&$account['email']&&!empty($config['mail']['enabled'])):?><button class="btn secondary" type="submit" name="action" value="resend_verification" formnovalidate>إعادة إرسال التأكيد</button><?php endif;?></div>
            </form>
        </section>
        <section class="section" id="security">
            <div class="section-head"><div><h2>تغيير كلمة المرور</h2><p>يتطلب معرفة كلمة المرور الحالية.</p></div></div>
            <form method="post">
                <input type="hidden" name="action" value="password"><?= csrf_field() ?>
                <div class="form-grid">
                    <div class="field full<?= field_class($errors,'current_password') ?>"><label for="current_password">كلمة المرور الحالية</label><input id="current_password" type="password" name="current_password" required autocomplete="current-password"><?= field_error($errors,'current_password') ?></div>
                    <div class="field<?= field_class($errors,'new_password') ?>"><label for="new_password">كلمة المرور الجديدة</label><input id="new_password" type="password" name="new_password" minlength="10" required autocomplete="new-password"><?= field_error($errors,'new_password') ?></div>
                    <div class="field<?= field_class($errors,'new_password_confirmation') ?>"><label for="new_password_confirmation">تأكيد الجديدة</label><input id="new_password_confirmation" type="password" name="new_password_confirmation" minlength="10" required autocomplete="new-password"><?= field_error($errors,'new_password_confirmation') ?></div>
                </div>
                <div class="form-actions"><button class="btn" type="submit">تغيير كلمة المرور</button></div>
            </form>
        </section>
    </main>
</div>
<?php require __DIR__.'/includes/layout_bottom.php';

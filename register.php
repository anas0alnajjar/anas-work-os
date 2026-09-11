<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/auth.php';
require_guest();

if (empty($config['registration_enabled'])) { http_response_code(403); exit('إنشاء الحسابات مغلق حالياً.'); }
if (!auth_multi_user_ready()) { http_response_code(503); exit('تحديث عزل المستخدمين لم يكتمل بعد. شغّل system_update.php بالحساب الأساسي.'); }

$data=['display_name'=>'','username'=>'','email'=>'','timezone'=>$config['timezone'] ?? 'Asia/Damascus'];
$errors=[];
if (is_post()) {
    verify_csrf();
    foreach($data as $key=>$default) $data[$key]=trim((string)($_POST[$key] ?? $default));
    $password=(string)($_POST['password'] ?? '');
    $confirmation=(string)($_POST['password_confirmation'] ?? '');
    add_required_error($errors,'display_name',$data['display_name'],'الاسم');
    add_required_error($errors,'username',$data['username'],'اسم المستخدم');
    add_required_error($errors,'email',$data['email'],'البريد الإلكتروني');
    add_max_error($errors,'display_name',$data['display_name'],'الاسم',120);
    if (!preg_match('/^[\p{L}\p{N}_.-]{3,40}$/u',$data['username'])) $errors['username']='استخدم 3–40 حرفاً أو رقماً، ويمكن استعمال النقطة والشرطة فقط.';
    add_email_error($errors,'email',$data['email']);
    if (!in_array($data['timezone'],['Asia/Damascus','Asia/Dubai','Europe/Istanbul','Europe/Berlin','UTC'],true)) $errors['timezone']='اختر منطقة زمنية صحيحة.';
    foreach(auth_password_errors($password) as $message) $errors['password']=$message;
    if ($password !== $confirmation) $errors['password_confirmation']='تأكيد كلمة المرور غير مطابق.';
    if (!$errors && (int)scalar('SELECT COUNT(*) FROM users WHERE LOWER(username)=LOWER(?)',[$data['username']])) $errors['username']='اسم المستخدم مستخدم مسبقاً.';
    if (!$errors && (int)scalar('SELECT COUNT(*) FROM users WHERE LOWER(email)=LOWER(?)',[$data['email']])) $errors['email']='يوجد حساب بهذا البريد. استخدم استعادة كلمة المرور.';

    if (!$errors) {
        q("INSERT INTO users(username,email,display_name,reminder_email,timezone,reminders_enabled,account_status,password_hash) VALUES(?,?,?,?,?,1,'active',?)",[
            $data['username'],strtolower($data['email']),$data['display_name'],strtolower($data['email']),$data['timezone'],password_hash($password,PASSWORD_DEFAULT)
        ]);
        $userId=(int)db()->lastInsertId();
        $user=['id'=>$userId,'username'=>$data['username'],'display_name'=>$data['display_name'],'email'=>strtolower($data['email'])];
        $mailSent=false;
        if (!empty($config['mail']['enabled']) && !empty($config['email_verification_enabled'])) {
            try { auth_send_action_email($user,'email_verification'); $mailSent=true; }
            catch(Throwable $exception) { error_log('[registration-verification] '.$exception->getMessage()); }
        }
        flash('success',$mailSent?'تم إنشاء الحساب. أرسلنا رابط تأكيد إلى بريدك ويمكنك الدخول الآن.':'تم إنشاء الحساب ويمكنك الدخول الآن. يمكنك إعادة إرسال التأكيد من الإعدادات.');
        redirect('login.php');
    }
}
?>
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#002347"><title>إنشاء حساب | Anas Work OS</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="auth-page"><main class="auth-shell"><a class="auth-brand" href="login.php"><span class="brand-mark">AN</span><div><strong>Anas Work OS</strong><small>مساحة عمل مهنية خاصة بك</small></div></a><section class="auth-card"><span class="auth-kicker">CREATE YOUR WORKSPACE</span><h1>أنشئ حسابك</h1><p>بياناتك وعلاقاتك وفرصك وخطة محتواك معزولة بالكامل عن بقية المستخدمين.</p><?= error_summary($errors) ?><form method="post"><?= csrf_field() ?><div class="form-grid"><div class="field full<?= field_class($errors,'display_name') ?>"><label for="display_name">الاسم <em>*</em></label><input id="display_name" name="display_name" required maxlength="120" autocomplete="name" value="<?= e($data['display_name']) ?>"><?= field_error($errors,'display_name') ?></div><div class="field<?= field_class($errors,'username') ?>"><label for="username">اسم المستخدم <em>*</em></label><input id="username" name="username" required maxlength="40" autocomplete="username" dir="ltr" value="<?= e($data['username']) ?>"><?= field_error($errors,'username') ?></div><div class="field<?= field_class($errors,'email') ?>"><label for="email">البريد الإلكتروني <em>*</em></label><input id="email" type="email" name="email" required maxlength="190" autocomplete="email" dir="ltr" value="<?= e($data['email']) ?>"><?= field_error($errors,'email') ?></div><div class="field full<?= field_class($errors,'timezone') ?>"><label for="timezone">المنطقة الزمنية</label><select id="timezone" name="timezone"><?php foreach(['Asia/Damascus'=>'دمشق','Asia/Dubai'=>'دبي','Europe/Istanbul'=>'إسطنبول','Europe/Berlin'=>'برلين','UTC'=>'UTC'] as $value=>$label): ?><option value="<?= e($value) ?>"<?= $data['timezone']===$value?' selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= field_error($errors,'timezone') ?></div><div class="field<?= field_class($errors,'password') ?>"><label for="password">كلمة المرور <em>*</em></label><input id="password" type="password" name="password" required minlength="10" autocomplete="new-password"><?= field_error($errors,'password') ?></div><div class="field<?= field_class($errors,'password_confirmation') ?>"><label for="password_confirmation">تأكيد كلمة المرور <em>*</em></label><input id="password_confirmation" type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"><?= field_error($errors,'password_confirmation') ?></div></div><button class="btn block" type="submit">إنشاء مساحة العمل</button></form><div class="auth-links"><span>لديك حساب؟ <a href="login.php">تسجيل الدخول</a></span></div></section></main></body></html>

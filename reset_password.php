<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/auth.php';
require_guest();
$token=trim((string)($_GET['token']??$_POST['token']??''));
$tokenRow=auth_find_token($token,'password_reset');
$errors=[];
if(is_post() && $tokenRow){
    verify_csrf();$password=(string)($_POST['password']??'');$confirmation=(string)($_POST['password_confirmation']??'');
    foreach(auth_password_errors($password) as $message)$errors['password']=$message;
    if($password!==$confirmation)$errors['password_confirmation']='تأكيد كلمة المرور غير مطابق.';
    if(!$errors){
        $pdo=db();$pdo->beginTransaction();
        try{
            $locked=auth_find_token($token,'password_reset',true);if(!$locked)throw new RuntimeException('Token is no longer valid.');
            q('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),(int)$locked['user_id']]);
            auth_mark_token_used((int)$locked['id']);
            q("UPDATE user_tokens SET used_at=NOW() WHERE user_id=? AND token_type='password_reset' AND used_at IS NULL",[(int)$locked['user_id']]);
            $pdo->commit();flash('success','تم تغيير كلمة المرور. سجّل الدخول بالكلمة الجديدة.');redirect('login.php');
        }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();error_log('[password-reset] '.$exception->getMessage());$errors['token']='انتهت صلاحية الرابط أو تم استخدامه.';}
    }
}
?>
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>كلمة مرور جديدة | Anas Work OS</title><link rel="stylesheet" href="assets/style.css"></head><body class="auth-page"><main class="auth-shell narrow"><a class="auth-brand" href="login.php"><span class="brand-mark">AN</span><div><strong>Anas Work OS</strong><small>حماية الحساب</small></div></a><section class="auth-card"><span class="auth-kicker">SECURE RESET</span><h1>كلمة مرور جديدة</h1><?php if(!$tokenRow||isset($errors['token'])):?><div class="alert error"><span>الرابط منتهي الصلاحية أو مستخدم مسبقاً.</span></div><a class="btn block" href="forgot_password.php">طلب رابط جديد</a><?php else:?><p>استخدم 10 محارف على الأقل مع حرف ورقم.</p><?= error_summary($errors) ?><form method="post"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>"><div class="field<?= field_class($errors,'password') ?>"><label for="password">كلمة المرور الجديدة</label><input id="password" type="password" name="password" minlength="10" required autocomplete="new-password"><?= field_error($errors,'password') ?></div><div class="field<?= field_class($errors,'password_confirmation') ?>"><label for="password_confirmation">تأكيد كلمة المرور</label><input id="password_confirmation" type="password" name="password_confirmation" minlength="10" required autocomplete="new-password"><?= field_error($errors,'password_confirmation') ?></div><button class="btn block" type="submit">حفظ كلمة المرور</button></form><?php endif;?></section></main></body></html>

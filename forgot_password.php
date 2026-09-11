<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/auth.php';
require_guest();
$sent=false;$error='';
if(is_post()){
    verify_csrf();
    $email=trim((string)($_POST['email']??''));
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) $error='أدخل بريداً إلكترونياً صحيحاً.';
    if(!$error){
        $recent=(int)scalar("SELECT COUNT(*) FROM user_tokens t JOIN users u ON u.id=t.user_id WHERE LOWER(u.email)=LOWER(?) AND t.token_type='password_reset' AND t.created_at>=DATE_SUB(NOW(),INTERVAL 10 MINUTE)",[$email]);
        $user=q("SELECT id,username,email,display_name FROM users WHERE LOWER(email)=LOWER(?) AND account_status='active' LIMIT 1",[$email])->fetch();
        if($user && !$recent){try{auth_send_action_email($user,'password_reset');}catch(Throwable $exception){error_log('[password-reset-mail] '.$exception->getMessage());}}
        usleep(350000);$sent=true;
    }
}
?>
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>استعادة كلمة المرور | Anas Work OS</title><link rel="stylesheet" href="assets/style.css"></head><body class="auth-page"><main class="auth-shell narrow"><a class="auth-brand" href="login.php"><span class="brand-mark">AN</span><div><strong>Anas Work OS</strong><small>استعادة الوصول</small></div></a><section class="auth-card"><span class="auth-kicker">PASSWORD RECOVERY</span><h1>نسيت كلمة المرور؟</h1><?php if($sent):?><div class="auth-success"><strong>تحقق من بريدك</strong><p>إذا كان البريد مرتبطاً بحساب، أرسلنا رابطاً صالحاً لمرة واحدة. لم نكشف ما إذا كان الحساب موجوداً.</p></div><a class="btn secondary block" href="login.php">العودة لتسجيل الدخول</a><?php else:?><p>أدخل بريد الحساب وسنرسل رابطاً آمناً لتعيين كلمة مرور جديدة.</p><?php if($error):?><div class="alert error"><span><?= e($error) ?></span></div><?php endif;?><form method="post"><?= csrf_field() ?><div class="field"><label for="email">البريد الإلكتروني</label><input id="email" type="email" name="email" required autocomplete="email" dir="ltr"></div><button class="btn block" type="submit">إرسال رابط الاستعادة</button></form><div class="auth-links"><a href="login.php">العودة لتسجيل الدخول</a></div><?php endif;?></section></main></body></html>

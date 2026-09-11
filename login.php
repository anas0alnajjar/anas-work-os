<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/auth.php';
require_guest();

$error = '';
if (is_post()) {
    verify_csrf();
    $identifier = trim((string)($_POST['identifier'] ?? ''));
    $password = $_POST['password'] ?? '';
    try {
        $attempts = (array)($_SESSION['login_attempts'] ?? ['count'=>0,'since'=>time()]);
        if (time()-(int)$attempts['since'] > 300) $attempts=['count'=>0,'since'=>time()];
        if ((int)$attempts['count'] >= 7) {
            usleep(800000);
            $error = 'محاولات كثيرة. انتظر خمس دقائق ثم حاول مجدداً.';
        } else {
            $u = q("SELECT * FROM users WHERE (LOWER(username)=LOWER(?) OR LOWER(email)=LOWER(?)) AND account_status='active' LIMIT 1", [$identifier,$identifier])->fetch();
            if ($u && password_verify($password, $u['password_hash'])) {
                session_regenerate_id(true);
                unset($_SESSION['login_attempts']);
                $_SESSION['user'] = ['id'=>(int)$u['id'],'username'=>$u['username'],'display_name'=>$u['display_name'] ?: $u['username'],'email'=>$u['email']];
                q('UPDATE users SET last_login_at=NOW() WHERE id=?',[(int)$u['id']]);
                redirect('dashboard.php');
            }
            $_SESSION['login_attempts']=['count'=>(int)$attempts['count']+1,'since'=>(int)$attempts['since']];
            usleep(400000);
            $error = 'بيانات الدخول غير صحيحة.';
        }
    } catch (Throwable $e) {
        $error = 'النظام غير مثبت أو إعداد قاعدة البيانات غير صحيح. افتح install.php أولاً.';
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#002347">
    <title>تسجيل الدخول | Anas Work OS</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<main class="login-shell">
    <section class="login-brand-panel" aria-label="Anas Work OS">
        <div class="login-brand">
            <span class="brand-mark" aria-hidden="true">AN</span>
            <div><h1>Anas Work OS</h1><p>نظام العلاقات والفرص المهنية</p></div>
        </div>
        <div class="login-message">
            <h2>كل علاقة مهنية.<br>كل فرصة.<br>خطوة تالية واضحة.</h2>
            <p>مساحة عمل شخصية لتنظيم الجهات، التواصل، والمتابعة الدقيقة لطلبات العمل دون تشتيت.</p>
        </div>
        <div class="login-footnote">PRIVATE WORKSPACE · ANAS AL-NAJJAR</div>
    </section>

    <section class="login-card">
        <div class="mobile-brand">
            <span class="brand-mark" aria-hidden="true">AN</span>
            <strong>Anas Work OS</strong>
        </div>
        <h2>مرحباً بعودتك</h2>
        <p>أدخل بياناتك للوصول إلى مساحة العمل.</p>
        <?php if ($error): ?><div class="alert error"><span><?= e($error) ?></span></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="field">
                <label for="identifier">اسم المستخدم أو البريد</label>
                <input id="identifier" name="identifier" required autofocus autocomplete="username" placeholder="username أو name@example.com">
            </div>
            <div class="field">
                <label for="password">كلمة المرور</label>
                <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="أدخل كلمة المرور">
            </div>
            <button class="btn" type="submit">الدخول إلى مساحة العمل</button>
        </form>
        <div class="auth-links"><a href="forgot_password.php">نسيت كلمة المرور؟</a><?php if(!empty($config['registration_enabled'])): ?><span>ليس لديك حساب؟ <a href="register.php">إنشاء حساب</a></span><?php endif; ?></div>
        <div class="login-security">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
            <span>جلسة خاصة ومحمية</span>
        </div>
    </section>
</main>
</body>
</html>

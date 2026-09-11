<?php
// انسخ هذا الملف إلى config.local.php على الخادم ثم استبدل القيم فقط.
// لا ترفع config.local.php الحقيقي إلى مستودع أو ترسله ضمن ملف ZIP.
return [
    'app_url' => 'https://YOUR-DOMAIN.example',
    'setup_secret' => 'CHANGE_TO_A_RANDOM_64_CHARACTER_SECRET',
    'install_enabled' => false,
    'registration_enabled' => false, // فعّله بعد اكتمال migrations واختبار العزل.
    'email_verification_enabled' => true,
    'password_reset_minutes' => 60,
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'u000000000_anas_work_os',
        'user' => 'u000000000_work_os',
        'pass' => 'CHANGE_TO_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'enabled' => true,
        'host' => 'smtp.hostinger.com',
        'port' => 465,
        'encryption' => 'ssl',
        // username وfrom_email يجب أن يكونا صندوقاً واحداً موثقاً وقادراً على الإرسال.
        'username' => 'CHANGE_TO_VERIFIED_SENDER@masar-up.com',
        'password' => 'CHANGE_TO_SMTP_PASSWORD',
        'from_email' => 'CHANGE_TO_VERIFIED_SENDER@masar-up.com',
        'from_name' => 'Anas Work OS',
        // تستخدمه migration فقط لربط الحساب القديم؛ كل مستخدم يحدد مستلمه من الإعدادات.
        'recipient' => 'anas0alnajjar@gmail.com',
        'timeout' => 20,
    ],
];

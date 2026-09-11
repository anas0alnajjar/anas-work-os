<?php
// الإعدادات العامة فقط. الأسرار الخاصة بالبيئة توضع في config.local.php.
$config = [
    'app_name' => 'Anas Work OS',
    'timezone' => 'Asia/Damascus',
    'session_name' => 'anas_work_os_session',
    'app_url' => getenv('APP_URL') ?: '',
    'setup_secret' => getenv('SETUP_SECRET') ?: '',
    'install_enabled' => filter_var(getenv('INSTALL_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'registration_enabled' => filter_var(getenv('REGISTRATION_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'email_verification_enabled' => filter_var(getenv('EMAIL_VERIFICATION_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'password_reset_minutes' => 60,
    'db' => [
        'host' => 'localhost',
        'port' => 3666,
        'name' => 'anas_work_os',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'enabled' => false,
        'host' => '',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => '',
        'password' => '',
        'from_email' => '',
        'from_name' => 'Anas Work OS',
        'recipient' => '',
        'timeout' => 20,
    ],
];

$localFile = __DIR__ . '/config.local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) $config = array_replace_recursive($config, $local);
}

return $config;

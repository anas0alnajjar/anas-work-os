<?php
declare(strict_types=1);

require_once __DIR__.'/mailer.php';

function auth_password_errors(string $password): array {
    $errors = [];
    if (strlen($password) < 10) $errors[] = 'كلمة المرور يجب ألا تقل عن 10 محارف.';
    if (!preg_match('/[A-Za-z]/',$password) || !preg_match('/\d/',$password)) $errors[] = 'استخدم حرفاً واحداً ورقماً واحداً على الأقل.';
    return $errors;
}

function auth_multi_user_ready(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    $userColumn = db()->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='reminder_hour'")->fetchColumn();
    if (!(int)$userColumn) return $ready = false;
    foreach (['organizations','job_applications','content_topics','reminder_dispatches','content_reminder_logs'] as $table) {
        $statement = db()->prepare('SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=\'user_id\'');
        $statement->execute([$table]);
        if ($statement->fetchColumn() !== 'NO') return $ready = false;
    }
    return $ready = true;
}

function auth_create_token(int $userId, string $type, int $minutes): string {
    if (!in_array($type,['password_reset','email_verification'],true)) throw new InvalidArgumentException('Invalid token type.');
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256',$raw);
    q('UPDATE user_tokens SET used_at=NOW() WHERE user_id=? AND token_type=? AND used_at IS NULL',[$userId,$type]);
    q('INSERT INTO user_tokens(user_id,token_type,token_hash,expires_at,requested_ip) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL ? MINUTE),?)',[$userId,$type,$hash,max(10,$minutes),client_ip() ?: null]);
    return $raw;
}

function auth_find_token(string $raw, string $type, bool $lock=false): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/',$raw)) return null;
    $sql = 'SELECT t.*,u.username,u.email,u.display_name,u.account_status FROM user_tokens t JOIN users u ON u.id=t.user_id WHERE t.token_hash=? AND t.token_type=? AND t.used_at IS NULL AND t.expires_at>=NOW()'.($lock?' FOR UPDATE':'');
    $row = q($sql,[hash('sha256',$raw),$type])->fetch();
    return $row ?: null;
}

function auth_mark_token_used(int $tokenId): void { q('UPDATE user_tokens SET used_at=NOW() WHERE id=? AND used_at IS NULL',[$tokenId]); }

function auth_send_action_email(array $user, string $type): void {
    global $config;
    $to = trim((string)($user['email'] ?? ''));
    if (!filter_var($to,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Account email is unavailable.');
    $isReset = $type === 'password_reset';
    $minutes = $isReset ? (int)($config['password_reset_minutes'] ?? 60) : 1440;
    $token = auth_create_token((int)$user['id'],$type,$minutes);
    $path = ($isReset?'reset_password.php':'verify_email.php').'?token='.rawurlencode($token);
    $url = absolute_app_url($path);
    $name = htmlspecialchars((string)($user['display_name'] ?: $user['username']),ENT_QUOTES,'UTF-8');
    $title = $isReset ? 'استعادة كلمة المرور' : 'تأكيد البريد الإلكتروني';
    $action = $isReset ? 'تعيين كلمة مرور جديدة' : 'تأكيد البريد';
    $note = $isReset ? 'ينتهي الرابط خلال '.$minutes.' دقيقة، ويعمل مرة واحدة فقط.' : 'ينتهي الرابط خلال 24 ساعة، ويعمل مرة واحدة فقط.';
    $safeUrl = htmlspecialchars($url,ENT_QUOTES,'UTF-8');
    $html = '<!doctype html><html lang="ar" dir="rtl"><body style="margin:0;background:#f4f7f9;font-family:Arial,Tahoma,sans-serif"><div style="max-width:600px;margin:0 auto;padding:28px 16px"><div style="padding:26px;border-radius:16px 16px 0 0;background:#002347;color:white"><small style="color:#68c9f5">ANAS WORK OS</small><h1 style="margin:8px 0 0;font-size:24px">'.$title.'</h1></div><div style="padding:26px;border-radius:0 0 16px 16px;background:white;color:#334e61"><p>مرحباً '.$name.'،</p><p>'.$note.'</p><p style="margin:26px 0"><a href="'.$safeUrl.'" style="padding:12px 18px;border-radius:8px;background:#0088cc;color:white;text-decoration:none;font-weight:bold">'.$action.'</a></p><p style="color:#71818d;font-size:12px">إذا لم تطلب هذه العملية، تجاهل الرسالة.</p></div></div></body></html>';
    $plain = $title."\n\n".$url."\n\n".$note;
    try {
        smtp_send($config['mail'] ?? [],$to,$title.' | '.($config['app_name'] ?? 'Anas Work OS'),$html,$plain);
    } catch (Throwable $exception) {
        q('UPDATE user_tokens SET used_at=NOW() WHERE token_hash=? AND used_at IS NULL',[hash('sha256',$token)]);
        throw $exception;
    }
}

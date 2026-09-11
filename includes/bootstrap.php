<?php
declare(strict_types=1);

$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('ملف config.php غير موجود. انسخ config.example.php إلى config.php وعدّل بيانات قاعدة البيانات.');
}
$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'Asia/Damascus');

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
    );
    ini_set('session.use_strict_mode','1');
    ini_set('session.use_only_cookies','1');
    session_name($config['session_name'] ?? 'anas_work_os_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if ($isHttps) header('Strict-Transport-Security: max-age=31536000');
}

function db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo instanceof PDO) return $pdo;
    $db = $config['db'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset']);
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_auth(): void { if (!current_user()) redirect('login.php'); }
function require_guest(): void { if (current_user()) redirect('dashboard.php'); }
function current_user_id(): int {
    $id = (int)(current_user()['id'] ?? 0);
    if ($id < 1) { if (PHP_SAPI !== 'cli') redirect('login.php'); throw new RuntimeException('Authenticated user required.'); }
    return $id;
}
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,45); }
function absolute_app_url(string $path=''): string {
    global $config;
    $base = rtrim((string)($config['app_url'] ?? ''),'/');
    $directory = PHP_SAPI === 'cli' ? '/' : str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
    if ($base === '' && PHP_SAPI !== 'cli') {
        $scheme = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        ) ? 'https' : 'http';
        $host = preg_replace('/[^a-z0-9.:-]/i','',(string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
        $base = $scheme.'://'.$host.($directory==='/'?'':rtrim($directory,'/'));
    } elseif ($base !== '' && $directory !== '/') {
        $basePath = rtrim((string)(parse_url($base,PHP_URL_PATH) ?? ''),'/');
        if ($basePath !== rtrim($directory,'/')) $base .= rtrim($directory,'/');
    }
    return $base.'/'.ltrim($path,'/');
}
function flash(string $type, string $message): void { $_SESSION['flash'][] = compact('type','message'); }
function pull_flashes(): array { $f=$_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419); exit('انتهت الجلسة أو فشل التحقق الأمني. أعد تحميل الصفحة.');
    }
}
function q(string $sql, array $params=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($params); return $s; }
function scalar(string $sql, array $params=[]): mixed { return q($sql,$params)->fetchColumn(); }

function valid_date(string $value): bool {
    if ($value === '') return true;
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}
function add_required_error(array &$errors, string $field, string $value, string $label): void {
    if ($value === '') $errors[$field] = "حقل {$label} مطلوب.";
}
function text_length(string $value): int {
    if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
    $count = preg_match_all('/./us', $value, $matches);
    return $count === false ? strlen($value) : $count;
}
function add_max_error(array &$errors, string $field, string $value, string $label, int $max): void {
    if ($value !== '' && text_length($value) > $max) $errors[$field] = "حقل {$label} يجب ألا يتجاوز {$max} حرفاً.";
}
function add_url_error(array &$errors, string $field, string $value, string $label): void {
    if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) $errors[$field] = "أدخل رابطاً صحيحاً في حقل {$label}.";
}
function add_email_error(array &$errors, string $field, string $value): void {
    if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) $errors[$field] = 'أدخل بريداً إلكترونياً صحيحاً.';
}
function add_date_error(array &$errors, string $field, string $value, string $label): void {
    if (!valid_date($value)) $errors[$field] = "أدخل تاريخاً صحيحاً في حقل {$label}.";
}
function add_choice_error(array &$errors, string $field, string $value, array $allowed, string $label): void {
    if (!in_array($value, $allowed, true)) $errors[$field] = "اختر قيمة صحيحة في حقل {$label}.";
}
function field_class(array $errors, string $field): string { return isset($errors[$field]) ? ' invalid' : ''; }
function field_error(array $errors, string $field): string {
    return isset($errors[$field]) ? '<small class="field-error">'.e($errors[$field]).'</small>' : '';
}
function error_summary(array $errors): string {
    if (!$errors) return '';
    return '<div class="alert error validation-summary" role="alert"><span class="alert-icon" aria-hidden="true">!</span><div><strong>تعذر حفظ البيانات</strong><span>راجع الحقول المحددة أدناه ثم أعد المحاولة.</span></div></div>';
}
function pagination_meta(int $total, int $perPage=15): array {
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
    $offset = ($page - 1) * $perPage;
    return [
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
        'offset' => $offset,
        'from' => $total ? $offset + 1 : 0,
        'to' => min($total, $offset + $perPage),
        'total' => $total,
    ];
}
function page_url(int $page): string {
    $query = $_GET;
    $query['page'] = $page;
    return '?'.http_build_query($query);
}
function render_pagination(array $pager): string {
    if ($pager['pages'] <= 1) return '';
    $current = $pager['page']; $pages = $pager['pages'];
    $start = max(1, $current - 2); $end = min($pages, $current + 2);
    $html = '<nav class="pagination" aria-label="التنقل بين الصفحات">';
    if ($current > 1) $html .= '<a class="page-arrow" href="'.e(page_url($current - 1)).'" aria-label="الصفحة السابقة">‹</a>';
    if ($start > 1) $html .= '<a href="'.e(page_url(1)).'">1</a>'.($start > 2 ? '<span>…</span>' : '');
    for ($i=$start; $i<=$end; $i++) {
        $html .= $i === $current
            ? '<span class="active" aria-current="page">'.$i.'</span>'
            : '<a href="'.e(page_url($i)).'">'.$i.'</a>';
    }
    if ($end < $pages) $html .= ($end < $pages - 1 ? '<span>…</span>' : '').'<a href="'.e(page_url($pages)).'">'.$pages.'</a>';
    if ($current < $pages) $html .= '<a class="page-arrow" href="'.e(page_url($current + 1)).'" aria-label="الصفحة التالية">›</a>';
    return $html.'</nav>';
}
function badge_class(string $status): string {
    $green=['تم الرد','اجتماع','عرض مرسل','عرض','مقبول','تم القبول','عميل','فاز','عرض وظيفي'];
    $blue=['تم الإرسال','تواصل','مقابلة','اختبار','قيد المراجعة','Applied','تم التقديم'];
    $amber=['جاهز للإرسال','تحضير','متابعة','مؤجل','مؤجل حالياً','انتظار'];
    $red=['مرفوض','مغلق','منسحب','خسر','لا متابعة'];
    if (in_array($status,$green,true)) return 'badge success';
    if (in_array($status,$blue,true)) return 'badge info';
    if (in_array($status,$amber,true)) return 'badge warning';
    if (in_array($status,$red,true)) return 'badge danger';
    return 'badge neutral';
}
function date_or_dash(?string $date): string { return $date ? date('Y-m-d', strtotime($date)) : '—'; }

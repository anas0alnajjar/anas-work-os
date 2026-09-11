<?php
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

function reminder_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function reminder_timezone(string $timezone): DateTimeZone {
    try { return new DateTimeZone($timezone); }
    catch (Throwable) { throw new RuntimeException('Invalid user timezone.'); }
}

function reminder_collect_content_items(int $userId, string $timezone, int $daysAhead = 2, bool $dueOnly = false): array {
    $now = new DateTimeImmutable('now',reminder_timezone($timezone));
    $until = $now->modify('+'.max(0,$daysAhead).' days')->format('Y-m-d 23:59:59');
    $window = $dueOnly
        ? "((p.custom_reminder_at IS NOT NULL AND p.custom_reminder_at<=?) OR (p.custom_reminder_at IS NULL AND p.post_status='scheduled' AND p.scheduled_at<=?))"
        : "((p.custom_reminder_at IS NOT NULL AND p.custom_reminder_at<=?) OR (p.custom_reminder_at IS NULL AND p.post_status='scheduled' AND p.scheduled_at BETWEEN ? AND ?))";
    $params = $dueOnly
        ? [$userId,$now->format('Y-m-d H:i:s'),$now->format('Y-m-d H:i:s')]
        : [$userId,$now->format('Y-m-d H:i:s'),$now->format('Y-m-d H:i:s'),$until];
    $posts = q(
        "SELECT p.id,p.topic_id,p.platform,p.post_title,p.post_status,p.scheduled_at,p.custom_reminder_at,t.title_ar
         FROM content_posts p JOIN content_topics t ON t.id=p.topic_id
         WHERE t.user_id=? AND p.reminder_sent_at IS NULL AND p.post_status<>'published' AND {$window}
         ORDER BY COALESCE(p.custom_reminder_at,p.scheduled_at) ASC",
        $params
    )->fetchAll();
    $platformLabels = ['linkedin'=>'LinkedIn','facebook'=>'Facebook','instagram'=>'Instagram'];
    $items = [];
    foreach ($posts as $post) {
        $reminderDate = $post['custom_reminder_at'] ?: $post['scheduled_at'];
        $items[] = [
            'kind'=>'خطة النشر',
            'title'=>($post['post_title'] ?: $post['title_ar']).' — '.($platformLabels[$post['platform']] ?? $post['platform']),
            'date'=>$reminderDate,
            'meta'=>$post['custom_reminder_at'] ? 'موعد التذكير المخصص' : 'موعد النشر قريب',
            'url'=>'content_topic.php?id='.(int)$post['topic_id'].'#post-'.$post['platform'],
            'overdue'=>$reminderDate < $now->format('Y-m-d H:i:s'),
            'content_post_id'=>(int)$post['id'],
        ];
    }
    return $items;
}

function reminder_collect_items(int $userId, int $daysAhead = 2, ?int $jobId = null, string $timezone = 'Asia/Damascus'): array {
    $now = new DateTimeImmutable('now',reminder_timezone($timezone));
    $today = $now->format('Y-m-d');
    $untilDate = $now->modify('+' . max(0,$daysAhead) . ' days');
    $until = $untilDate->format('Y-m-d');
    $items = [];

    $jobWhere = $jobId ? ' AND id = ?' : '';
    $jobParams = [$userId,$until];
    if ($jobId) $jobParams[] = $jobId;
    $jobs = q(
        "SELECT id,company,job_title,status,next_followup_date FROM job_applications
         WHERE user_id=? AND next_followup_date IS NOT NULL AND next_followup_date <= ?
         AND status NOT IN ('مقبول','مرفوض','منسحب','مغلق'){$jobWhere}
         ORDER BY next_followup_date ASC",
        $jobParams
    )->fetchAll();
    foreach ($jobs as $job) {
        $items[] = [
            'kind' => 'طلب عمل',
            'title' => $job['job_title'] . ' — ' . $job['company'],
            'date' => $job['next_followup_date'],
            'meta' => 'المرحلة: ' . $job['status'],
            'url' => 'job_view.php?id=' . (int)$job['id'],
            'overdue' => $job['next_followup_date'] < $today,
        ];
    }

    if ($jobId === null) {
        $contacts = q(
            "SELECT c.id,c.name,c.next_followup_date,o.name organization_name FROM contacts c
             JOIN organizations o ON o.id=c.organization_id
             WHERE o.user_id=? AND c.next_followup_date IS NOT NULL AND c.next_followup_date <= ?
             ORDER BY c.next_followup_date ASC",
            [$userId,$until]
        )->fetchAll();
        foreach ($contacts as $contact) {
            $items[] = [
                'kind' => 'متابعة تواصل',
                'title' => $contact['name'] . ' — ' . $contact['organization_name'],
                'date' => $contact['next_followup_date'],
                'meta' => 'موعد التواصل التالي',
                'url' => 'contact_view.php?id=' . (int)$contact['id'],
                'overdue' => $contact['next_followup_date'] < $today,
            ];
        }
    }

    $interviewParams = [$userId,$now->format('Y-m-d H:i:s'),$untilDate->format('Y-m-d 23:59:59')];
    $interviewWhere = $jobId ? ' AND i.job_application_id = ?' : '';
    if ($jobId) $interviewParams[] = $jobId;
    $interviews = q(
        "SELECT i.id,i.job_application_id,i.interview_type,i.scheduled_at,i.interview_format,j.company,j.job_title
         FROM job_interviews i JOIN job_applications j ON j.id=i.job_application_id
         WHERE j.user_id=? AND i.scheduled_at BETWEEN ? AND ? AND i.status IN ('مجدولة','Scheduled'){$interviewWhere}
         ORDER BY i.scheduled_at ASC",
        $interviewParams
    )->fetchAll();
    foreach ($interviews as $interview) {
        $items[] = [
            'kind' => 'مقابلة',
            'title' => $interview['job_title'] . ' — ' . $interview['company'],
            'date' => $interview['scheduled_at'],
            'meta' => $interview['interview_type'] . ' · ' . $interview['interview_format'],
            'url' => 'interview_form.php?job_id=' . (int)$interview['job_application_id'] . '&id=' . (int)$interview['id'],
            'overdue' => false,
        ];
    }

    if ($jobId === null) $items = [...$items,...reminder_collect_content_items($userId,$timezone,$daysAhead,false)];

    usort($items, static fn(array $a, array $b): int => strcmp($a['date'], $b['date']));
    return $items;
}

function reminder_render(array $items, string $appName): array {
    $rows = '';
    $plainLines = [$appName . ' — التذكيرات المهنية', ''];
    foreach ($items as $item) {
        $dateLabel = date('Y-m-d H:i', strtotime($item['date']));
        $state = $item['overdue'] ? 'متأخر' : 'قادم';
        $stateColor = $item['overdue'] ? '#b42318' : '#0088cc';
        $rows .= '<tr><td style="padding:18px 0;border-bottom:1px solid #e7edf1">'
            . '<div style="font-size:12px;font-weight:700;color:' . $stateColor . '">' . reminder_escape($item['kind']) . ' · ' . $state . '</div>'
            . '<div style="margin-top:5px;font-size:17px;font-weight:800;color:#002347">' . reminder_escape($item['title']) . '</div>'
            . '<div style="margin-top:5px;font-size:13px;color:#4a5568">' . reminder_escape($item['meta']) . ' · ' . reminder_escape($dateLabel) . '</div>'
            . '</td></tr>';
        $plainLines[] = '[' . $item['kind'] . '] ' . $item['title'] . ' — ' . $dateLabel . ' — ' . $item['meta'];
    }
    $count = count($items);
    $html = '<!doctype html><html lang="ar" dir="rtl"><body style="margin:0;background:#f4f7f9;font-family:Arial,Tahoma,sans-serif;color:#002347">'
        . '<div style="max-width:640px;margin:0 auto;padding:28px 16px">'
        . '<div style="padding:26px;background:#002347;border-radius:16px 16px 0 0;color:#fff"><div style="font-size:12px;color:#66c9f5">ANAS WORK OS</div>'
        . '<h1 style="margin:8px 0 4px;font-size:25px">لديك ' . $count . ' متابعة تحتاج انتباهك</h1><p style="margin:0;color:#c8d4df;font-size:14px">ملخص واضح للطلبات، العلاقات، والمقابلات القادمة.</p></div>'
        . '<table role="presentation" style="width:100%;border-collapse:collapse;background:#fff;padding:0 26px"><tbody>' . $rows . '</tbody></table>'
        . '<div style="padding:18px 26px;border-top:1px solid #e7edf1;border-radius:0 0 16px 16px;background:#fff;color:#71818d;font-size:12px">تم الإرسال تلقائياً من ' . reminder_escape($appName) . '.</div>'
        . '</div></body></html>';
    return [$html, implode("\n", $plainLines)];
}

function reminder_record_delivery(int $userId, string $dispatchKey, string $recipient, array $items, string $localDate): void {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('INSERT INTO reminder_dispatches(user_id,dispatch_key,recipient,item_count) VALUES(?,?,?,?)',[$userId,$dispatchKey,$recipient,count($items)]);
        $contentPostIds = array_values(array_unique(array_map(
            static fn(array $item): int => (int)($item['content_post_id'] ?? 0),
            array_filter($items,static fn(array $item): bool => !empty($item['content_post_id']))
        )));
        if ($contentPostIds) {
            $placeholders = implode(',',array_fill(0,count($contentPostIds),'?'));
            q("UPDATE content_posts p JOIN content_topics t ON t.id=p.topic_id SET p.reminder_sent_at=NOW() WHERE p.id IN ({$placeholders}) AND t.user_id=?",[...$contentPostIds,$userId]);
            q('INSERT INTO content_reminder_logs(user_id,reminder_date,recipient_email,content_summary,send_status) VALUES(?,?,?,?,?)',[
                $userId,$localDate,$recipient,count($contentPostIds).' content post reminder(s)','sent'
            ]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function reminder_send_content_due(int $userId): array {
    global $config;
    $account = q('SELECT reminder_email,reminders_enabled,account_status,timezone FROM users WHERE id=?',[$userId])->fetch();
    if (!$account || $account['account_status'] !== 'active' || !(int)$account['reminders_enabled']) return ['sent'=>false,'count'=>0,'reason'=>'disabled'];
    $recipient = trim((string)$account['reminder_email']);
    if (!filter_var($recipient,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Reminder recipient is not configured.');
    $timezone = (string)($account['timezone'] ?: 'Asia/Damascus');
    $localNow = new DateTimeImmutable('now',reminder_timezone($timezone));
    $items = reminder_collect_content_items($userId,$timezone,0,true);
    if (!$items) return ['sent'=>false,'count'=>0,'reason'=>'empty'];
    $dispatchKey = 'content-hour:'.$localNow->format('Y-m-d-H');
    if (scalar('SELECT COUNT(*) FROM reminder_dispatches WHERE user_id=? AND dispatch_key=?',[$userId,$dispatchKey])) {
        return ['sent'=>false,'count'=>count($items),'reason'=>'duplicate'];
    }
    [$html,$plain] = reminder_render($items,(string)($config['app_name'] ?? 'Anas Work OS'));
    smtp_send($config['mail'] ?? [],$recipient,'تذكير خطة النشر — '.count($items).' عناصر جاهزة',$html,$plain);
    reminder_record_delivery($userId,$dispatchKey,$recipient,$items,$localNow->format('Y-m-d'));
    return ['sent'=>true,'count'=>count($items),'reason'=>'sent'];
}

function reminder_send_digest(bool $force = false, ?int $jobId = null, ?int $userId = null): array {
    global $config;
    $mail = $config['mail'] ?? [];
    if (!$userId) $userId = current_user() ? current_user_id() : 0;
    if ($userId < 1) return ['sent'=>false,'count'=>0,'reason'=>'no-user'];
    $account = q("SELECT reminder_email,reminders_enabled,account_status,timezone FROM users WHERE id=?",[$userId])->fetch();
    if (!$account || $account['account_status'] !== 'active' || !(int)$account['reminders_enabled']) return ['sent'=>false,'count'=>0,'reason'=>'disabled'];
    $recipient = trim((string)($account['reminder_email'] ?? ''));
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Reminder recipient is not configured.');

    $timezone = (string)($account['timezone'] ?: 'Asia/Damascus');
    $localNow = new DateTimeImmutable('now',reminder_timezone($timezone));
    $items = reminder_collect_items($userId,2,$jobId,$timezone);
    if (!$items && !$force) return ['sent' => false, 'count' => 0, 'reason' => 'empty'];

    if (!$items && $jobId) {
        $job = q('SELECT id,company,job_title,status,next_followup_date FROM job_applications WHERE id=? AND user_id=?', [$jobId,$userId])->fetch();
        if ($job) {
            $items[] = [
                'kind' => 'طلب عمل',
                'title' => $job['job_title'] . ' — ' . $job['company'],
                'date' => $job['next_followup_date'] ?: $localNow->format('Y-m-d'),
                'meta' => 'المرحلة: ' . $job['status'],
                'url' => 'job_view.php?id=' . (int)$job['id'],
                'overdue' => false,
            ];
        }
    }
    if (!$items) return ['sent' => false, 'count' => 0, 'reason' => 'empty'];

    $dispatchKey = $jobId === null ? 'daily:' . $localNow->format('Y-m-d') : 'manual-job:' . $jobId . ':' . $localNow->format('YmdHis') . ':' . bin2hex(random_bytes(3));
    if (!$force && scalar('SELECT COUNT(*) FROM reminder_dispatches WHERE user_id=? AND dispatch_key=?', [$userId,$dispatchKey])) {
        return ['sent' => false, 'count' => count($items), 'reason' => 'duplicate'];
    }

    [$html, $plain] = reminder_render($items, (string)($config['app_name'] ?? 'Anas Work OS'));
    $subject = 'تذكيرات العمل — ' . count($items) . ' عناصر تحتاج المتابعة';
    smtp_send($mail, $recipient, $subject, $html, $plain);
    reminder_record_delivery($userId,$dispatchKey,$recipient,$items,$localNow->format('Y-m-d'));
    return ['sent' => true, 'count' => count($items), 'reason' => 'sent'];
}
